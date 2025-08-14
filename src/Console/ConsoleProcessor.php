<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Report;
use Throwable;

use function exec;
use function implode;
use function is_numeric;
use function max;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_repeat;
use function trim;

/**
 * @phpstan-import-type MetricsHumanReadable from Metrics
 */
final class ConsoleProcessor implements Processor
{
    public function __construct(public readonly ConsoleExporter $exporter)
    {
    }

    /**
     * @param iterable<UnitOfWork> $unitOfWorks
     *
     * @throws Throwable
     */
    public function process(iterable $unitOfWorks): void
    {
        foreach ($unitOfWorks as $unitOfWork) {
            $unitOfWork->run();

            $stats = $unitOfWork->result();
            $this->exporter->output->writeln($unitOfWork->toConsoleString());
            $this->exporter->output->writeln('');
            if ($stats instanceof Report) {
                $this->exporter->exportReport($stats);
                $this->exporter->output->writeln('');
                continue;
            }

            /** @var MetricsHumanReadable $data */
            $data = $stats->forHuman();
            $this->exporter->output->writeln(implode(
                "\n",
                self::leaderList(
                    pairs: self::exchangeKeys($data),
                    terminalWidth: self::detectTerminalWidth()
                )
            ));
            $this->exporter->output->writeln('');
        }
    }

    /**
     * @param MetricsHumanReadable $metrics
     *
     * @return array<string, string>
     */
    private static function exchangeKeys(array $metrics): array
    {
        static $humanKeys = [
            'cpu_time' => 'CPU Time',
            'memory_usage' => 'Memory Usage',
            'real_memory_usage' => 'Real Memory Usage',
            'peak_memory_usage' => 'Peak Memory Usage',
            'real_peak_memory_usage' => 'Real Peak Memory Usage',
            'execution_time' => 'Execution Time',
        ];

        $humans = [];
        foreach ($metrics as $key => $metric) {
            /** @var string $humanKey */
            $humanKey = $humanKeys[$key] ?? $key;
            $humans[ $humanKey] = $metric;
        }

        return $humans;
    }

    /**
     * Print a leader list with left-aligned keys, right-aligned values,
     * and dots filling the space in between.
     *
     * @param array<string,string> $pairs Key => Value
     * @param string $filler Filler character (default: '.')
     * @param int $padExtra Extra space after the key before dots (default: 1)
     * @return string[] Formatted lines
     */
    private static function leaderList(array $pairs, string $filler = '.', int $padExtra = 1, int $terminalWidth = 80): array
    {
        $lines = [];
        foreach ($pairs as $key => $value) {
            $dotsCount = $terminalWidth - mb_strlen($value) - mb_strlen($key)  - (2 * $padExtra);
            $lines[] = sprintf(
                '%s %s %s',
                $key,
                str_repeat($filler, (int) max(1, $dotsCount)),
                $value
            );
        }

        return $lines;
    }

    /**
     * Detects terminal width across platforms.
     */
    private static function detectTerminalWidth(int $fallback = 80): int
    {
        $exec = function (string $command): string|false {
            set_error_handler(fn () => true);
            $result = exec($command);
            restore_error_handler();

            return $result;
        };

        // Try tput
        $cols = $exec('tput cols 2>/dev/null');
        if (is_numeric($cols) && $cols > 0) {
            return (int) $cols;
        }

        // Try stty size
        $stty = $exec('stty size 2>/dev/null');
        if (false !== $stty && 1 === preg_match('/^\d+\s+(?<size>\d+)$/', trim($stty), $match)) {
            return (int) $match['size'];
        }

        if (!Environment::current()->isWindows()) {
            return $fallback;
        }

        $mode = $exec('mode con');
        if (false !== $mode && 1 === preg_match('/Columns:\s+(?<size>\d+)/i', $mode, $match)) {
            return (int) $match['size'];
        }

        return $fallback;
    }
}
