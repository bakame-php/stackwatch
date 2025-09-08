<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Environment;
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

        $pathProfiler = PathProfiler::forConsole($input, $output, $this->logger);
        $output->writeln(Version::toConsoleString());
        if ($input->infoSection->isVisible()) {
            $formatter = $pathProfiler->formatter;
            if ($formatter instanceof ConsoleFormatter) {
                $formatter->exporter->exportEnvironment($this->environment);
                $formatter->exporter->output->writeln('');
            }
        } else {
            $output->writeln('<fg=green>Runtime:</> PHP '.$this->environment->phpVersion.' <fg=green>OS:</> '.$this->environment->os.' <fg=green>Memory Limit:</> '.$this->environment->rawMemoryLimit);
        }
        $output->writeln('');
        $pathProfiler->handle($input->path);
    }
}
