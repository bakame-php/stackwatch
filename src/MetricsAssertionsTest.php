<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\TestCase;

final class MetricsAssertionsTest extends TestCase
{
    use MetricsAssertions;

    public function testHeavyStuffMetrics(): void
    {
        $service = fn () => true;

        $this->assertMetrics(
            $service,
            iterations: 5,
            warmup: 2,
            type: AggregatorType::Median
        )
            ->executionTime()
            ->greaterThan(0)
            ->lessThanOrEqual(50, 'ms')
            ->cpuTime()
            ->lessThanOrEqual(50, 'ms')
            ->memoryUsage()->greaterThan(0)
            ->memoryUsageGrowth()->lessThan(2_000_000)
            ->peakMemoryUsage()->greaterThanOrEqualMetric('memoryUsage')
            ->realPeakMemoryUsage()->greaterThanOrEqualMetric('realMemoryUsage')
            ->allNonNegative()
            ->assertConsistency();
    }
}
