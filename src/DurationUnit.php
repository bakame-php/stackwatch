<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Throwable;
use ValueError;

use function fmod;
use function is_float;
use function number_format;
use function preg_match;
use function round;
use function strtolower;

enum DurationUnit: int
{
    private const REGEXP_PATTERN = '/^
        (?<number>\d+(?:\.\d+)?)
        \s*
        (?<unit>
            n|ns|nanosecond|nanoseconds|
            us|µs|microsecond|microseconds|
            ms|millisecond|milliseconds|
            s|second|seconds|
            min|minute|minutes|
            h|hour|hours
        )
    $/ix';

    case Hour = 1_000 ** 3 * 3_600;
    case Minute = 1_000 ** 3 * 60;
    case Second = 1_000 ** 3;
    case Millisecond = 1_000 ** 2;
    case Microsecond = 1_000;
    case Nanosecond = 1;

    public function fromNanoseconds(float|int $nanoseconds): float|int
    {
        $converted = self::filterDuration($nanoseconds) / $this->value;

        return is_float($converted) && 0.0 === fmod($converted, 1.0) ? (int) $converted : $converted;
    }

    public function toNanoseconds(float|int $value): int
    {
        return (int) round(self::filterDuration($value) * $this->value);
    }

    /**
     * Format duration into human-readable string with the appropriate unit.
     *
     * @throws ValueError If duration is negative.
     */
    public static function format(float|int $value, ?int $precision = null): string
    {
        $value = self::filterDuration($value);
        if (0 === $value) {
            return '0 '.self::Nanosecond->suffix();
        }

        foreach (self::cases() as $unit) {
            if ($value >= $unit->value) {
                return $unit->formatFromNanoseconds($value, $precision).' '.$unit->suffix();
            }
        }

        return self::Nanosecond->formatFromNanoseconds($value, $precision).' '.self::Nanosecond->suffix();
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

    /**
     * @throws ValueError if bytes are not positive integer or zero
     *
     * @return float|int Zero or a positive numeric value.
     */
    private static function filterDuration(float|int $duration): float|int
    {
        0 <= $duration || throw new ValueError('duration cannot be negative');

        if (0.0 === $duration || 0 === $duration) {
            return 0;
        }

        return $duration;
    }

    /**
     * @throws InvalidArgument If the parsing fails
     */
    public static function parse(string $value): int
    {
        1 === preg_match(self::REGEXP_PATTERN, $value, $matches) || throw new InvalidArgument('The value must be a valid time duration formatted string.');

        $unit = match (strtolower($matches['unit'])) {
            'n', 'ns', 'nanosecond', 'nanoseconds' => self::Nanosecond,
            'us', 'µs', 'microsecond', 'microseconds' => self::Microsecond,
            'ms', 'millisecond', 'milliseconds' => self::Millisecond,
            's', 'second', 'seconds' => self::Second,
            'min', 'minute', 'minutes' => self::Minute,
            'h' , 'hour', 'hours' => self::Hour,
            default => throw new InvalidArgument('Invalid or unsupported time unit.'),
        };

        return $unit->toNanoseconds((float) $matches['number']);
    }

    public static function tryParse(string $value): ?int
    {
        try {
            return self::parse($value);
        } catch (Throwable $exception) {
            return null;
        }
    }
}
