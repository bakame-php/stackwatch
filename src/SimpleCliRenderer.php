<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function number_format;

final class SimpleCliRenderer implements Renderer
{
    public function __construct(private readonly OutputInterface $output = new ConsoleOutput())
    {
    }

    public function render(Profiler $profiler): void
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
            $table->addRow($this->profileToRow($profile))  ;
        }

        $table->render();
    }

    /**
     * @return list<string|TableCell>
     */
    public function profileToRow(Profile $profile): array
    {
        if ($profile->hasNotBegun()) {
            return [$profile->label(), new TableCell('The profiling has not started', ['colspan' => 6])];
        }

        if ($profile->isRunning()) {
            return [$profile->label(), new TableCell('The profiling is not finished', ['colspan' => 6])];
        }

        return [
            $profile->label(),
            number_format($profile->cpuTime(), 6),
            number_format($profile->executionTime(), 6),
            number_format($profile->memoryUsage() / 1024, 1),
            number_format($profile->realMemoryUsage() / 1024, 1),
            number_format($profile->peakMemoryUsage() / 1024, 1),
            number_format($profile->realPeakMemoryUsage() / 1024, 1),
        ];
    }
}
