<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\AnsiStyle;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\LeaderPrinter;
use Bakame\Stackwatch\Translator;
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
        private readonly LeaderPrinter $leaderPrinter = new LeaderPrinter(),
        private readonly Translator $translator = new Translator(),
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

        $profiler = PathProfiler::forConsole($input, $output, $this->logger, $this->translator);
        $output->writeln(Version::toConsoleString());

        if ($input->infoSection->isVisible()) {
            $formatter = $profiler->formatter;
            if ($formatter instanceof ConsoleFormatter) {
                $data = $this->translator->translateArrayKeys($this->environment->toHuman());
                $formatter->exporter->output->writeln(
                    $this
                    ->leaderPrinter
                    ->setPairs($data)
                    ->setStylesKey(AnsiStyle::BrightGreen)
                    ->setStylesValue(AnsiStyle::BrightCyan)
                    ->renderCli()
                );
                $formatter->exporter->output->writeln('');
            }
        } else {
            $output->writeln('<fg=green>Runtime:</> PHP '.$this->environment->phpVersion.' <fg=green>OS:</> '.$this->environment->os.' <fg=green>Memory Limit:</> '.$this->environment->rawMemoryLimit);
        }

        $output->writeln('');
        $profiler->handle($input->path);
    }
}
