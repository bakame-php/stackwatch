<?php

declare(strict_types=1);

use Bakame\Stackwatch\AggregationType;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Stack;

if (!function_exists('stack_call')) {
    /**
     * Profile a callable, returns a @see Result object.
     *
     * The function returns the Result object generated for further usage if needed.
     *
     * @param ?non-empty-string $label
     */
    function stack_call(callable $callback, ?string $label = null): Result
    {
        return Stack::call($callback, $label);
    }
}

if (!function_exists('stack_cdump')) {
    /**
     * Profile a callable, dump the generated Span and return the callback returned value.
     *
     * The function returns the Result object generated for further usage if needed.
     *
     * @param ?non-empty-string $label
     */
    function stack_cdump(callable $callback, ?string $label = null): Result
    {
        return Stack::dumpCall($callback, $label);
    }
}

if (!function_exists('stack_cdd')) {
    /**
     * Profile a callable, dump the generated Span and return the callback returned value.
     *
     * The function returns the callable expecged value
     *
     * @param ?non-empty-string $label
     */
    function stack_cdd(callable $callback, ?string $label = null): never
    {
        Stack::ddCall($callback, $label);
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
     */
    function stack_rdd(callable $callback, int $iterations = 1, int $warmup = 0): never
    {
        Stack::ddReport($callback, $iterations, $warmup);
    }
}

if (!function_exists('stack_bench')) {
    /**
     * Profile a callable and returns the generated Metrics object.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function stack_bench(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): Metrics
    {
        return Stack::benchmark($callback, $iterations, $warmup, $type);
    }
}

if (!function_exists('stack_bdump')) {
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
    function stack_bdump(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): Metrics
    {
        return Stack::dumpBenchmark($callback, $iterations, $warmup, $type);
    }
}

if (!function_exists('stack_bdd')) {
    /**
     * Profile a callable, dump the generated @see Metrics and halt script execution.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     */
    function stack_bdd(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): never
    {
        Stack::ddBenchmark($callback, $iterations, $warmup, $type);
    }
}
