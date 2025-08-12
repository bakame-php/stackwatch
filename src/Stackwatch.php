<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Console\ConsoleHandler;
use Bakame\Stackwatch\Console\Input;
use Bakame\Stackwatch\Console\JsonHandler;
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
        try {
            $input = Input::fromCli();
        } catch (Throwable $exception) {
            $this->stderr->writeln('<error> Input argument error: '.$exception->getMessage().' </error>');
            $this->stdout->writeln('');
            $this->stdout->writeln($this->helpText());

            exit(self::ERROR);
        }

        exit($this->execute($input));
    }

    public function execute(Input $input): int
    {
        if ($input->showHelp) {
            $this->stdout->writeln(Version::toConsoleString());
            $this->stdout->writeln($this->helpText());

            return self::SUCCESS;
        }

        if ($input->showVersion) {
            $this->stdout->writeln(
                Input::JSON_FORMAT === $input->format
                    ? Version::toJson()
                    : '<info>'.Version::name().' '.Version::full().'</info>'
            );

            return self::SUCCESS;
        }

        if (null === $input->path && !$input->showInfo) {
            $this->stderr->writeln('<error> Please specify a valid path. </error>');
            $this->stdout->writeln($this->helpText());

            return self::ERROR;
        }

        try {
            (match ($input->format) {
                Input::TABLE_FORMAT => new ConsoleHandler($this->stdout, $this->logger, $this->environment),
                Input::JSON_FORMAT => new JsonHandler($this->logger, $this->environment),
                default => throw new RuntimeException('Unknown output format: '.$input->format),
            })->handle($input);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->stderr->writeln('<error> Execution Error: '.$exception->getMessage().'</error>');

            return self::ERROR;
        }
    }

    private function helpText(): string
    {
        $name = Version::name();
        $description = self::DESCRIPTION;
        $optionsUsage = Input::usage();
        $optionsDescription = Input::consoleDescription();

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
