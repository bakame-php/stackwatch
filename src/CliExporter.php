<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function number_format;

final class CliExporter implements Exporter
{
    public function __construct(private readonly OutputInterface $output = new ConsoleOutput())
    {
    }

    public function exportProfilingData(ProfilingData $profilingData): void
    {
        $table = new Table($this->output);
        $table->setHeaders([
            'Label',
            'CPU Time (s)',
            'Exec Time (s)',
            'Memory (kB)',
            'Real Mem (kB)',
            'Peak Mem (kB)',
            'Real Peak (kB)',
        ]);

        $table->addRow($this->profilingDataToRow($profilingData));
        $table->render();
    }

    public function exportProfiler(Profiler $profiler): void
    {
        $table = new Table($this->output);
        $table->setHeaders([
            'Label',
            'CPU Time (s)',
            'Exec Time (s)',
            'Memory (kB)',
            'Real Mem (kB)',
            'Peak Mem (kB)',
            'Real Peak (kB)',
        ]);

        foreach ($profiler as $profile) {
            $table->addRow($this->profilingDataToRow($profile));
        }

        $table->render();
    }

    /**
     * @return list<string|TableCell>
     */
    private function profilingDataToRow(ProfilingData $profile): array
    {
        $metrics = $profile->metrics;

        return [
            $profile->label,
            number_format($metrics->cpuTime, 9),
            number_format($metrics->executionTime, 9),
            number_format($metrics->memoryUsage / 1024, 1),
            number_format($metrics->realMemoryUsage / 1024, 1),
            number_format($metrics->peakMemoryUsage / 1024, 1),
            number_format($metrics->realPeakMemoryUsage / 1024, 1),
        ];
    }
}
