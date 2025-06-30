<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use function fmod;
use function is_float;
use function number_format;
use function round;

enum TimeUnit: int
{
    case Nanosecond = 1;
    case Microsecond = 1_000;
    case Millisecond = 1_000 ** 2;
    case Second = 1_000 ** 3;
    case Minute = 1_000 ** 3 * 60;
    case Hour = 1_000 ** 3 * 3_600;
    case Day = 1_000 ** 3 * 86_400;

    public function fromNanoseconds(float|int $nanoseconds): float|int
    {
        $converted = $nanoseconds / $this->value;

        return is_float($converted) && 0.0 === fmod($converted, 1.0) ? (int) $converted : $converted;
    }

    public function toNanoseconds(float|int $value): int
    {
        return (int) round($value * $this->value);
    }

    public function formatFromNanoseconds(float|int $value, ?int $precision = null): string
    {
        $value = $this->fromNanoseconds($value);

        return null === $precision
            ? (string) $value
            : number_format($value, $precision, '.', '');
    }
}
