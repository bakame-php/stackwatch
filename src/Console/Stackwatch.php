<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Exporter\LeaderPrinter;
use Bakame\Stackwatch\Translator;
use Bakame\Stackwatch\Version;
use Bakame\Stackwatch\Warning;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Throwable;

use function ini_set;
use function preg_match;

use const STDERR;

final class Stackwatch
{
    public const SUCCESS = 0;
    public const ERROR = 1;
    private const DESCRIPTION = 'Profiles functions and methods in a PHP codebase using #[Profile] attributes.';

    /**
     * @param array<string> $argv
     */
    public static function setMemoryLimit(array $argv = []): void
    {
        foreach ($argv as $arg) {
            if (1 === preg_match('/^--memory-limit=(?<memory>.+)$/', $arg, $m)) {
                ini_set('memory_limit', $m['memory']);
                break;
            }
        }
    }

    /**
     * @param array<string> $argv
     */
    public static function resolveLogger(array $argv = [], ?StreamOutput $default = null): LoggerInterface
    {
        $logFile = null;
        foreach ($argv as $i => $arg) {
            if (str_starts_with($arg, '--log=')) {
                $logFile = substr($arg, strlen('--log='));
                break;
            }

            if ('--log' === $arg && isset($argv[$i + 1])) {
                $logFile = $argv[$i + 1];
                break;
            }
        }

        if (null === $logFile) {
            return new Logger($default ?? new StreamOutput(STDERR));
        }

        /** @var resource $handler */
        $handler = Warning::trap(fopen(...), $logFile, 'a');

        return new Logger(new StreamOutput($handler));
    }

    public function __construct(
        private readonly OutputInterface $stdout,
        private readonly OutputInterface $stderr,
        private readonly LoggerInterface $logger,
        private readonly Environment $environment,
        private readonly LeaderPrinter $leaderPrinter = new LeaderPrinter(),
        private readonly Translator $translator = new Translator(),
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
        if ($input->helpSection->isVisible()) {
            $this->stdout->writeln(Version::toConsoleString());
            $this->stdout->writeln($this->helpText());

            return self::SUCCESS;
        }

        if ($input->versionSection->isVisible()) {
            $this->stdout->writeln(
                Input::JSON_FORMAT === $input->format
                    ? Version::toJson()
                    : '<info>'.Version::name().' '.Version::full().'</info>'
            );

            return self::SUCCESS;
        }

        if (null === $input->path && $input->infoSection->isHidden()) {
            $this->stderr->writeln('<error> Please specify a valid path. </error>');
            $this->stdout->writeln($this->helpText());

            return self::ERROR;
        }

        try {
            (match ($input->format) {
                Input::TEXT_FORMAT => new ConsoleHandler($this->stdout, $this->logger, $this->environment, $this->leaderPrinter, $this->translator),
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
