<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use ValueError;

use function number_format;
use function round;

enum MemoryUnit: int
{
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
        0 <= $bytes || throw new ValueError('Bytes cannot be negative');

        if (0.0 === $bytes || 0 === $bytes) {
            return 0;
        }

        $converted = $bytes / $this->value;

        return is_float($converted) && 0.0 === fmod($converted, 1.0) ? (int) $converted : $converted;
    }

    public function toBytes(float|int $value): int
    {
        0 <= $value || throw new ValueError('Bytes cannot be negative');

        if (0.0 === $value || 0 === $value) {
            return 0;
        }

        return (int) round($value * $this->value);
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
    public static function format(int|float $bytes, ?int $precision = null): string
    {
        foreach (self::cases() as $unit) {
            if ($bytes >= $unit->value) {
                return $unit->formatFromBytes($bytes, $precision).' '.$unit->suffix();
            }
        }

        0 <= $bytes || throw new ValueError('Bytes cannot be negative');

        return '0 '.self::Byte->suffix();
    }
}
