<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use function number_format;
use function round;

enum MemoryUnit: int
{
    case Byte = 1;
    case Kilobyte = 1_024;
    case Megabyte = 1_024 ** 2;
    case Gigabyte = 1_024 ** 3;
    case Terabyte = 1_024 ** 4;

    public function fromBytes(float|int $bytes): float|int
    {
        $converted = $bytes / $this->value;

        return is_float($converted) && 0.0 === fmod($converted, 1.0) ? (int) $converted : $converted;
    }

    public function toBytes(float|int $value): int
    {
        return (int) round($value * $this->value);
    }

    public function formatFromBytes(float|int $bytes, ?int $precision = null): string
    {
        $value = $this->fromBytes($bytes);

        return null === $precision
            ? (string) $value
            : number_format($value, $precision, '.', '');
    }
}
