<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class Stackwatch
{
    public const SUCCESS = 0;
    public const ERROR = 1;
    private const DESCRIPTION = 'Profiles functions and methods in a PHP codebase using #[Profile] attributes.';

    public function __construct(
        private readonly OutputInterface $stdout,
        private readonly OutputInterface $stderr,
        private readonly LoggerInterface $logger,
        private readonly Environment $environment,
    ) {
    }

    public function handle(): never
    {
        exit($this->execute(StackwatchInput::fromCli()));
    }

    public function execute(StackwatchInput $options): int
    {
        if ($options->showHelp) {
            $this->stdout->writeln(Version::banner());
            $this->stdout->writeln($this->helpText());

            return self::SUCCESS;
        }

        if ($options->showVersion) {
            $this->stdout->writeln('<info>'.Version::full().'</info>');

            return self::SUCCESS;
        }

        if (null === $options->path && !$options->showInfo) {
            $this->stderr->writeln('<error> Please specify a valid path. </error>');
            $this->stdout->writeln($this->helpText());

            return self::ERROR;
        }

        try {
            $handler = match ($options->format) {
                StackwatchInput::CLI_FORMAT => new ConsoleHandler($this->stdout, $this->logger, $this->environment),
                StackwatchInput::JSON_FORMAT => new JsonHandler($this->logger, $this->environment),
                default => throw new RuntimeException('Unknown output format: '.$options->format),
            };

            $handler->handle($options);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->stderr->writeln('<error> Execution Error: '.$e->getMessage().'</error>');

            return self::ERROR;
        }
    }

    private function helpText(): string
    {
        $name = Version::name();
        $description = self::DESCRIPTION;
        $optionsUsage = StackwatchInput::usage();
        $optionsDescription = StackwatchInput::description();

        return <<<HELP
<fg=yellow>Description:</>
 $description

<fg=yellow>Usage:</>
 $name $optionsUsage

<fg=yellow>Options:</>
$optionsDescription
HELP;
    }
}
