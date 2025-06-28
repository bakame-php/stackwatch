<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;

use function count;

/**
 * @phpstan-type MetricsStat array{
 *     cpu_time: float,
 *     memory_usage: float,
 *     real_memory_usage: float,
 *     peak_memory_usage: float,
 *     real_peak_memory_usage: float
 * }
 */
final class Metrics implements JsonSerializable
{
    public function __construct(
        public readonly float $cpuTime,
        public readonly float $executionTime,
        public readonly float $memoryUsage,
        public readonly float $peakMemoryUsage,
        public readonly float $realMemoryUsage,
        public readonly float $realPeakMemoryUsage,
    ) {
    }

    public static function fromSnapshots(Snapshot $start, Snapshot $end): self
    {
        $start->timestamp <= $end->timestamp || throw new UnableToProfile('The ending snapshot was taken before the starting snapshot.');

        return new self(
            cpuTime: self::calculateCpuTime($start, $end),
            executionTime: $end->executionTime - $start->executionTime,
            memoryUsage: $end->memoryUsage - $start->memoryUsage,
            peakMemoryUsage: $end->peakMemoryUsage - $start->peakMemoryUsage,
            realMemoryUsage: $end->realMemoryUsage - $start->realMemoryUsage,
            realPeakMemoryUsage: $end->realPeakMemoryUsage - $start->realPeakMemoryUsage,
        );
    }

    public static function none(): self
    {
        return new self(
            cpuTime: 0,
            executionTime: 0,
            memoryUsage: 0,
            peakMemoryUsage: 0,
            realMemoryUsage: 0,
            realPeakMemoryUsage: 0,
        );
    }

    private static function calculateCpuTime(Snapshot $start, Snapshot $end): float
    {
        $cpuStart = $start->cpu;
        $cpuEnd = $end->cpu;

        $utime = ($cpuEnd['ru_utime.tv_sec'] - $cpuStart['ru_utime.tv_sec']) + ($cpuEnd['ru_utime.tv_usec'] - $cpuStart['ru_utime.tv_usec']) / 1_000_000;
        $stime = ($cpuEnd['ru_stime.tv_sec'] - $cpuStart['ru_stime.tv_sec']) + ($cpuEnd['ru_stime.tv_usec'] - $cpuStart['ru_stime.tv_usec']) / 1_000_000;

        return $utime + $stime;
    }

    /**
     * @return MetricsStat
     */
    public function jsonSerialize(): array
    {
        return $this->stats();
    }

    /**
     * @return MetricsStat
     */
    public function stats(): array
    {
        return [
            'cpu_time' => $this->cpuTime,
            'memory_usage' => $this->memoryUsage,
            'real_memory_usage' => $this->realMemoryUsage,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'real_peak_memory_usage' => $this->realPeakMemoryUsage,
        ];
    }

    public static function avg(Metrics|Profiler ...$metrics): self
    {
        $sum = Metrics::none();
        $count = 0;
        foreach ($metrics as $metric) {
            if ($metric instanceof self) {
                ++$count;
                $sum = self::add($sum, $metric);

                continue;
            }

            $count += count($metric);
            foreach ($metric as $profile) {
                $sum = self::add($sum, $profile->metrics);
            }
        }

        return new Metrics(
            cpuTime: $sum->cpuTime / $count,
            executionTime: $sum->executionTime / $count,
            memoryUsage: $sum->memoryUsage / $count,
            peakMemoryUsage: $sum->peakMemoryUsage / $count,
            realMemoryUsage: $sum->realMemoryUsage / $count,
            realPeakMemoryUsage: $sum->realPeakMemoryUsage / $count,
        );
    }

    private static function add(Metrics $carry, Metrics $metric): Metrics
    {
        return new self(
            cpuTime: $carry->cpuTime + $metric->cpuTime,
            executionTime: $carry->executionTime + $metric->executionTime,
            memoryUsage: $carry->memoryUsage + $metric->memoryUsage,
            peakMemoryUsage: $carry->peakMemoryUsage + $metric->peakMemoryUsage,
            realMemoryUsage: $carry->realMemoryUsage + $metric->realMemoryUsage,
            realPeakMemoryUsage: $carry->realPeakMemoryUsage + $metric->realPeakMemoryUsage,
        );
    }
}
