<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

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
}
