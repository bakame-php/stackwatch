<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Test;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetricsAssertionsTest extends TestCase
{
    use MetricsAssertions;

    #[Test]
    public function it_can_test_some_callback_performance(): void
    {
        $callback = fn () => true;

        $assertMetrics = $this
            ->iter(5)
            ->warmup(2)
            ->aggMedian()
            ->assertMetrics($callback);

        $assertMetrics->executionTime()->greaterThan(0)->lessThanOrEqual(50, 'ms');
        $assertMetrics->cpuTime()->lessThanOrEqual(50, 'ms');
        $assertMetrics->memoryUsage()->greaterThan(0);
        $assertMetrics->memoryUsageGrowth()->lessThan(2, 'mb');
        $assertMetrics->peakMemoryUsage()->greaterThanOrEqualMetric('memoryUsage');
        $assertMetrics->realPeakMemoryUsage()->greaterThanOrEqualMetric('realMemoryUsage');
        $assertMetrics->allNonNegative();
    }
}
