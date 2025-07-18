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
use function strtoupper;

enum MemoryUnit: int
{
    private const REGEXP_PATTERN = '/^
        (?<number>\d+(?:\.\d+)?)
        \s*
        (?<unit>
            b|byte|bytes|
            k|kb|kilobyte|kilobytes|
            m|mb|megabyte|megabytes|
            g|gb|gigabyte|gigabytes|
            t|tb|terbyte|terabytes|
        )
    $/ix';

    //should be declared in descending order!
    case Terabyte = 1_024 ** 4;
    case Gigabyte = 1_024 ** 3;
    case Megabyte = 1_024 ** 2;
    case Kilobyte = 1_024;
    case Byte = 1;

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
        return match (strtoupper($unit)) {
            'B', 'BYTE', 'BYTES' => self::Byte,
            'K', 'KB', 'KILOBYTE', 'KYLOBYTES' => self::Kilobyte,
            'M', 'MB', 'MEGABYTE', 'MEGABYTES' => self::Megabyte,
            'G', 'GB', 'GIGABYTE', 'GIGABYTES' => self::Gigabyte,
            'T', 'TB', 'TERABYTE', 'TERABYTES' => self::Terabyte,
            default => throw new InvalidArgument('Invalid or unsupported memory unit.'),
        };
    }

    /**
     * @throws ValueError If the value is negative.
     *
     * @return float|int Zero or a positive numeric value.
     */
    private static function filterBytes(float|int $bytes): float|int
    {
        0 <= $bytes || throw new ValueError('Bytes cannot be negative');

        if (0.0 === $bytes || 0 === $bytes) {
            return 0;
        }

        return $bytes;
    }

    /**
     * Converts bytes to this unit.
     */
    public function convertFromBytes(float|int $bytes): float|int
    {
        $converted = self::filterBytes($bytes) / $this->value;

        return is_float($converted) && 0.0 === fmod($converted, 1.0) ? (int) $converted : $converted;
    }

    /**
     * Converts a value in this unit to bytes.
     */
    public function convertToBytes(float|int $value): int
    {
        return (int) round(self::filterBytes($value) * $this->value);
    }

    /**
     * Converts a value in this unit to another unit.
     *
     * @param self $to The target unit to convert to.
     * @param float|int $value The value expressed in this unit.
     */
    public function convertTo(self $to, float|int $value): float|int
    {
        return $to->convertFromBytes($this->convertToBytes($value));
    }

    /**
     * Converts a value from another unit to this unit.
     *
     * @param self $from The source unit to convert from.
     * @param float|int $value The value in the source unit.
     */
    public function convertFrom(self $from, float|int $value): float|int
    {
        return $this->convertFromBytes($from->convertToBytes($value));
    }

    /**
     * Tries to parse human-readable string into bytes and returns null on failure.
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
     * Parses human-readable string into bytes.
     *
     * @throws InvalidArgument If the parsing fails
     */
    public static function parse(string $value): int
    {
        1 === preg_match(self::REGEXP_PATTERN, $value, $matches) || throw new InvalidArgument('The value must be a valid memory formatted string.');

        return self::fromUnit($matches['unit'])->convertToBytes((float) $matches['number']);
    }

    /**
     * Formats the given memory (in bytes) into a human-readable string,
     * using the largest fitting memory unit (e.g., "1.5 GB", "200 KB").
     *
     * @param float|int $bytes Memory in bytes.
     * @param ?int $precision Optional number of decimal places to format.
     *
     * @throws ValueError If duration is negative.
     */
    public static function format(float|int $bytes, ?int $precision = null): string
    {
        $bytes = self::filterBytes($bytes);
        foreach (self::cases() as $unit) {
            if ($bytes >= $unit->value) {
                return $unit->formatFromBytes($bytes, $precision).' '.$unit->suffix();
            }
        }

        return self::Byte->formatFromBytes($bytes, $precision).' '.self::Byte->suffix();
    }

    private function formatFromBytes(float|int $bytes, ?int $precision = null): float|int|string
    {
        return null === $precision
            ? $this->convertFromBytes($bytes)
            : number_format($this->convertFromBytes($bytes), $precision, '.', '');
    }

    private function suffix(): string
    {
        return match ($this) {
            self::Byte => 'B',
            self::Kilobyte => 'KB',
            self::Megabyte => 'MB',
            self::Gigabyte => 'GB',
            self::Terabyte => 'TB',
        };
    }

    public static function formatSquared(float|int $bytes2, ?int $precision = null): string
    {
        $units = [
            'B²'  => 1,
            'kB²' => 1_000 ** 2,       // 10^6
            'MB²' => 1_000_000 ** 2,   // 10^12
            'GB²' => 1_000_000_000 ** 2, // 10^18
            'TB²' => 1_000_000_000_000 ** 2, // 10^24
        ];

        foreach (array_reverse($units, true) as $unit => $factor) {
            if ($bytes2 >= $factor) {
                $value = $bytes2 / $factor;
                return number_format($value, $precision ?? 6) . " $unit";
            }
        }

        // Default fallback
        return number_format($bytes2, 6) . " B²";
    }
}
