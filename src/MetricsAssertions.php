<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Throwable;

trait MetricsAssertions
{
    /**
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    protected function assertMetrics(
        callable $callback,
        int $iterations = 3,
        int $warmup = 0,
        AggregatorType $type = AggregatorType::Average
    ): MetricsAssert {
        return new MetricsAssert(Stack::metrics($callback, $iterations, $warmup, $type));
    }
}
