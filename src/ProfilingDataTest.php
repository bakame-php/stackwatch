<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function usleep;

/**
 * @phpstan-import-type SnapshotStat from Snapshot
 * @phpstan-import-type MetricsStat from Metrics
 * @phpstan-import-type ProfilingDataStat from ProfilingData
 */
#[CoversClass(ProfilingData::class)]
#[CoversClass(Metrics::class)]
final class ProfilingDataTest extends TestCase
{
    #[Test]
    public function it_has_a_specific_lifecycle(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();

        $profilingData = new ProfilingData($start, $end, 'test');

        self::assertSame('test', $profilingData->label);
        self::assertGreaterThan(0, $profilingData->metrics->executionTime);
        self::assertGreaterThanOrEqual(0, $profilingData->metrics->cpuTime);
        self::assertGreaterThanOrEqual(0, $profilingData->metrics->memoryUsage);
        self::assertGreaterThanOrEqual(0, $profilingData->metrics->realMemoryUsage);
        self::assertGreaterThanOrEqual(0, $profilingData->metrics->peakMemoryUsage);
        self::assertGreaterThanOrEqual(0, $profilingData->metrics->realPeakMemoryUsage);
    }

    #[Test]
    public function it_will_returns_metrics_in_an_associative_array(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();
        $profilingData = new ProfilingData($start, $end, 'test');

        $stats = $profilingData->stats();

        self::assertArrayHasKey('label', $stats);
        self::assertArrayHasKey('start', $stats);
        self::assertArrayHasKey('end', $stats);
        self::assertArrayHasKey('metrics', $stats);

        self::assertIsArray($stats['metrics']);
        self::assertArrayHasKey('cpu_time', $stats['metrics']);
        self::assertArrayHasKey('memory_usage', $stats['metrics']);
    }

    #[Test]
    public function it_can_be_json_encoded(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();
        $profilingData = new ProfilingData($start, $end, 'test');

        /** @var non-empty-string $json */
        $json = json_encode($profilingData);
        self::assertJson($json);

        /** @var ProfilingDataStat $decoded */
        $decoded = json_decode($json, true);
        self::assertArrayHasKey('label', $decoded);
    }

    #[Test]
    public function it_will_reject_invalid_label(): void
    {
        $this->expectException(InvalidArgument::class);

        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();

        new ProfilingData($start, $end, '_123invalid');
    }
}
