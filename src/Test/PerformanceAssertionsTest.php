<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetricsAssert::class)]
#[CoversClass(PerformanceResult::class)]
final class PerformanceAssertionsTest extends TestCase
{
    use PerformanceAssertions;

    /**
     * @return array{iterations: int<1, max>}
     */
    protected function defaultMetricsConfig(): array
    {
        return ['iterations' => 10];
    }

    #[Test]
    public function it_can_test_some_callback_performance(): void
    {
        $this
            ->iterations(5)
            ->warmup(2);

        self::assertSame(5, $this->performanceIterations);
        self::assertSame(2, $this->performanceWarmup);

        $assertMedianMetrics = $this->assertMedian(fn () => true);

        self::assertSame(10, $this->performanceIterations);
        self::assertSame(0, $this->performanceWarmup);

        $assertMedianMetrics->executionTime()->greaterThan(0)->lessThanOrEqual(50, 'ms');
        $assertMedianMetrics->cpuTime()->lessThanOrEqual(50, 'ms');
        $assertMedianMetrics->memoryUsage()->greaterThan(0);
        $assertMedianMetrics->memoryUsageGrowth()->lessThan(2, 'mb');
        $assertMedianMetrics->peakMemoryUsage()->greaterThanOrEqualMetric('memoryUsage');
        $assertMedianMetrics->realPeakMemoryUsage()->greaterThanOrEqualMetric('realMemoryUsage');
        $assertMedianMetrics->allNonNegative();
    }
}
