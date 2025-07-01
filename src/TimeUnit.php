<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use ValueError;

use function fmod;
use function is_float;
use function number_format;
use function round;

enum TimeUnit: int
{
    case Hour = 1_000 ** 3 * 3_600;
    case Minute = 1_000 ** 3 * 60;
    case Second = 1_000 ** 3;
    case Millisecond = 1_000 ** 2;
    case Microsecond = 1_000;
    case Nanosecond = 1;

    public function fromNanoseconds(float|int $nanoseconds): float|int
    {
        0 <= $nanoseconds || throw new ValueError('duration cannot be negative');
        if (0.0 === $nanoseconds || 0 === $nanoseconds) {
            return 0;
        }

        $converted = $nanoseconds / $this->value;

        return is_float($converted) && 0.0 === fmod($converted, 1.0) ? (int) $converted : $converted;
    }

    public function toNanoseconds(float|int $value): int
    {
        0 <= $value || throw new ValueError('duration cannot be negative');
        if (0.0 === $value || 0 === $value) {
            return 0;
        }

        return (int) round($value * $this->value);
    }

    /**
     * Format duration into human-readable string with the appropriate unit.
     *
     * @throws ValueError If duration is negative.
     */
    public static function format(float|int $value, ?int $precision = null): string
    {
        foreach (self::cases() as $unit) {
            if ($value >= $unit->value) {
                return $unit->formatFromNanoseconds($value, $precision).' '.$unit->suffix();
            }
        }

        0 <= $value || throw new ValueError('duration cannot be negative');

        return '0 '.self::Nanosecond->suffix();
    }

    private function formatFromNanoseconds(float|int $value, ?int $precision = null): string
    {
        $value = $this->fromNanoseconds($value);

        return null === $precision
            ? (string) $value
            : number_format($value, $precision, '.', '');
    }

    public function suffix(): string
    {
        return match ($this) {
            self::Nanosecond => 'n',
            self::Microsecond => 'µs',
            self::Millisecond => 'ms',
            self::Second => 's',
            self::Minute => 'min',
            self::Hour => 'h',
        };
    }
}
