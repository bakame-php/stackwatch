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
     * @param iterable<UnitOfWork> $unitOfWorks
     *
     * @throws Throwable
     */
    public function process(iterable $unitOfWorks): void
    {
        foreach ($unitOfWorks as $unitOfWork) {
            $unitOfWork->run();

            $stats = $unitOfWork->result();
            $this->exporter->output->writeln($unitOfWork->toConsoleString());
            $stats instanceof Metrics ? $this->exporter->exportMetrics($stats) : $this->exporter->exportReport($stats);
        }
    }
}
