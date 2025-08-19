<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Exporter\LeaderPrinter;
use Bakame\Stackwatch\Version;
use Bakame\Stackwatch\Warning;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @phpstan-import-type EnvironmentHumanReadable from Environment
 */
final class ConsoleHandler implements Handler
{
    public function __construct(
        private readonly OutputInterface $stdout,
        private readonly LoggerInterface $logger,
        private readonly Environment $environment,
    ) {
    }

    public function handle(Input $input): void
    {
        $output = $this->stdout;

        if (null !== $input->output) {
            /** @var resource $handler */
            $handler = Warning::trap(fopen(...), $input->output, 'w');
            $output = new StreamOutput($handler);
        }

        $profiler = PathProfiler::forConsole($input, $output, $this->logger);
        $output->writeln(Version::toConsoleString());

        if ($input->infoSection->isVisible()) {
            $formatter = $profiler->formatter;
            if ($formatter instanceof ConsoleFormatter) {
                $leaderPrinter = new LeaderPrinter(filler: '.', padExtra: 1);
                /** @var EnvironmentHumanReadable $data */
                $data = $this->environment->forHuman();
                $formatter->exporter->output->writeln($leaderPrinter->render($data));
                $formatter->exporter->output->writeln('');
            }
        } else {
            $output->writeln('<fg=green>Runtime:</> PHP '.$this->environment->phpVersion.' <fg=green>OS:</> '.$this->environment->os.' <fg=green>Memory Limit:</> '.$this->environment->rawMemoryLimit);
        }

        $output->writeln('');
        $profiler->handle($input->path);
    }
}
