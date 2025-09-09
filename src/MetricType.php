<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

enum MetricType: string
{
    case CpuTime = 'cpu_time';
    case ExecutionTime = 'execution_time';
    case MemoryUsage = 'memory_usage';
    case MemoryUsageGrowth = 'memory_usage_growth';
    case PeakMemoryUsage = 'peak_memory_usage';
    case PeakMemoryUsageGrowth = 'peak_memory_usage_growth';
    case RealMemoryUsage = 'real_memory_usage';
    case RealMemoryUsageGrowth = 'real_memory_usage_growth';
    case RealPeakMemoryUsage = 'real_peak_memory_usage';
    case RealPeakMemoryUsageGrowth = 'real_peak_memory_usage_growth';

    public function isGrowth(): bool
    {
        return match ($this) {
            self::MemoryUsageGrowth,
            self::PeakMemoryUsageGrowth,
            self::RealMemoryUsageGrowth,
            self::RealPeakMemoryUsageGrowth => true,
            default => false,
        };
    }

    public function unit(): Unit
    {
        return match ($this) {
            self::CpuTime, self::ExecutionTime => Unit::Nanoseconds,
            default => Unit::Bytes,
        };
    }
}
