<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

enum Unit: string
{
   case Nanoseconds = 'nanoseconds';
   case Bytes = 'bytes';

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
