<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function usleep;

#[CoversClass(Metrics::class)]
#[CoversClass(Profiler::class)]
#[CoversClass(Result::class)]
#[CoversClass(LabelGenerator::class)]
/**
 * @phpstan-import-type SpanMap from Span
 */
final class ProfilerTest extends TestCase
{
    #[Test]
    public function it_can_return_each_metrics_separately(): void
    {
        $callback = function (): string {
            usleep(1000);

            return 'end';
        };

        $metrics = Profiler::metrics($callback, iterations: 2, warmup: 3);

        self::assertGreaterThanOrEqual(0, $metrics->executionTime);
        self::assertGreaterThanOrEqual(0, $metrics->cpuTime);
        self::assertGreaterThanOrEqual(0, $metrics->memoryUsage);
        self::assertGreaterThanOrEqual(0, $metrics->realMemoryUsage);
        self::assertGreaterThanOrEqual(0, $metrics->peakMemoryUsage);
        self::assertGreaterThanOrEqual(0, $metrics->realPeakMemoryUsage);
    }

    #[Test]
    public function it_fails_to_return_the_metrics_on_invalid_iteration_argument(): void
    {
        $this->expectException(InvalidArgument::class);

        Profiler::metrics(fn () => null, 0); /* @phpstan-ignore-line  */
    }
}
