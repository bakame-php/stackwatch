<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Version;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

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
            set_error_handler(fn () => true);
            $handler = @fopen($input->output, 'w');
            restore_error_handler();
            false !== $handler || throw new RuntimeException('Unable to open the file for storing the output.');
            $output = new StreamOutput($handler);
        }

        $profiler = PathProfiler::forConsole($output, $this->logger);
        $output->writeln(Version::toConsoleString());

        if ($input->showInfo) {
            $processor = $profiler->processor;
            if ($processor instanceof ConsoleTableProcessor) {
                $processor->exporter->exportEnvironment($this->environment);
            }
        } else {
            $output->writeln('<fg=green>Runtime:</> PHP '.$this->environment->phpVersion.' <fg=green>OS:</> '.$this->environment->os.' <fg=green>Memory Limit:</> '.$this->environment->rawMemoryLimit);
        }

        $output->writeln('');
        if (null !== $input->path) {
            $profiler->handle($input->path);
        }
    }
}
