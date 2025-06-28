<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function usleep;

/**
 * @phpstan-import-type ProfileMetrics from Profile
 */
#[CoversClass(Profile::class)]
#[CoversClass(Metrics::class)]
final class ProfileTest extends TestCase
{
    #[Test]
    public function it_has_a_specific_lifecycle(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();

        $profile = new Profile('test', $start, $end);

        self::assertSame('test', $profile->label);
        self::assertGreaterThan(0, $profile->metrics->executionTime);
        self::assertGreaterThanOrEqual(0, $profile->metrics->cpuTime);
        self::assertGreaterThanOrEqual(0, $profile->metrics->memoryUsage);
        self::assertGreaterThanOrEqual(0, $profile->metrics->realMemoryUsage);
        self::assertGreaterThanOrEqual(0, $profile->metrics->peakMemoryUsage);
        self::assertGreaterThanOrEqual(0, $profile->metrics->realPeakMemoryUsage);
    }

    #[Test]
    public function it_will_returns_metrics_in_an_associative_array(): void
    {
        $start = Snapshot::now();
        usleep(1000);
        $end = Snapshot::now();
        $profile = new Profile('test', $start, $end);

        $stats = $profile->stats();

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
        $profile = new Profile('test', $start, $end);

        /** @var non-empty-string $json */
        $json = json_encode($profile);
        self::assertJson($json);

        /** @var ProfileMetrics $decoded */
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
        $profile = new Profile('_123invalid', $start, $end);
    }
}
