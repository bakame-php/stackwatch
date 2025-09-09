<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Exporter\CallbackDumper;
use Bakame\Stackwatch\Exporter\ViewExporter;
use Throwable;

use function gc_collect_cycles;

final class Stack
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
     * @throws InvalidArgument
     */
    private static function assertItCanBeRun(int $iterations, int $warmup): void
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');
        0 <= $warmup || throw new InvalidArgument('The warmup argument must be an integer greater than or equal to 0.');
    }

    /**
     * Returns the value and the profiling data of the callback execution.
     *
     * @param ?non-empty-string $label
     *
     * @throws InvalidArgument|Throwable
     */
    public static function call(callable $callback, ?string $label = null): Result
    {
        $start = Snapshot::now('start');
        $returnValue = ($callback)();
        $end = Snapshot::now('end');

        return new Result($returnValue, new Span($label ?? self::generateLabel(), $start, $end));
    }


    /**
     * Returns the value and the profiling data of the callback execution.
     * And dump the Span object.
     *
     * @param ?non-empty-string $label
     *
     * @throws InvalidArgument|Throwable
     */
    public static function dumpCall(callable $callback, ?string $label = null): Result
    {
        $result = self::call($callback, $label);
        $result->span->dump();

        return $result;
    }

    /**
     * Dump and Die the Span generated during the callbakc execution.
     *
     * @param ?non-empty-string $label
     *
     * @throws InvalidArgument|Throwable
     */
    public static function ddCall(callable $callback, ?string $label = null): never
    {
        self::call($callback, $label)->span->dd();
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
    public static function benchmark(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): AggregatedMetrics
    {
        return self::report($callback, $iterations, $warmup)->column($type);
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
        gc_collect_cycles();
        $metrics = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $metrics[] = self::call($callback)->span->metrics;
        }

        return Report::fromMetrics(...$metrics);
    }

    /**
     * Profile a callable and dump the stats to console.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    public static function dumpReport(callable $callback, int $iterations = 1, int $warmup = 0): Report
    {
        $stats = self::report($callback, $iterations, $warmup);

        (new ViewExporter())->exportStack(
            $stats,
            new Profile(null, $iterations, $warmup),
            CallLocation::fromLastInternalCall(__NAMESPACE__, ['*Test.php'])
        );

        return $stats;
    }

    /**
     * Profile a callable and dump the stats to console.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    public static function dumpBenchmark(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): AggregatedMetrics
    {
        $stats = self::benchmark($callback, $iterations, $warmup, $type);

        (new ViewExporter())->exportStack(
            $stats,
            new Profile($type, $iterations, $warmup),
            CallLocation::fromLastInternalCall(__NAMESPACE__, ['*Test.php'])
        );

        return $stats;
    }

    /**
     * Profile a callable, dump the stats to console and die.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     */
    public static function ddReport(callable $callback, int $iterations = 1, int $warmup = 0): never
    {
        CallbackDumper::dd(fn (): Report => self::dumpReport($callback, $iterations, $warmup));
    }

    /**
     * Profile a callable, dump the stats to console and die.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     */
    public static function ddBenchmark(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): never
    {
        CallbackDumper::dd(fn (): AggregatedMetrics => self::dumpBenchmark($callback, $iterations, $warmup, $type));
    }
}
