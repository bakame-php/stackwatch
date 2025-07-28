<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$iterations = 100;
$warmup = 10;
$callback = function (): int {
    usleep(500);

    return strlen(str_repeat('abc', 1_000));
};

Benchmark::toConsole(Benchmark::run(callback: $callback, iterations: $iterations, warmup: $warmup));
