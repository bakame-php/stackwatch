<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\Console\Exporter;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Statistics;
use Bakame\Stackwatch\Timeline;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-import-type MetricsHumanReadable from Metrics
 * @phpstan-import-type SnapshotHumanReadable from Snapshot
 * @phpstan-import-type StatsHumanReadable from Statistics
 * @phpstan-import-type EnvironmentHumanReadable from Environment
 */
final class ConsoleExporter implements Exporter
{
    public function __construct(public readonly OutputInterface $output = new ConsoleOutput())
    {
    }

    public function exportSummary(Result|Span $span, Profiler|Timeline|null $parent = null): void
    {
        if ($span instanceof Result) {
            $span = $span->span;
        }

        $this->createSpanTable([$span], $parent)->render();
    }

    public function exportProfiler(Profiler $profiler, ?string $label = null): void
    {
        $input = null === $label ? $profiler : $profiler->getAll($label);

        $this
            ->createSpanTable($input)
            ->setHeaderTitle(' '.$profiler->identifier().' ')
            ->addRow(new TableSeparator())
            ->addRow($this->metricsToRow('<fg=green>Average</>', $profiler->average($label)))
            ->render();
    }

    public function exportTimeline(Timeline $timeline): void
    {
        if (! $timeline->hasEnoughSnapshots()) {
            $this
                ->createTable()
                ->setHeaderTitle($timeline->identifier())
                ->addRow([new TableCell('<fg=yellow>Not enough snapshot to generate an export</>', ['colspan' => 7])])
                ->render();

            return;
        }

        /** @var Span $span */
        $span = $timeline->summarize();

        $this
            ->createSpanTable($timeline->deltas())
            ->setHeaderTitle(' '.$timeline->identifier().' ')
            ->addRow(new TableSeparator())
            ->addRow($this->metricsToRow('<fg=green>Summary</>', $span->metrics))
            ->render();
    }

    private function createTable(): Table
    {
        return (new Table($this->output))
            ->setHeaders([
            'Label',
            'CPU Time',
            'Execution Time',
            'Memory Usage',
            'Real Memory Usage',
            'Peak Memory Usage',
            'Real Peak Memory Usage',
        ]);
    }

    /**
     * @param iterable<Span> $spans
     */
    private function createSpanTable(iterable $spans, Profiler|Timeline|null $parent = null): Table
    {
        $table = $this->createTable();
        if (null !== $parent) {
            $table->setHeaderTitle(' '.$parent->identifier().' ');
        }

        foreach ($spans as $span) {
            $table->addRow($this->metricsToRow($span->label, $span->metrics));
        }

        return $table;
    }

    /**
     * @return list<string>
     */
    private function metricsToRow(string $formattedLabel, Metrics $metrics): array
    {
        /** @var MetricsHumanReadable $formattedMetrics */
        $formattedMetrics = $metrics->forHuman();

        return [
            $formattedLabel,
            $formattedMetrics['cpu_time'],
            $formattedMetrics['execution_time'],
            $formattedMetrics['memory_usage'],
            $formattedMetrics['real_memory_usage'],
            $formattedMetrics['peak_memory_usage'],
            $formattedMetrics['real_peak_memory_usage'],
        ];
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        /** @var SnapshotHumanReadable $stats */
        $stats = $snapshot->forHuman();

        (new Table($this->output))
            ->setHeaders([
                'Timestamp',
                'Call Location Path',
                'Call Location Line',
                'Memory Usage',
                'Real Memory Usage',
                'Peak Memory Usage',
                'Real Peak Memory Usage',
                'CPU System Time',
                'CPU User Time',
                'CPU Total Time',
            ])
            ->addRow([
                $stats['timestamp'],
                $stats['origin_path'],
                $stats['origin_line'],
                $stats['memory_usage'],
                $stats['real_memory_usage'],
                $stats['peak_memory_usage'],
                $stats['real_peak_memory_usage'],
                $stats['cpu_system_time'],
                $stats['cpu_user_time'],
                $stats['cpu_total_time'],
            ])
            ->setVertical()
            ->render();
    }

