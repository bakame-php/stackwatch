<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Throwable;

/**
 * Profile a callable, returns a @see Result object.
 *
 * The function returns the Result object generated for further usage if needed.
 *
 * @param ?non-empty-string $label
 *
 * @throws Throwable
 */
function stack_call(callable $callback, ?string $label = null): Result
{
    return Stack::call($callback, $label);
}

/**
 * Profile a callable, dump the generated Span and return the callback returned value.
 *
 * The function returns the Result object generated for further usage if needed.
 *
 * @param ?non-empty-string $label
 *
 * @throws Throwable
 */
function stack_cdump(callable $callback, ?string $label = null): Result
{
    return Stack::dumpCall($callback, $label);
}

/**
 * Profile a callable, dump the generated Span and return the callback returned value.
 *
 * The function returns the callable expected value
 *
 * @param ?non-empty-string $label
 *
 * @throws Throwable
 */
function stack_cdd(callable $callback, ?string $label = null): never
{
    Stack::ddCall($callback, $label);
}

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

/**
 * Profile a callable and returns the generated Metrics object.
 *
 * @param int<1, max> $iterations
 * @param int<0, max> $warmup
 *
 * @throws Throwable
 */
function stack_bench(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): AggregatedMetrics
{
    return Stack::benchmark($callback, $iterations, $warmup, $type);
}

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
function stack_bdump(callable $callback, int $iterations = 1, int $warmup = 0, AggregationType $type = AggregationType::Average): AggregatedMetrics
{
    return Stack::dumpBenchmark($callback, $iterations, $warmup, $type);
}

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
