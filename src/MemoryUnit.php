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
        (?<unit>b|byte|bytes|kb|mb|gb|tb|m|g|t|k)
    $/ix';

    case Terabyte = 1_024 ** 4;
    case Gigabyte = 1_024 ** 3;
    case Megabyte = 1_024 ** 2;
    case Kilobyte = 1_024;
    case Byte = 1;

    public function suffix(): string
    {
        return match ($this) {
            self::Byte => 'B',
            self::Kilobyte => 'KB',
            self::Megabyte => 'MB',
            self::Gigabyte => 'GB',
            self::Terabyte => 'TB',
        };
    }

    public function fromBytes(float|int $bytes): float|int
    {
        $converted = self::filterBytes($bytes) / $this->value;

        return is_float($converted) && 0.0 === fmod($converted, 1.0) ? (int) $converted : $converted;
    }

    public function toBytes(float|int $value): int
    {
        return (int) round(self::filterBytes($value) * $this->value);
    }

    private function formatFromBytes(float|int $bytes, ?int $precision = null): float|int|string
    {
        $value = $this->fromBytes($bytes);

        return null === $precision
            ? $value
            : number_format($value, $precision, '.', '');
    }

    /**
     * Format bytes into human-readable string with the appropriate unit.
     *
     * @param float|int $bytes Number of bytes (non-negative).
     *
     * @throws ValueError If bytes are negative.
     */
    public static function format(float|int $bytes, ?int $precision = null): string
    {
        $bytes = self::filterBytes($bytes);
        if (0 === $bytes) {
            return '0 '.self::Byte->suffix();
        }

        foreach (self::cases() as $unit) {
            if ($bytes >= $unit->value) {
                return $unit->formatFromBytes($bytes, $precision).' '.$unit->suffix();
            }
        }

        return self::Byte->formatFromBytes($bytes, $precision).' '.self::Byte->suffix();
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
     * @throws InvalidArgument If the parsing fails
     */
    public static function parse(string $value): int
    {
        1 === preg_match(self::REGEXP_PATTERN, $value, $matches) || throw new InvalidArgument('The value must be a valid memory formatted string.');

        $unit = match (strtoupper($matches['unit'])) {
            'B', 'BYTES', 'BYTE' => self::Byte,
            'KB', 'K' => self::Kilobyte,
            'MB', 'M' => self::Megabyte,
            'GB', 'G' => self::Gigabyte,
            'TB', 'T' => self::Terabyte,
            default => throw new InvalidArgument('Invalid or unsupported memory unit.'),
        };

        return $unit->toBytes((float) $matches['number']);
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
