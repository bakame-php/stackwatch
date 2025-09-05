<?php

declare(strict_types=1);

use Bakame\Stackwatch\AggregatorType;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;

if (!function_exists('stack')) {
    /**
     * Profile a callable, returns a @see Result object.
     *
     * The function returns the Result object generated for further usage if needed.
     */
    function stack(callable $callback): Result
    {
        return Profiler::execute($callback);
    }
}

if (!function_exists('stack_watch')) {
    /**
     * Profile a callable, dump the generated Span and return the callback returned value.
     *
     * The function returns the callable expecged value
     */
    function stack_watch(callable $callback): mixed
    {
        $result = Profiler::execute($callback);
        $result->span->dump();

        return $result->returnValue;
    }
}

if (!function_exists('stack_report')) {
    /**
     * Profile a callable and return the generated @see Report.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function stack_report(callable $callback, int $iterations = 3, int $warmup = 0): Report
    {
        return Profiler::report($callback, $iterations, $warmup);
    }
}

if (!function_exists('stack_dump')) {
    /**
     * Profile a callable, dump and return the generated @see Report.
     *
     * The function returns the generated Report for further usage if needed.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function stack_dump(callable $callback, int $iterations = 3, int $warmup = 0): Report
    {
        /** @var Report $report */
        $report = Profiler::dump($callback, $iterations, $warmup);

        return $report;
    }
}

if (!function_exists('stack_dd')) {
    /**
     * Profile a callable, dump the generated @see Report and halt script execution.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function stack_dd(callable $callback, int $iterations = 3, int $warmup = 0): never
    {
        Profiler::dd($callback, $iterations, $warmup);
    }
}

if (!function_exists('stack_metrics')) {
    /**
     * Profile a callable and returns the generated Metrics object.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function stack_metrics(callable $callback, int $iterations = 3, int $warmup = 0, AggregatorType $type = AggregatorType::Average): Metrics
    {
        return Profiler::metrics($callback, $iterations, $warmup, $type);
    }
}

if (!function_exists('stack_mdump')) {
    /**
     * Profile a callable, dump and return the generated @see Metrics.
     *
     * The function returns the generated Metrics for further usage if needed.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function stack_mdump(callable $callback, int $iterations = 3, int $warmup = 0, AggregatorType $type = AggregatorType::Average): Metrics
    {
        /** @var Metrics $stats */
        $stats = Profiler::dump($callback, $iterations, $warmup, $type);

        return $stats;
    }
}

if (!function_exists('stack_mdd')) {
    /**
     * Profile a callable, dump the generated @see Metrics and halt script execution.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function stack_mdd(callable $callback, int $iterations = 3, int $warmup = 0, AggregatorType $type = AggregatorType::Average): never
    {
        Profiler::dd($callback, $iterations, $warmup, $type);
    }
}
