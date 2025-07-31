<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(DurationUnit::class)]
final class DurationUnitTest extends TestCase
{
    #[Test]
    public function it_can_convert_to_nanoseconds(): void
    {
        self::assertSame(1_000 ** 3, DurationUnit::Second->convertToNano(1));
        self::assertSame(1_000 ** 2, DurationUnit::Millisecond->convertToNano(1));
        self::assertSame(1_000, DurationUnit::Microsecond->convertToNano(1));
        self::assertSame(1, DurationUnit::Nanosecond->convertToNano(1));
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
    #[DataProvider('provideConversions')]
    public function it_can_convert_to(float|int $value, DurationUnit $from, DurationUnit $to, float|int $expected): void
    {
        self::assertSame($expected, $from->convertTo($to, $value));
    }

    #[Test]
    #[DataProvider('provideConversions')]
    public function it_can_convert_from(float|int $value, DurationUnit $from, DurationUnit $to, float|int $expected): void
    {
        self::assertSame($expected, $to->convertFrom($from, $value));
    }

    /**
     * @return iterable<array{value:float|int, from:DurationUnit, to:DurationUnit, expected:float|int}>
     */
    public static function provideConversions(): iterable
    {
        return [
            ['value' => 1, 'from' => DurationUnit::Hour, 'to' => DurationUnit::Minute, 'expected' => 60],
            ['value' => 90, 'from' => DurationUnit::Minute, 'to' => DurationUnit::Hour, 'expected' => 1.5],
            ['value' => 2, 'from' => DurationUnit::Second, 'to' => DurationUnit::Millisecond, 'expected' => 2000],
            ['value' => 500, 'from' => DurationUnit::Millisecond, 'to' => DurationUnit::Second, 'expected' => 0.5],
            ['value' => 1_000_000, 'from' => DurationUnit::Nanosecond, 'to' => DurationUnit::Microsecond, 'expected' => 1000],
            ['value' => 3.5, 'from' => DurationUnit::Hour, 'to' => DurationUnit::Minute, 'expected' => 210],
            ['value' => 123, 'from' => DurationUnit::Microsecond, 'to' => DurationUnit::Nanosecond, 'expected' => 123_000],
        ];
    }
}
