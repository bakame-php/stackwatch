<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\Console\Exporter;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Statistics;
use Bakame\Stackwatch\Summary;
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
 */
final class ConsoleExporter implements Exporter
{
    public function __construct(public readonly OutputInterface $output = new ConsoleOutput())
    {
    }

    public function exportSummary(Result|Summary $summary, Profiler|Timeline|null $parent = null): void
    {
        if ($summary instanceof Result) {
            $summary = $summary->summary;
        }

        $this->createSummaryTable([$summary], $parent)->render();
    }

    public function exportProfiler(Profiler $profiler, ?string $label = null): void
    {
        $input = null === $label ? $profiler : $profiler->getAll($label);

        $this
            ->createSummaryTable($input)
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

        /** @var Summary $summary */
        $summary = $timeline->summarize();

        $this
            ->createSummaryTable($timeline->deltas())
            ->setHeaderTitle(' '.$timeline->identifier().' ')
            ->addRow(new TableSeparator())
            ->addRow($this->metricsToRow('<fg=green>Summary</>', $summary->metrics))
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
     * @param iterable<Summary> $summaries
     */
    private function createSummaryTable(iterable $summaries, Profiler|Timeline|null $parent = null): Table
    {
        $table = $this->createTable();
        if (null !== $parent) {
            $table->setHeaderTitle(' '.$parent->identifier().' ');
        }

        foreach ($summaries as $summary) {
            $table->addRow($this->metricsToRow($summary->label, $summary->metrics));
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

    public function exportMetrics(Result|Summary|Metrics $metrics): void
    {
        /** @var MetricsHumanReadable $stats */
        $stats = (match (true) {
            $metrics instanceof Result => $metrics->summary->metrics,
            $metrics instanceof Summary => $metrics->metrics,
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
                $stats['execution_time'],
                $stats['cpu_time'],
                $stats['memory_usage'],
                $stats['real_memory_usage'],
                $stats['peak_memory_usage'],
                $stats['real_peak_memory_usage'],
            ])
            ->setVertical()
            ->render();
    }

    public function exportStatistics(Statistics $statistics): void
    {
        /** @var StatsHumanReadable $stats */
        $stats = $statistics->forHuman();

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
            'peakMemoryUsage' => $report->peakMemoryUsage->forHuman(),
            'realMemoryUsage' => $report->realMemoryUsage->forHuman(),
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
        $memoryLimit = $environment->memoryLimit;
        if (null !== $memoryLimit && 0 < $memoryLimit) {
            $memoryLimit = MemoryUnit::format($memoryLimit);
        }

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
                $environment->os,
                $environment->osFamily,
                $environment->hostname,
                $environment->machine,
                $environment->phpIntSize,
                $environment->phpArchitecture,
                $environment->sapi,
                $environment->phpVersion,
                $memoryLimit,
                $environment->rawMemoryLimit,
                $environment->cpuCores,
                MemoryUnit::format($environment->totalDisk, 1),
                MemoryUnit::format($environment->freeDisk, 1),
            ])
            ->setVertical()
            ->render();
    }
}
