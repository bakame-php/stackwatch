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
 * @phpstan-import-type SpanMap from Span
 */
#[CoversClass(Span::class)]
#[CoversClass(Metrics::class)]
#[CoversClass(CallLocation::class)]
#[CoversClass(CallRange::class)]
final class SpanTest extends TestCase
{
    #[Test]
    public function it_has_a_specific_lifecycle(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();

        $span = new Span('test', $start, $end);

        self::assertSame('test', $span->label);
        self::assertGreaterThan(0, $span->metrics->executionTime);
        self::assertGreaterThanOrEqual(0, $span->metrics->cpuTime);
        self::assertGreaterThanOrEqual(0, $span->metrics->memoryUsage);
        self::assertGreaterThanOrEqual(0, $span->metrics->realMemoryUsage);
        self::assertGreaterThanOrEqual(0, $span->metrics->peakMemoryUsage);
        self::assertGreaterThanOrEqual(0, $span->metrics->realPeakMemoryUsage);
    }

    #[Test]
    public function it_will_returns_metrics_in_an_associative_array(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();
        $span = new Span('test', $start, $end);
        $stats = $span->toArray();

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
        $span = new Span('test', $start, Snapshot::now());

        self::assertEquals($span, Span::fromArray($span->toArray()));
    }

    #[Test]
    public function it_can_be_json_encoded(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();
        $span = new Span('test', $start, $end);

        /** @var non-empty-string $json */
        $json = json_encode($span);
        self::assertJson($json);

        /** @var SpanMap $decoded */
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

        new Span('_123invalid', $start, $end);
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

        $span = new Span('test_summary', $startSnapshot, $endSnapshot);

        // Check that start and end of the range match the snapshots
        self::assertSame($startSnapshot->originPath, $span->range->start->path);
        self::assertSame($startSnapshot->originLine, $span->range->start->line);
        self::assertSame($endSnapshot->originPath, $span->range->end->path);
        self::assertSame($endSnapshot->originLine, $span->range->end->line);

        // Check computed properties
        self::assertTrue($span->range->isSameFile());
        self::assertTrue($span->range->hasStart());
        self::assertTrue($span->range->hasEnd());
        self::assertTrue($span->range->isComplete());
        self::assertTrue($span->range->isForward());
        self::assertSame(11, $span->range->lineSpan());
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

        $span = new Span('cross-file-summary', $startSnapshot, $endSnapshot);

        self::assertFalse($span->range->isSameFile());
        self::assertNull($span->range->lineSpan());
    }
}
