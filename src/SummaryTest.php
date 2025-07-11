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
 * @phpstan-import-type SummaryStat from Summary
 */
#[CoversClass(Summary::class)]
#[CoversClass(Metrics::class)]
final class SummaryTest extends TestCase
{
    #[Test]
    public function it_has_a_specific_lifecycle(): void
    {
        $start = Snapshot::now('start');
        usleep(1000);
        $end = Snapshot::now('end');

        $summary = new Summary($start, $end, 'test');

        self::assertSame('test', $summary->label);
        self::assertGreaterThan(0, $summary->metrics->executionTime);
        self::assertGreaterThanOrEqual(0, $summary->metrics->cpuTime);
        self::assertGreaterThanOrEqual(0, $summary->metrics->memoryUsage);
        self::assertGreaterThanOrEqual(0, $summary->metrics->realMemoryUsage);
        self::assertGreaterThanOrEqual(0, $summary->metrics->peakMemoryUsage);
        self::assertGreaterThanOrEqual(0, $summary->metrics->realPeakMemoryUsage);
    }

    #[Test]
    public function it_will_returns_metrics_in_an_associative_array(): void
    {
        $start = Snapshot::now('start');
        usleep(1000);
        $end = Snapshot::now('end');
        $summary = new Summary($start, $end, 'test');
        $stats = $summary->toArray();

        self::assertArrayHasKey('label', $stats);
        self::assertArrayHasKey('snapshots', $stats);
        self::assertArrayHasKey('label', $stats['snapshots'][0]);
        self::assertArrayHasKey('label', $stats['snapshots'][1]);
        self::assertArrayHasKey('metrics', $stats);
        self::assertIsArray($stats['metrics']);
        self::assertArrayHasKey('cpu_time', $stats['metrics']);
        self::assertArrayHasKey('memory_usage', $stats['metrics']);
    }

    #[Test]
    public function it_can_be_json_encoded(): void
    {
        $start = Snapshot::now('start');
        usleep(1000);
        $end = Snapshot::now('end');
        $summary = new Summary($start, $end, 'test');

        /** @var non-empty-string $json */
        $json = json_encode($summary);
        self::assertJson($json);

        /** @var SummaryStat $decoded */
        $decoded = json_decode($json, true);
        self::assertArrayHasKey('label', $decoded);
    }

    #[Test]
    public function it_will_reject_invalid_label(): void
    {
        $this->expectException(InvalidArgument::class);

        $start = Snapshot::now('start');
        usleep(1000);
        $end = Snapshot::now('test');

        new Summary($start, $end, '_123invalid');
    }
}
