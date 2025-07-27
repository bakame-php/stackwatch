<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

enum Unit: string
{
    case Nanoseconds = 'nanoseconds';
    case Bytes = 'bytes';

    public function parse(string $format): int
    {
        return match ($this) {
            self::Nanoseconds => DurationUnit::parse($format),
            default => MemoryUnit::parse($format),
        };
    }

    public function tryParse(string $format): int|null
    {
        return match ($this) {
            self::Nanoseconds => DurationUnit::tryParse($format),
            default => MemoryUnit::tryParse($format),
        };
    }

    public function parseSquared(string $format): float
    {
        return match ($this) {
            self::Nanoseconds => DurationUnit::parseSquared($format),
            default => MemoryUnit::parseSquared($format),
        };
    }

    public function format(float|int $unit, ?int $precision = null): string
    {
        return match ($this) {
            self::Nanoseconds => DurationUnit::format($unit, $precision),
            default => MemoryUnit::format($unit, $precision),
        };
    }

    public function formatSquared(float|int $unit, ?int $precision = null): string
    {
        return match ($this) {
            self::Nanoseconds => DurationUnit::formatSquared($unit, $precision),
            default => MemoryUnit::formatSquared($unit, $precision),
        };
    }
}
