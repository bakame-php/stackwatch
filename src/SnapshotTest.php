<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function hrtime;

#[CoversClass(Snapshot::class)]
final class SnapshotTest extends TestCase
{
    #[Test]
    public function it_can_create_a_valid_snapshot_with_the_now_named_constructor(): void
    {
        self::assertArrayHasKey('ru_utime.tv_sec', Snapshot::now()->cpu);
    }

    #[Test]
    public function it_can_generate_a_valid_json_representation(): void
    {
        $data = Snapshot::now()->jsonSerialize();

        self::assertArrayHasKey('label', $data);
        self::assertArrayHasKey('timestamp', $data);
        self::assertArrayHasKey('hrtime', $data);
        self::assertArrayHasKey('cpu', $data);
        self::assertArrayHasKey('memory_usage', $data);
    }

    #[Test]
    public function it_can_evaluate_if_two_instance_are_equals(): void
    {
        $snapshot1 = Snapshot::now();

        $snapshot2 = new Snapshot(
            $snapshot1->label,
            $snapshot1->timestamp,
            $snapshot1->hrtime,
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
        self::assertFalse(Snapshot::now('test')->equals(Snapshot::now('test')));
    }

    #[Test]
    public function it_will_return_false_on_other_type(): void
    {
        self::assertFalse(Snapshot::now('test')->equals(new stdClass()));
    }

    #[Test]
    public function it_will_return_a_human_readable_value(): void
    {
        /** @var string $cpuForHuman */
        $cpuForHuman = Snapshot::now('test')->forHuman('cpu');

        self::assertStringContainsString('ru_utime.tv_sec', $cpuForHuman);
    }

    #[Test]
    public function it_will_throw_if_the_property_does_not_exits(): void
    {
        $this->expectException(InvalidArgument::class);

        Snapshot::now()->forHuman('foobar');
    }

    #[Test]
    public function it_is_forgiving_when_using_for_human(): void
    {
        $snapshot = Snapshot::now();

        self::assertSame(
            $snapshot->forHuman('MeMorY UsaGE'),
            $snapshot->forHuman('memory_usage')
        );
    }

    #[Test]
    public function it_will_throw_if_the_cpu_property_is_missing_keys(): void
    {
        $this->expectException(InvalidArgument::class);

        new Snapshot(
            label: 'test',
            timestamp: new DateTimeImmutable(),
            hrtime: hrtime(true),
            cpu: [],  /* @phpstan-ignore-line */
            memoryUsage: 1,
            realMemoryUsage: 1,
            peakMemoryUsage: 1,
            realPeakMemoryUsage: 1
        );
    }

    #[Test]
    public function it_can_be_recreated_from_an_array(): void
    {
        $snapshot = Snapshot::now();

        self::assertEquals($snapshot, Snapshot::fromArray($snapshot->toArray()));
    }

    #[Test]
    public function it_fails_to_create_a_new_instance_from_an_invalid_array(): void
    {
        $this->expectException(InvalidArgument::class);

        Snapshot::fromArray([]); /* @phpstan-ignore-line */
    }
}