    public function exportMetrics(Result|Span|Metrics $metrics): void
    {
        /** @var MetricsHumanReadable $map */
        $map = (match (true) {
            $metrics instanceof Result => $metrics->span->metrics,
            $metrics instanceof Span => $metrics->metrics,
            default => $metrics,
        })->forHuman();

        (new Table($this->output))
            ->setHeaders([
                'Execution Time',
                'CPU Time',
                'Memory Usage',
                'Real Memory Usage',
                'Peak Memory Usage',
                'Real Peak Memory Usage',
            ])
            ->addRow([
                $map['execution_time'],
                $map['cpu_time'],
                $map['memory_usage'],
                $map['real_memory_usage'],
                $map['peak_memory_usage'],
                $map['real_peak_memory_usage'],
            ])
            ->setVertical()
            ->render();
    }

    public function exportStatistics(Statistics $statistics): void
    {
        /** @var StatsHumanReadable $map */
        $map = $statistics->forHuman();

        (new Table($this->output))
            ->setHeaders([
                'Nb Iterations',
                'Min Value',
                'Max Value',
                'Median Value',
                'Sum',
                'Range',
                'Average',
                'Variance',
                'Std Dev',
                'Coef Var',
            ])
            ->addRow([
                $map['count'],
                $map['minimum'],
                $map['maximum'],
                $map['median'],
                $map['sum'],
                $map['range'],
                $map['average'],
                $map['variance'],
                $map['std_dev'],
                $map['coef_var'],
            ])
            ->setVertical()
            ->render();
    }

    public function exportReport(Report $report): void
    {
        static $reportPropertyNames = [
            'cpuTime' => 'CPU Time',
            'executionTime' => 'Execution Time',
            'memoryUsage' => 'Memory Usage',
            'realMemoryUsage' => 'Real Memory Usage',
            'peakMemoryUsage' => 'Peak Memory Usage',
            'realPeakMemoryUsage' =>  'Real Peak Memory Usage',
        ];

        $reportData = [
            'cpuTime' => $report->cpuTime->forHuman(),
            'executionTime' => $report->executionTime->forHuman(),
            'memoryUsage' => $report->memoryUsage->forHuman(),
            'realMemoryUsage' => $report->realMemoryUsage->forHuman(),
            'peakMemoryUsage' => $report->peakMemoryUsage->forHuman(),
            'realPeakMemoryUsage' => $report->realPeakMemoryUsage->forHuman(),
        ];

        $table = (new Table($this->output))
            ->setHeaders([
                'Metric ',
                'Nb Iterations',
                'Min Value',
                'Max Value',
                'Median Value',
                'Sum',
                'Range',
                'Average',
                'Variance',
                'Std Dev',
                'Coef Var',
            ]);

        foreach ($reportData as $name => $stats) {
            $table->addRow([
                $reportPropertyNames[$name],
                $stats['count'],
                $stats['minimum'],
                $stats['maximum'],
                $stats['median'],
                $stats['sum'],
                $stats['range'],
                $stats['average'],
                $stats['variance'],
                $stats['std_dev'],
                $stats['coef_var'],
            ]);
        }

        $table->render();
    }

    public function exportEnvironment(Environment $environment): void
    {
        /** @var EnvironmentHumanReadable $map */
        $map = $environment->forHuman();

        (new Table($this->output))
            ->setHeaders([
                'Operating System',
                'OS Family',
                'Hostname',
                'Architecture',
                'PHP Integer Size',
                'PHP Architecture',
                'SAPI',
                'PHP Version',
                'Memory Limit',
                'Raw Memory Limit',
                'CPU Cores',
                'Disk Size',
                'Free Disk Space',
            ])
            ->addRow([
                $map['os'],
                $map['os_family'],
                $map['hostname'],
                $map['machine'],
                $map['php_int_size'],
                $map['php_architecture'],
                $map['sapi'],
                $map['php_version'],
                $map['memory_limit'],
                $map['raw_memory_limit'],
                $map['cpu_cores'],
                $map['total_disk'],
                $map['free_disk'],
            ])
            ->setVertical()
            ->render();
    }
}
