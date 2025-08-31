<?php

declare(strict_types=1);

use Bakame\Stackwatch\Profile;
use Bakame\Stackwatch\Profiler;

if (!function_exists('pf')) {
    /**
     * Profile a callable and dump the stats to console.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function pf(callable $callback, int $iterations = 3, int $warmup = 0, string $type = Profile::SUMMARY): void
    {
        Profiler::dump($callback, $iterations, $warmup, $type);
    }
}

if (!function_exists('pfd')) {
    /**
     * Profile a callable and dump the stats to console, then exit.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    function pfd(callable $callback, int $iterations = 3, int $warmup = 0, string $type = Profile::SUMMARY): never
    {
        Profiler::dd($callback, $iterations, $warmup, $type);
    }
}
