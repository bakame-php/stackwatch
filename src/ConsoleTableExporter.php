<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

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
final class ConsoleTableExporter implements Exporter
{
    public function __construct(private readonly OutputInterface $output = new ConsoleOutput())
    {
    }

    public function exportSummary(ProfiledResult|Summary $summary, Profiler|Marker|null $parent = null): void
    {
        if ($summary instanceof ProfiledResult) {
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

    public function exportMarker(Marker $marker): void
    {
        if (! $marker->hasEnoughSnapshots()) {
            $this
                ->createTable()
                ->setHeaderTitle($marker->identifier())
                ->addRow([new TableCell('<fg=yellow>Not enough snapshot to generate an export</>', ['colspan' => 7])])
                ->render();

            return;
        }

        /** @var Summary $summary */
        $summary = $marker->summary();

        $this
            ->createSummaryTable($marker->deltas())
            ->setHeaderTitle(' '.$marker->identifier().' ')
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
            'Exec Time',
            'Memory',
            'Real Mem',
            'Peak Mem',
            'Real Peak',
        ]);
    }

    /**
     * @param iterable<Summary> $summaries
     */
    private function createSummaryTable(iterable $summaries, Profiler|Marker|null $parent = null): Table
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
                'Memory Usage',
                'Real Memory Usage',
                'Peak Memory Usage',
                'Real Peak Memory Usage',
                'CPU',
            ])
            ->addRow([
                $stats['timestamp'],
                $stats['memory_usage'],
                $stats['real_memory_usage'],
                $stats['peak_memory_usage'],
                $stats['real_peak_memory_usage'],
                $stats['cpu'],
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
                $stats['min'],
                $stats['max'],
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
