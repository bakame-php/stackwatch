<?php

declare(strict_types=1);

use Bakame\Stackwatch\AggregatorType;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Renderer;
use Bakame\Stackwatch\Report;

if (!function_exists('pf_dump')) {
    /**
     * Profile a callable and dump the stats to console.
     *
     * The function returns the Stat objec (Metrics or Report)
     * generated for further usage if needed.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function pf_dump(callable $callback, int $iterations = 3, int $warmup = 0, ?AggregatorType $type = null): Metrics|Report
    {
        return Profiler::dump($callback, $iterations, $warmup, $type);
    }
}

if (!function_exists('pf_dd')) {
    /**
     * Profile a callable and dump the stats to console, then exit.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function pf_dd(callable $callback, int $iterations = 3, int $warmup = 0, ?AggregatorType $type = null): never
    {
        Profiler::dd($callback, $iterations, $warmup, $type);
    }
}
