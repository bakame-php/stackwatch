<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeUnit::class)]
#[CoversClass(MemoryUnit::class)]
final class UnitTest extends TestCase
{
    #[Test]
    public function it_can_convert_to_bytes(): void
    {
        self::assertSame(1, MemoryUnit::Byte->toBytes(1));
        self::assertSame(1_024, MemoryUnit::Kilobyte->toBytes(1));
        self::assertSame(1_024 ** 2, MemoryUnit::Megabyte->toBytes(1));
        self::assertSame(1_024 ** 3, MemoryUnit::Gigabyte->toBytes(1));
        self::assertSame(1_024 ** 4, MemoryUnit::Terabyte->toBytes(1));
    }
    #[Test]
    public function it_can_convert_from_bytes(): void
    {
        self::assertSame('1', MemoryUnit::Byte->formatFromBytes(1));
        self::assertSame('1', MemoryUnit::Kilobyte->formatFromBytes(1_024));
        self::assertSame('1', MemoryUnit::Megabyte->formatFromBytes(1_024 ** 2));
        self::assertSame('1', MemoryUnit::Gigabyte->formatFromBytes(1_024 ** 3));
        self::assertSame('1', MemoryUnit::Terabyte->formatFromBytes(1_024 ** 4));
        self::assertSame('1.000', MemoryUnit::Megabyte->formatFromBytes(1_024 * 1_024, 3));
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
        self::assertSame('1', TimeUnit::Second->formatFromNanoseconds(1_000_000_000));
        self::assertSame('1', TimeUnit::Millisecond->formatFromNanoseconds(1_000_000));
        self::assertSame('1', TimeUnit::Microsecond->formatFromNanoseconds(1_000));
        self::assertSame('1', TimeUnit::Nanosecond->formatFromNanoseconds(1));
        self::assertSame('1', TimeUnit::Minute->formatFromNanoseconds(1_000_000_000 * 60));
        self::assertSame('1', TimeUnit::Hour->formatFromNanoseconds(1_000_000_000 * 3_600));
        self::assertSame('1', TimeUnit::Day->formatFromNanoseconds(1_000_000_000 * 86_400));
        self::assertSame('1.000', TimeUnit::Millisecond->formatFromNanoseconds(1_000_000, 3));
    }
}
