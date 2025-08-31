<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Snapshot::class)]
#[CoversClass(CallLocation::class)]
final class SnapshotTest extends TestCase
{
    #[Test]
    public function it_can_generate_a_valid_json_representation(): void
    {
        $data = Snapshot::now()->jsonSerialize();

        self::assertArrayHasKey('label', $data);
        self::assertArrayHasKey('timestamp', $data);
        self::assertArrayHasKey('hrtime', $data);
        self::assertArrayHasKey('cpu_system_time', $data);
        self::assertArrayHasKey('cpu_user_time', $data);
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
            $snapshot1->cpuUserTime,
            $snapshot1->cpuSystemTime,
            $snapshot1->memoryUsage,
            $snapshot1->realMemoryUsage,
            $snapshot1->peakMemoryUsage,
            $snapshot1->realPeakMemoryUsage,
            $snapshot1->originPath,
            $snapshot1->originLine,
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
        $humans = Snapshot::now('test')->toHuman();

        self::assertIsArray($humans);
        self::assertArrayHasKey('cpu_user_time', $humans);
    }

    #[Test]
    public function it_will_throw_if_the_property_does_not_exits(): void
    {
        $this->expectException(InvalidArgument::class);

        Snapshot::now()->human('foobar');
    }

    #[Test]
    public function it_is_forgiving_when_using_for_human(): void
    {
        $snapshot = Snapshot::now();

        self::assertSame(
            $snapshot->human('memoryUsage'),
            $snapshot->human('memory_usage')
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

    #[Test]
    public function testCompareToAndRelations(): void
    {
        $a = new Snapshot(
            'start',
            new DateTimeImmutable(),
            100,
            1_500_000,
            250_000,
            1000,
            2000,
            3000,
            4000,
        );

        $b = new Snapshot(
            'start',
            new DateTimeImmutable(),
            200,
            1_500_000,
            250_000,
            1000,
            2000,
            3000,
            4000,
        );

        self::assertSame(-1, $a->compareTo($b));
        self::assertTrue($a->isBefore($b));
        self::assertFalse($a->isAfter($b));
        self::assertFalse($a->isAtSameTime($b));
        self::assertTrue($a->isBeforeOrAtSameTime($b));
        self::assertFalse($a->isAfterOrAtSameTime($b));

        self::assertSame(1, $b->compareTo($a));
        self::assertTrue($b->isAfter($a));
        self::assertFalse($b->isBefore($a));
        self::assertFalse($b->isAtSameTime($a));
        self::assertFalse($b->isBeforeOrAtSameTime($a));
        self::assertTrue($b->isAfterOrAtSameTime($a));

        $c = clone $a;
        self::assertSame(0, $a->compareTo($c));
        self::assertFalse($a->isBefore($c));
        self::assertFalse($a->isAfter($c));
        self::assertTrue($a->isAtSameTime($c));
        self::assertTrue($a->isBeforeOrAtSameTime($c));
        self::assertTrue($a->isAfterOrAtSameTime($c));
    }
}
