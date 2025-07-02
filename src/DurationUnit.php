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

    //should be declared in descending order!
    case Hour = 3_600_000_000_000;
    case Minute = 60_000_000_000;
    case Second = 1_000 ** 3;
    case Millisecond = 1_000 ** 2;
    case Microsecond = 1_000;
    case Nanosecond = 1;

    public static function tryFromUnit(string $unit): ?self
    {
        try {
            return self::fromUnit($unit);
        } catch (Throwable) {
            return null;
        }
    }

    public static function fromUnit(string $unit): self
    {
        return match (strtolower($unit)) {
            'n', 'ns', 'nanosecond', 'nanoseconds' => self::Nanosecond,
            'us', 'µs', 'microsecond', 'microseconds' => self::Microsecond,
            'ms', 'millisecond', 'milliseconds' => self::Millisecond,
            's', 'second', 'seconds' => self::Second,
            'min', 'minute', 'minutes' => self::Minute,
            'h' , 'hour', 'hours' => self::Hour,
            default => throw new InvalidArgument('Invalid or unsupported duration unit.'),
        };
    }

    /**
     * Converts nanoseconds to this unit.
     */
    public function convertFromNano(float|int $nanoseconds): float|int
    {
        $converted = self::filterDuration($nanoseconds) / $this->value;

        return is_float($converted) && 0.0 === fmod($converted, 1.0) ? (int) $converted : $converted;
    }

    /**
     * Converts a value in this unit to nanoseconds.
     */
    public function convertToNano(float|int $value): int
    {
        return (int) round(self::filterDuration($value) * $this->value);
    }

    /**
     * Tries to parse human-readable string into nanoseconds and returns null on failure.
     */
    public static function tryParse(string $value): ?int
    {
        try {
            return self::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parses human-readable string into nanoseconds.
     *
     * @throws InvalidArgument If the parsing fails
     */
    public static function parse(string $value): int
    {
        1 === preg_match(self::REGEXP_PATTERN, $value, $matches) || throw new InvalidArgument('The value must be a valid time duration formatted string.');

        return self::fromUnit($matches['unit'])->convertToNano((float) $matches['number']);
    }

    /**
     * Formats the given duration (in nanoseconds) into a human-readable string,
     * using the largest fitting time unit (e.g., "1.5 min", "200 µs").
     *
     * @param float|int $nanoseconds Duration in nanoseconds.
     * @param int|null $precision Optional number of decimal places to format.
     *
     * @throws ValueError If duration is negative.
     */
    public static function format(float|int $nanoseconds, ?int $precision = null): string
    {
        $nanoseconds = self::filterDuration($nanoseconds);
        foreach (self::cases() as $unit) {
            if ($nanoseconds >= $unit->value) {
                return $unit->formatFromNanoseconds($nanoseconds, $precision).' '.$unit->suffix();
            }
        }

        return self::Nanosecond->formatFromNanoseconds($nanoseconds, $precision).' '.self::Nanosecond->suffix();
    }

    private function formatFromNanoseconds(float|int $nanoseconds, ?int $precision = null): float|int|string
    {
        return null === $precision
            ? $this->convertFromNano($nanoseconds)
            : number_format($this->convertFromNano($nanoseconds), $precision, '.', '');
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

    private function suffix(): string
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
