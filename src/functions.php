<?php

declare(strict_types=1);

use Bakame\Stackwatch\AggregationType;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Stack;

if (!function_exists('stack')) {
    /**
     * Profile a callable, returns a @see Result object.
     *
     * The function returns the Result object generated for further usage if needed.
     *
     * @param ?non-empty-string $label
     */
    function stack(callable $callback, ?string $label = null): Result
    {
        return Stack::execute($callback, $label);
    }
}

if (!function_exists('stack_dump')) {
    /**
     * Profile a callable, dump the generated Span and return the callback returned value.
     *
     * The function returns the Result object generated for further usage if needed.
     *
     * @param ?non-empty-string $label
     */
    function stack_dump(callable $callback, ?string $label = null): Result
    {
        $result = Stack::execute($callback, $label);
        $result->span->dump();

        return $result;
    }
}

if (!function_exists('stack_dd')) {
    /**
     * Profile a callable, dump the generated Span and return the callback returned value.
     *
     * The function returns the callable expecged value
     *
     * @param ?non-empty-string $label
     */
    function stack_dd(callable $callback, ?string $label = null): never
    {
        Stack::execute($callback, $label)->span->dd();
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
    function stack_report(callable $callback, int $iterations = 1, int $warmup = 0): Report
    {
        return Stack::report($callback, $iterations, $warmup);
    }
}

if (!function_exists('stack_rdump')) {
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
    function stack_rdump(callable $callback, int $iterations = 1, int $warmup = 0): Report
    {
        return Stack::dumpReport($callback, $iterations, $warmup);
    }
}

if (!function_exists('stack_rdd')) {
    /**
     * Profile a callable, dump the generated @see Report and halt script execution.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function stack_rdd(callable $callback, int $iterations = 1, int $warmup = 0): never
    {
        Stack::ddReport($callback, $iterations, $warmup);
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
    function stack_metrics(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): Metrics
    {
        return Stack::metrics($callback, $iterations, $warmup, $type);
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
    function stack_mdump(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): Metrics
    {
        return Stack::dumpMetrics($callback, $iterations, $warmup, $type);
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
    function stack_mdd(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): never
    {
        Stack::ddMetrics($callback, $iterations, $warmup, $type);
    }
}
