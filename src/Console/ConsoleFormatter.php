<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\ViewExporter;
use Bakame\Stackwatch\Report;
use Throwable;

final class ConsoleFormatter implements Formatter
{
    public function __construct(
        public readonly ViewExporter $exporter,
        public readonly Feature $dryRun = Feature::Disabled,
    ) {
    }

    /**
     * @param iterable<UnitOfWork> $unitOfWorks
     *
     * @throws Throwable
     */
    public function format(iterable $unitOfWorks): void
    {
        foreach ($unitOfWorks as $unitOfWork) {
            $this->exporter->output->writeln($unitOfWork->toConsoleString());
            if ($this->dryRun->isEnabled()) {
                continue;
            }

            $this->exporter->output->writeln('');
            $stats = $unitOfWork->result();
            if ($stats instanceof Report) {
                $this->exporter->exportReport($stats);
                $this->exporter->output->writeln('');
                continue;
            }

            $this->exporter->exportMetrics($stats);
            $this->exporter->output->writeln('');
        }
    }
}
