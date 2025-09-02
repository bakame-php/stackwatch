<?php

declare(strict_types=1);

use Bakame\Stackwatch\AggregatorMode;
use Bakame\Stackwatch\Profiler;

if (!function_exists('pf_dump')) {
    /**
     * Profile a callable and dump the stats to console.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function pf_dump(callable $callback, int $iterations = 3, int $warmup = 0, ?AggregatorMode $type = null): void
    {
        Profiler::dump($callback, $iterations, $warmup, $type);
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
    function pf_dd(callable $callback, int $iterations = 3, int $warmup = 0, ?AggregatorMode $type = null): never
    {
        Profiler::dd($callback, $iterations, $warmup, $type);
    }
}
