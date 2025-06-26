<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Snapshot::class)]
final class SnapshotTest extends TestCase
{
    #[Test]
    public function it_can_create_a_valid_snapshot_with_the_now_named_constructor(): void
    {
        $snapshot = Snapshot::now();

        self::assertArrayHasKey('ru_utime.tv_sec', $snapshot->cpu);
    }

    #[Test]
    public function it_can_generate_a_valid_json_representation(): void
    {
        $snapshot = Snapshot::now();
        $data = $snapshot->jsonSerialize();

        self::assertArrayHasKey('timestamp', $data);
        self::assertArrayHasKey('metrics', $data);
        self::assertArrayHasKey('execution_time', $data['metrics']);
        self::assertArrayHasKey('cpu', $data['metrics']);
        self::assertArrayHasKey('memory_usage', $data['metrics']);
    }

    #[Test]
    public function it_can_evaluate_if_two_instance_are_equals(): void
    {
        $snapshot1 = Snapshot::now();

        $snapshot2 = new Snapshot(
            $snapshot1->timestamp,
            $snapshot1->executionTime,
            $snapshot1->cpu,
            $snapshot1->memoryUsage,
            $snapshot1->realMemoryUsage,
            $snapshot1->peakMemoryUsage,
            $snapshot1->realPeakMemoryUsage
        );

        self::assertTrue($snapshot1->equals($snapshot2));
    }

    #[Test]
    public function it_will_detect_two_distinct_snapshots(): void
    {
        $snapshot1 = Snapshot::now();
        usleep(100);

        self::assertFalse($snapshot1->equals(Snapshot::now()));
    }

    #[Test]
    public function it_will_return_false_on_other_type(): void
    {
        self::assertFalse(Snapshot::now()->equals(new stdClass()));
    }
}
