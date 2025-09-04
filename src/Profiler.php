<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Throwable;

use function gc_collect_cycles;
use function header;
use function headers_sent;
use function ob_get_clean;
use function ob_start;

final class Profiler
{
    /**
     * @return non-empty-string
     */
    private static function generateLabel(): string
    {
        /** @var LabelGenerator $labelGenerator */
        static $labelGenerator;
        if (null === $labelGenerator) {
            $labelGenerator = new LabelGenerator();
        }

        return $labelGenerator->generate();
    }

    private static function warmup(int $warmup, callable $callback): void
    {
        if (0 < $warmup) {
            for ($i = 0; $i < $warmup; ++$i) {
                $callback();
            }
        }
    }

    /**
     * Returns the value and the profiling data of the callback execution.
     *
     * @throws InvalidArgument|Throwable
     */
    public static function execute(callable $callback): Result
    {
        gc_collect_cycles();
        $start = Snapshot::now('start');
        $returnValue = ($callback)();
        $end = Snapshot::now('end');

        return new Result($returnValue, new Span(self::generateLabel(), $start, $end));
    }

    /**
     * Returns aggregated metrics associated with the callback.
     *
     * The aggregation mode (average, median, …) is controlled by $aggregatorMode.
     *
     * @param int<0, max> $warmup
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function metrics(callable $callback, int $iterations = 1, int $warmup = 0, AggregatorType $type = AggregatorType::Average): Metrics
    {
        self::assertItCanBeRun($iterations, $warmup);
        self::warmup($warmup, $callback);
        $metrics = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $metrics[] = self::execute($callback)->span->metrics;
        }

        return Metrics::aggregate($type, ...$metrics);
    }

    /**
     * Returns the metrics associated with the callback.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws InvalidArgument|Throwable
     */
    public static function report(callable $callback, int $iterations = 1, int $warmup = 0): Report
    {
        self::assertItCanBeRun($iterations, $warmup);
        self::warmup($warmup, $callback);
        $metrics = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $metrics[] = self::execute($callback)->span->metrics;
        }

        return Report::fromMetrics(...$metrics);
    }

    /**
     * @throws InvalidArgument
     */
    private static function assertItCanBeRun(int $iterations, int $warmup): void
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');
        0 <= $warmup || throw new InvalidArgument('The warmup argument must be an integer greater than or equal to 0.');
    }

    /**
     * Profile a callable and dump the stats to console.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    public static function dump(callable $callback, int $iterations = 3, int $warmup = 0, ?AggregatorType $type = null): Metrics|Report
    {
        $stats = match ($type) {
            null => self::report($callback, $iterations, $warmup),
            default => self::metrics($callback, $iterations, $warmup, $type),
        };

        $renderer = new Renderer();
        $renderer->render($stats, new Profile($type, $iterations, $warmup), CallLocation::fromLastInternalCall(__NAMESPACE__, ['*Test.php']));

        return $stats;
    }

    /**
     * Profile a callable, dump the stats to console and die.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    public static function dd(callable $callback, int $iterations = 3, int $warmup = 0, ?AggregatorType $type = null): never
    {
        ob_start();
        self::dump($callback, $iterations, $warmup, $type);
        $dumpOutput = ob_get_clean();

        if (Environment::current()->isCli()) {
            echo $dumpOutput;
            exit(1);
        }

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $dumpOutput;
        exit(1);
    }
}
