<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\ConsoleTableExporter;
use Bakame\Stackwatch\Metrics;
use Throwable;

final class ConsoleTableProcessor implements Processor
{
    public function __construct(public readonly ConsoleTableExporter $exporter)
    {
    }

    /**
     * @param iterable<Target> $targets
     *
     * @throws Throwable
     */
    public function process(iterable $targets): void
    {
        foreach ($targets as $target) {
            $this->exporter->output->writeln($target->toConsoleString());
            $stats = $target->generate();
            $stats instanceof Metrics ? $this->exporter->exportMetrics($stats) : $this->exporter->exportReport($stats);
        }
    }
}
