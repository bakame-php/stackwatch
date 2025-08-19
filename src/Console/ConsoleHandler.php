<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Cloak;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Exporter\LeaderPrinter;
use Bakame\Stackwatch\Version;
use Psr\Log\LoggerInterface;
use RuntimeException;
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
            /** @var resource|false $handler */
            $handler = Cloak::warning(fopen(...), $input->output, 'w');
            false !== $handler || throw new RuntimeException('Unable to open the file for storing the output.');
            $output = new StreamOutput($handler);
        }

        $profiler = PathProfiler::forConsole($input, $output, $this->logger);
        $output->writeln(Version::toConsoleString());

        if ($input->infoSection->isVisible()) {
            $processor = $profiler->processor;
            if ($processor instanceof ConsoleProcessor) {
                $leaderPrinter = new LeaderPrinter(filler: '.', padExtra: 1);
                /** @var EnvironmentHumanReadable $data */
                $data = $this->environment->forHuman();
                $processor->exporter->output->writeln($leaderPrinter->render($data));
                $processor->exporter->output->writeln('');
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
