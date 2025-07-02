<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(MemoryUnit::class)]
final class MemoryUnitTest extends TestCase
{
    #[Test]
    public function it_can_convert_to_bytes(): void
    {
        self::assertSame(0, MemoryUnit::Byte->convertToBytes(0.0));
        self::assertSame(1, MemoryUnit::Byte->convertToBytes(1));
        self::assertSame(1_024, MemoryUnit::Kilobyte->convertToBytes(1));
        self::assertSame(1_024 ** 2, MemoryUnit::Megabyte->convertToBytes(1));
        self::assertSame(1_024 ** 3, MemoryUnit::Gigabyte->convertToBytes(1));
        self::assertSame(1_024 ** 4, MemoryUnit::Terabyte->convertToBytes(1));
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
    public function it_will_fail_with_a_negative_bytes(): void
    {
        $this->expectException(ValueError::class);

        MemoryUnit::format(-1);
    }
}
