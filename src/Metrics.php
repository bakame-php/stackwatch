<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;

use function array_reduce;
use function count;

/**
 * @phpstan-type MetricsStat array{
 *     cpu_time: float,
 *     execution_time: float,
 *     memory_usage: float,
 *     real_memory_usage: float,
 *     peak_memory_usage: float,
 *     real_peak_memory_usage: float
 * }
 */
final class Metrics implements JsonSerializable
{
    /**
     * @param float $cpuTime expressed in nanoseconds
     * @param float $executionTime expressed in nanoseconds
     * @param float $memoryUsage expressed in bytes
     * @param float $peakMemoryUsage expressed in bytes
     * @param float $realMemoryUsage expressed in bytes
     * @param float $realPeakMemoryUsage expressed in bytes
     */
    public function __construct(
        public readonly float $cpuTime,
        public readonly float $executionTime,
        public readonly float $memoryUsage,
        public readonly float $peakMemoryUsage,
        public readonly float $realMemoryUsage,
        public readonly float $realPeakMemoryUsage,
    ) {
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

    public static function fromSnapshots(Snapshot $start, Snapshot $end): self
    {
        $start->timestamp <= $end->timestamp || throw new UnableToProfile('The ending snapshot was taken before the starting snapshot.');

        return new self(
            cpuTime: self::calculateCpuTime($start, $end) * 1000,
            executionTime: $end->executionTime - $start->executionTime,
            memoryUsage: $end->memoryUsage - $start->memoryUsage,
            peakMemoryUsage: $end->peakMemoryUsage - $start->peakMemoryUsage,
            realMemoryUsage: $end->realMemoryUsage - $start->realMemoryUsage,
            realPeakMemoryUsage: $end->realPeakMemoryUsage - $start->realPeakMemoryUsage,
        );
    }

    private static function calculateCpuTime(Snapshot $start, Snapshot $end): float
    {
        $cpuStart = $start->cpu;
        $cpuEnd = $end->cpu;

        return ($cpuEnd['ru_utime.tv_sec'] - $cpuStart['ru_utime.tv_sec'])
            + ($cpuEnd['ru_utime.tv_usec'] - $cpuStart['ru_utime.tv_usec'])
            + ($cpuEnd['ru_stime.tv_sec'] - $cpuStart['ru_stime.tv_sec'])
            + ($cpuEnd['ru_stime.tv_usec'] - $cpuStart['ru_stime.tv_usec']);
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
            'execution_time' => $this->executionTime,
            'memory_usage' => $this->memoryUsage,
            'real_memory_usage' => $this->realMemoryUsage,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'real_peak_memory_usage' => $this->realPeakMemoryUsage,
        ];
    }

    public static function avg(Profiler|ProfilingResult|ProfilingData|Metrics ...$metrics): self
    {
        /** @var array<Metrics> $metricsList */
        $metricsList = array_reduce($metrics, function (array $carry, Profiler|ProfilingResult|ProfilingData|Metrics $metric) {
            if ($metric instanceof self) {
                $carry[] = $metric;

                return $carry;

            }

            if ($metric instanceof ProfilingData) {
                $carry[] = $metric->metrics;

                return $carry;
            }

            if ($metric instanceof ProfilingResult) {
                $carry[] = $metric->profilingData->metrics;

                return $carry;
            }

            foreach ($metric as $profile) {
                $carry[] = $profile->metrics;
            }

            return $carry;
        }, []);

        $count = count($metricsList);
        $sum = self::add(...$metricsList);

        return 2 < $count ? $sum : new Metrics(
            cpuTime: $sum->cpuTime / $count,
            executionTime: $sum->executionTime / $count,
            memoryUsage: $sum->memoryUsage / $count,
            peakMemoryUsage: $sum->peakMemoryUsage / $count,
            realMemoryUsage: $sum->realMemoryUsage / $count,
            realPeakMemoryUsage: $sum->realPeakMemoryUsage / $count,
        );
    }

    public static function add(Metrics ...$metrics): Metrics
    {
        return array_reduce(
            $metrics,
            fn (Metrics $sum, Metrics $metric): Metrics => new self(
                cpuTime: $sum->cpuTime + $metric->cpuTime,
                executionTime: $sum->executionTime + $metric->executionTime,
                memoryUsage: $sum->memoryUsage + $metric->memoryUsage,
                peakMemoryUsage: $sum->peakMemoryUsage + $metric->peakMemoryUsage,
                realMemoryUsage: $sum->realMemoryUsage + $metric->realMemoryUsage,
                realPeakMemoryUsage: $sum->realPeakMemoryUsage + $metric->realPeakMemoryUsage,
            ),
            Metrics::none()
        );
    }
}
