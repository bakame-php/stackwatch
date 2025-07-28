<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Throwable;

use function class_exists;
use function fopen;
use function fwrite;
use function set_error_handler;

use const JSON_BIGINT_AS_STRING;
use const JSON_PRETTY_PRINT;
use const PHP_EOL;
use const PHP_SAPI;
use const STDERR;
use const STDOUT;

final class Stackwatch
{
    public const SUCCESS = 0;
    public const ERROR = 1;

    public static function run(): never
    {
        if (!class_exists(StreamOutput::class)) {
            fwrite(STDERR, 'The symfony/console package is required to use the command line.'.PHP_EOL);

            exit(self::ERROR);
        }

        if ('cli' !== PHP_SAPI) {
            fwrite(STDERR, 'This script must be run from the command line.'.PHP_EOL);

            exit(self::ERROR);
        }

        (new self(new StreamOutput(STDOUT), new StreamOutput(STDERR)))->handle();
    }

    public function __construct(private readonly OutputInterface $stdout, private readonly OutputInterface $stderr)
    {
    }

    public function handle(): never
    {
        exit($this->execute(StackwatchInput::fromCli()));
    }

    public function execute(StackwatchInput $options): int
    {
        if ($options->showHelp) {
            $this->stdout->writeln($this->header());
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
            $environment = Environment::current();
            $logger = new ConsoleLogger($this->stderr);
            if (StackwatchInput::JSON_FORMAT === $options->format) {
                $output = $options->output ?? STDOUT;
                $jsonOptions = $options->pretty ? JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING : JSON_BIGINT_AS_STRING;
                $profiler = PathProfiler::forJson($output, $jsonOptions, $logger);
                if ($options->showInfo) {
                    $processor = $profiler->processor;
                    if ($processor instanceof JsonProcessor) {
                        $processor->exporter->writeln($environment);
                    }
                }
            } elseif (StackwatchInput::CLI_FORMAT === $options->format) {
                $output = $this->stdout;
                if (null !== $options->output) {
                    set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
                    $handler = fopen($options->output, 'w');
                    restore_error_handler();
                    if (false === $handler) {
                        throw new RuntimeException('Unable to open the file for storing the output.');
                    }
                    $output = new StreamOutput($handler);
                }

                $profiler = PathProfiler::forConsole($output, $logger);
                $output->writeln($this->header());
                if ($options->showInfo) {
                    $processor = $profiler->processor;
                    if ($processor instanceof ConsoleTableProcessor) {
                        $processor->exporter->exportEnvironment($environment);
                    }
                    $output->writeln('');
                } else {
                    $output->writeln('<fg=green>Runtime:</> PHP '.$environment->phpVersion.' <fg=green>OS:</> '.$environment->os.' <fg=green>Memory Limit:</> '.$environment->rawMemoryLimit);
                    $output->writeln('');
                }
            } else {
                throw new RuntimeException('Unknown output format: '.$options->format);
            }

            if (null !== $options->path) {
                $profiler->handle($options->path);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->stderr->writeln('<error> Execution Error: '.$e->getMessage().'</error>');

            return self::ERROR;
        }
    }

    private function header(): string
    {
        $version = Version::full();

        return <<<HELP
<fg=green>stackwatch $version</><fg=yellow> by Ignace Nyamagana Butera and contributors.</>

HELP;
    }

    private function helpText(): string
    {
        return <<<'HELP'
<fg=yellow>Description:</>
  Profiles functions and methods in a PHP codebase using #[Profile] attributes.

<fg=yellow>Usage:</>
   stackwatch --path=PATH [--output=OUTPUT] [--format=FORMAT] [--pretty] [--info] [--help]

<fg=yellow>Options:</>
<fg=green>  -p, --path=PATH</>       Path to scan for PHP files to profile (required)
<fg=green>  -o, --output=OUTPUT</>   Path to store the profiling output (optional)
<fg=green>  -f, --format=FORMAT</>   Output format: 'cli' or 'json' (default: 'cli')
<fg=green>  -P, --pretty</>          Pretty-print the JSON/NDJSON output (json only)
<fg=green>  -i, --info</>            Show additional system/environment information
<fg=green>  -h, --help</>            Display this help message
<fg=green>  -V, --version</>         Display the version and exit
HELP;
    }
}
