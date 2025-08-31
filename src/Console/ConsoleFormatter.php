<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\LeaderPrinter;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Translator;
use Throwable;

final class ConsoleFormatter implements Formatter
{
    public function __construct(
        public readonly ConsoleExporter $exporter,
        public readonly LeaderPrinter $leaderPrinter = new LeaderPrinter(),
        public readonly Feature $dryRun = Feature::Disabled,
        public readonly Translator $translator = new Translator(),
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

            $data = $this->leaderPrinter->setPairs($this->translator->translateArrayKeys($stats->toHuman()))->render();
            $this->exporter->output->writeln($data);
            $this->exporter->output->writeln('');
        }
    }
}
