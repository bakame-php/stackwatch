<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(TimeUnit::class)]
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
    public function it_can_convert_to_nanoseconds(): void
    {
        self::assertSame(1_000 ** 3, TimeUnit::Second->toNanoseconds(1));
        self::assertSame(1_000 ** 2, TimeUnit::Millisecond->toNanoseconds(1));
        self::assertSame(1_000, TimeUnit::Microsecond->toNanoseconds(1));
        self::assertSame(1, TimeUnit::Nanosecond->toNanoseconds(1));
    }

    #[Test]
    public function it_can_convert_from_nanoseconds(): void
    {
        self::assertSame('0 n', TimeUnit::format(0));
        self::assertSame('1 s', TimeUnit::format(1_000_000_000));
        self::assertSame('1 ms', TimeUnit::format(1_000_000));
        self::assertSame('1 µs', TimeUnit::format(1_000));
        self::assertSame('1 n', TimeUnit::format(1));
        self::assertSame('1 min', TimeUnit::format(1_000_000_000 * 60));
        self::assertSame('1 h', TimeUnit::format(1_000_000_000 * 3_600));
        self::assertSame('24.0 h', TimeUnit::format(1_000_000_000 * 86_400, 1));
        self::assertSame('1.000 ms', TimeUnit::format(1_000_000, 3));
    }

    #[Test]
    public function it_will_fail_with_a_negative_duration(): void
    {
        $this->expectException(ValueError::class);

        TimeUnit::format(-1);
    }

    #[Test]
    public function it_will_fail_with_a_negative_bytes(): void
    {
        $this->expectException(ValueError::class);

        MemoryUnit::format(-1);
    }
}
