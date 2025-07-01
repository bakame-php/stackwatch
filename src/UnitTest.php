<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(DurationUnit::class)]
#[CoversClass(MemoryUnit::class)]
final class UnitTest extends TestCase
{
    #[Test]
    public function it_can_convert_to_bytes(): void
    {
        self::assertSame(0, MemoryUnit::Byte->toBytes(0.0));
        self::assertSame(1, MemoryUnit::Byte->toBytes(1));
        self::assertSame(1_024, MemoryUnit::Kilobyte->toBytes(1));
        self::assertSame(1_024 ** 2, MemoryUnit::Megabyte->toBytes(1));
        self::assertSame(1_024 ** 3, MemoryUnit::Gigabyte->toBytes(1));
        self::assertSame(1_024 ** 4, MemoryUnit::Terabyte->toBytes(1));
    }

    #[Test]
    public function it_can_convert_from_bytes(): void
    {
        self::assertSame('0 B', MemoryUnit::format(0));
        self::assertSame('1 B', MemoryUnit::format(1));
        self::assertSame('1 KB', MemoryUnit::format(1_024));
        self::assertSame('1 MB', MemoryUnit::format(1_024 ** 2));
        self::assertSame('1 GB', MemoryUnit::format(1_024 ** 3));
        self::assertSame('1 TB', MemoryUnit::format(1_024 ** 4));
        self::assertSame('1.000 MB', MemoryUnit::format(1_024 * 1_024, 3));
    }

    #[Test]
    public function it_can_parse_from_byte_format(): void
    {
        self::assertSame(0, MemoryUnit::tryParse('0 b'));
        self::assertSame(0, MemoryUnit::tryParse('0 byte'));
        self::assertSame(0, MemoryUnit::tryParse('0 bytes'));
        self::assertSame(1_024, MemoryUnit::tryParse('1 kB'));
        self::assertSame(1_024 ** 3, MemoryUnit::tryParse('1.0 GB'));
        self::assertSame(1_024 ** 4, MemoryUnit::tryParse('1    TB'));
        self::assertSame(1_024 ** 2, MemoryUnit::tryParse('1.00 MB'));
        self::assertNull(MemoryUnit::tryParse('1.23 OB'));
    }

    #[Test]
    public function it_can_convert_to_nanoseconds(): void
    {
        self::assertSame(1_000 ** 3, DurationUnit::Second->toNanoseconds(1));
        self::assertSame(1_000 ** 2, DurationUnit::Millisecond->toNanoseconds(1));
        self::assertSame(1_000, DurationUnit::Microsecond->toNanoseconds(1));
        self::assertSame(1, DurationUnit::Nanosecond->toNanoseconds(1));
    }

    #[Test]
    public function it_can_convert_from_nanoseconds(): void
    {
        self::assertSame('0 n', DurationUnit::format(0));
        self::assertSame('1 s', DurationUnit::format(1_000_000_000));
        self::assertSame('1 ms', DurationUnit::format(1_000_000));
        self::assertSame('1 µs', DurationUnit::format(1_000));
        self::assertSame('1 n', DurationUnit::format(1));
        self::assertSame('1 min', DurationUnit::format(1_000_000_000 * 60));
        self::assertSame('1 h', DurationUnit::format(1_000_000_000 * 3_600));
        self::assertSame('24.0 h', DurationUnit::format(1_000_000_000 * 86_400, 1));
        self::assertSame('1.000 ms', DurationUnit::format(1_000_000, 3));
    }

    #[Test]
    public function it_can_parse_from_duration_format(): void
    {
        self::assertSame(0, DurationUnit::tryParse('0 n'));
        self::assertSame(0, DurationUnit::tryParse('0 n'));
        self::assertSame(0, DurationUnit::tryParse('0 N'));
        self::assertSame(1_000, DurationUnit::tryParse('1 us'));
        self::assertSame(1_000 ** 2, DurationUnit::tryParse('1.0 ms'));
        self::assertSame(1_000 ** 3, DurationUnit::tryParse('1.0 s'));
        self::assertSame(1_000 ** 3 * 60, DurationUnit::tryParse('1    Min'));
        self::assertSame(1_000 ** 3 * 60, DurationUnit::tryParse('1.00 min'));
        self::assertSame(1_000_000_000 * 86_400, DurationUnit::tryParse('24.0 h'));
        self::assertNull(DurationUnit::tryParse('21.58 days'));
    }

    #[Test]
    public function it_will_fail_with_a_negative_duration(): void
    {
        $this->expectException(ValueError::class);

        DurationUnit::format(-1);
    }

    #[Test]
    public function it_will_fail_with_a_negative_bytes(): void
    {
        $this->expectException(ValueError::class);

        MemoryUnit::format(-1);
    }
}
