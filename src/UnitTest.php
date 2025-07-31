<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Unit::class)]
#[CoversClass(DurationUnit::class)]
#[CoversClass(MemoryUnit::class)]
final class UnitTest extends TestCase
{
    #[DataProvider('squaredDurationProvider')]
    public function testFormatAndParseSquaredDuration(float $input, string $expectedFormat): void
    {
        self::assertSame($expectedFormat, Unit::Nanoseconds->formatSquared($input));
    }

    #[DataProvider('squaredMemoryProvider')]
    public function testFormatAndParseSquaredMemory(float $input, string $expectedFormat): void
    {
        self::assertSame($expectedFormat, Unit::Bytes->formatSquared($input, 1));
    }

    /**
     * @return array<array{0: float, 1:string}>
     */
    public static function squaredDurationProvider(): array
    {
        return [
            [1_000_000.0, '1.000000 μs²'],
            [144_000_000.0, '144.000000 μs²'],
            [1_000_000_000_000.0, '1.000000 ms²'],
        ];
    }

    /**
     * @return array<array{0: float, 1:string}>
     */
    public static function squaredMemoryProvider(): array
    {
        return [
            [1_048_576.0, '1.0 KB²'],
            [1_099_511_627_776.0, '1.0 MB²'],
            [1_152_921_504_606_846_976.0, '1.0 GB²'], // 1 GB²
        ];
    }
}
