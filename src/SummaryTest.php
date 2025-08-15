<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function usleep;

/**
 * @phpstan-import-type SnapshotMap from Snapshot
 * @phpstan-import-type MetricsMap from Metrics
 * @phpstan-import-type SummaryMap from Summary
 */
#[CoversClass(Summary::class)]
#[CoversClass(Metrics::class)]
#[CoversClass(CallLocation::class)]
#[CoversClass(CallRange::class)]
final class SummaryTest extends TestCase
{
    #[Test]
    public function it_has_a_specific_lifecycle(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();

        $summary = new Summary('test', $start, $end);

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
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();
        $summary = new Summary('test', $start, $end);
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
    public function it_can_do_a_roundtrup_with_from_and_to_array_methods(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $summary = new Summary('test', $start, Snapshot::now());

        self::assertEquals($summary, Summary::fromArray($summary->toArray()));
    }

    #[Test]
    public function it_can_be_json_encoded(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();
        $summary = new Summary('test', $start, $end);

        /** @var non-empty-string $json */
        $json = json_encode($summary);
        self::assertJson($json);

        /** @var SummaryMap $decoded */
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

        new Summary('_123invalid', $start, $end);
    }

    public function testRangeIsDerivedFromSnapshots(): void
    {
        $startSnapshot = new Snapshot(
            label: 'start',
            timestamp: new DateTimeImmutable(),
            hrtime: 1000.0,
            cpuUserTime: 1.0,
            cpuSystemTime: 0.5,
            memoryUsage: 100_000,
            realMemoryUsage: 120_000,
            peakMemoryUsage: 150_000,
            realPeakMemoryUsage: 170_000,
            originPath: '/path/to/file.php',
            originLine: 10
        );

        $endSnapshot = new Snapshot(
            label: 'end',
            timestamp: new DateTimeImmutable(),
            hrtime: 2000.0,
            cpuUserTime: 2.0,
            cpuSystemTime: 1.0,
            memoryUsage: 110_000,
            realMemoryUsage: 130_000,
            peakMemoryUsage: 160_000,
            realPeakMemoryUsage: 180_000,
            originPath: '/path/to/file.php',
            originLine: 20
        );

        $summary = new Summary('test_summary', $startSnapshot, $endSnapshot);

        // Check that start and end of the range match the snapshots
        self::assertSame($startSnapshot->originPath, $summary->range->start->path);
        self::assertSame($startSnapshot->originLine, $summary->range->start->line);
        self::assertSame($endSnapshot->originPath, $summary->range->end->path);
        self::assertSame($endSnapshot->originLine, $summary->range->end->line);

        // Check computed properties
        self::assertTrue($summary->range->isSameFile());
        self::assertTrue($summary->range->hasStart());
        self::assertTrue($summary->range->hasEnd());
        self::assertTrue($summary->range->isComplete());
        self::assertTrue($summary->range->isForward());
        self::assertSame(11, $summary->range->lineSpan());
    }

    public function testRangeIsNullWhenSnapshotsFromDifferentFiles(): void
    {
        $startSnapshot = new Snapshot(
            label: 'start',
            timestamp: new DateTimeImmutable(),
            hrtime: 1000.0,
            cpuUserTime: 1.0,
            cpuSystemTime: 0.5,
            memoryUsage: 100_000,
            realMemoryUsage: 120_000,
            peakMemoryUsage: 150_000,
            realPeakMemoryUsage: 170_000,
            originPath: '/file1.php',
            originLine: 5
        );

        $endSnapshot = new Snapshot(
            label: 'end',
            timestamp: new DateTimeImmutable(),
            hrtime: 2000.0,
            cpuUserTime: 2.0,
            cpuSystemTime: 1.0,
            memoryUsage: 110_000,
            realMemoryUsage: 130_000,
            peakMemoryUsage: 160_000,
            realPeakMemoryUsage: 180_000,
            originPath: '/file2.php',
            originLine: 15
        );

        $summary = new Summary('cross-file-summary', $startSnapshot, $endSnapshot);

        self::assertFalse($summary->range->isSameFile());
        self::assertNull($summary->range->lineSpan());
    }
}
