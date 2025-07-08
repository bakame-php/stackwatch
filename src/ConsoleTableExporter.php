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
 */
final class ConsoleTableExporter implements Exporter
{
    public function __construct(private readonly OutputInterface $output = new ConsoleOutput())
    {
    }

    public function exportProfilingData(ProfilingResult|ProfilingData $profilingData): void
    {
        if ($profilingData instanceof ProfilingResult) {
            $profilingData = $profilingData->profilingData;
        }

        $table = $this->createTable();
        $table->addRow($this->profilingDataToRow($profilingData));
        $table->render();
    }

    public function exportProfiler(Profiler $profiler, ?string $label = null): void
    {
        $input = null === $label ? $profiler : $profiler->getAll($label);
        $table = $this->createTable();
        foreach ($input as $profilingData) {
            $table->addRow($this->profilingDataToRow($profilingData));
        }
        $table->addRow(new TableSeparator());
        /** @var MetricsHumanReadable $metrics */
        $metrics = $profiler->average($label)->forHuman();
        $row = [
            '<fg=green>Average</>',
            $metrics['cpu_time'],
            $metrics['execution_time'],
            $metrics['memory_usage'],
            $metrics['real_memory_usage'],
            $metrics['peak_memory_usage'],
            $metrics['real_peak_memory_usage'],
        ];
        $table->addRow($row);
        $table->render();
    }

    public function exportTimeline(Timeline $timeline): void
    {
        $table = $this->createTable();
        if (! $timeline->hasIntervals()) {
            $row = [new TableCell('<fg=yellow>No timeline</>', ['colspan' => 7])];
            $table->addRow($row);
            $table->render();
            return;
        }
        foreach ($timeline->reports() as $profilingData) {
            $table->addRow($this->profilingDataToRow($profilingData));
        }
        $table->addRow(new TableSeparator());
        /** @var ProfilingData $summary */
        $summary = $timeline->summary();
        /** @var MetricsHumanReadable $metrics */
        $metrics = $summary->metrics->forHuman();
        $row = [
            '<fg=green>Summary</>',
            $metrics['cpu_time'],
            $metrics['execution_time'],
            $metrics['memory_usage'],
            $metrics['real_memory_usage'],
            $metrics['peak_memory_usage'],
            $metrics['real_peak_memory_usage'],
        ];
        $table->addRow($row);
        $table->render();
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
     * @return list<string>
     */
    private function profilingDataToRow(ProfilingData $profilingData): array
    {
        /** @var MetricsHumanReadable $metrics */
        $metrics = $profilingData->metrics->forHuman();

        return [
            $profilingData->label,
            $metrics['cpu_time'],
            $metrics['execution_time'],
            $metrics['memory_usage'],
            $metrics['real_memory_usage'],
            $metrics['peak_memory_usage'],
            $metrics['real_peak_memory_usage'],
        ];
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        /** @var SnapshotHumanReadable $stats */
        $stats = $snapshot->forHuman();
        $table = (new Table($this->output))
            ->setHeaders(['Timestamp', 'Memory Usage', 'Real Memory Usage', 'Peak Memory Usage', 'Real Peak Memory Usage', 'CPU'])
            ->setRows([
                [
                    $stats['timestamp'],
                    $stats['memory_usage'],
                    $stats['real_memory_usage'],
                    $stats['peak_memory_usage'],
                    $stats['real_peak_memory_usage'],
                    $stats['cpu'],
                ],
            ]);
        $table->setVertical();
        $table->render();
    }

    public function exportEnvironment(Environment $environment): void
    {
        $memoryLimit = $environment->memoryLimit;
        if (null !== $memoryLimit && 0 < $memoryLimit) {
            $memoryLimit = MemoryUnit::format($memoryLimit);
        }

        $table = (new Table($this->output))
            ->setHeaders([
                'Operating System',
                'OS Family',
                'Hostname',
                'Architecture',
                'PHP Integer Size',
                'PHP Architecture',
                'SAPI',
                'Memory Limit',
                'Raw Memory Limit',
                'CPU Cores',
                'Disk Size',
                'Free Disk Space',
            ])
            ->setRows([
                [
                    $environment->os,
                    $environment->osFamily,
                    $environment->hostname,
                    $environment->machine,
                    $environment->phpIntSize,
                    $environment->phpArchitecture,
                    $environment->sapi,
                    $memoryLimit,
                    $environment->rawMemoryLimit,
                    $environment->cpuCores,
                    MemoryUnit::format($environment->totalDisk, 1),
                    MemoryUnit::format($environment->freeDisk, 1),
                ],
            ]);

        $table->setVertical();
        $table->render();
    }
}
