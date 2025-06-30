<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleTableExporter implements Exporter
{
    public function __construct(private readonly OutputInterface $output = new ConsoleOutput())
    {
    }

    public function exportProfilingData(ProfilingResult|ProfilingData $profilingData): void
    {
        $this->profilingDataToTable($profilingData)->render();
    }

    public function exportProfiler(Profiler $profiler): void
    {
        $this->profilerToTable($profiler)->render();
    }

    public function profilerToTable(Profiler $profiler): Table
    {
        $table = $this->createTable();
        foreach ($profiler as $profilingData) {
            $table->addRow($this->profilingDataToRow($profilingData));
        }

        return $table;
    }

    public function profilingDataToTable(ProfilingResult|ProfilingData $profilingData): Table
    {
        if ($profilingData instanceof ProfilingResult) {
            $profilingData = $profilingData->profilingData;
        }

        $table = $this->createTable();
        $table->addRow($this->profilingDataToRow($profilingData));

        return $table;
    }

    private function createTable(): Table
    {
        $table = new Table($this->output);
        return $table->setHeaders([
            'Label',
            'CPU Time (s)',
            'Exec Time (s)',
            'Memory (kB)',
            'Real Mem (kB)',
            'Peak Mem (kB)',
            'Real Peak (kB)',
        ]);
    }

    /**
     * @return list<string>
     */
    private function profilingDataToRow(ProfilingData $profilingData): array
    {
        $metrics = $profilingData->metrics;

        return [
            $profilingData->label,
            TimeUnit::Second->formatFromNanoseconds($metrics->cpuTime, 9),
            TimeUnit::Second->formatFromNanoseconds($metrics->executionTime, 9),
            MemoryUnit::Kilobyte->formatFromBytes($metrics->memoryUsage, 1),
            MemoryUnit::Kilobyte->formatFromBytes($metrics->realMemoryUsage, 1),
            MemoryUnit::Kilobyte->formatFromBytes($metrics->peakMemoryUsage, 1),
            MemoryUnit::Kilobyte->formatFromBytes($metrics->realPeakMemoryUsage, 1),
        ];
    }
}
