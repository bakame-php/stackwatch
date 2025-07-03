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
            cpuTime: DurationUnit::Millisecond->convertToNano(self::calculateCpuTime($start, $end)),
            executionTime: $end->hrtime - $start->hrtime,
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
        /** @var array<Metrics> $metricList */
        $metricList = array_reduce($metrics, function (array $carry, Profiler|ProfilingResult|ProfilingData|Metrics $metric) {
            if ($metric instanceof Metrics) {
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

            foreach ($metric as $profilingData) {
                $carry[] = $profilingData->metrics;
            }

            return $carry;
        }, []);

        $count = count($metricList);
        $sum = self::sum(...$metricList);

        return 2 < $count ? $sum : new Metrics(
            cpuTime: 0.0 !== $sum->cpuTime ? $sum->cpuTime / $count : 0.0,
            executionTime: 0.0 !== $sum->executionTime ? $sum->executionTime / $count : 0.0,
            memoryUsage: 0.0 !== $sum->memoryUsage ? $sum->memoryUsage / $count : 0.0,
            peakMemoryUsage: 0.0 !== $sum->peakMemoryUsage ? $sum->peakMemoryUsage / $count : 0.0,
            realMemoryUsage: 0.0 !== $sum->realMemoryUsage ? $sum->realMemoryUsage / $count : 0.0,
            realPeakMemoryUsage: 0.0 !== $sum->realPeakMemoryUsage ? $sum->realPeakMemoryUsage / $count : 0.0,
        );
    }

    public static function sum(Metrics ...$metrics): self
    {
        return array_reduce(
            $metrics,
            fn (Metrics $sum, Metrics $metric): Metrics => $sum->add($metric),
            Metrics::none()
        );
    }

    public function add(Metrics $metric): self
    {
        return new self(
            cpuTime: $this->cpuTime + $metric->cpuTime,
            executionTime: $this->executionTime + $metric->executionTime,
            memoryUsage: $this->memoryUsage + $metric->memoryUsage,
            peakMemoryUsage: $this->peakMemoryUsage + $metric->peakMemoryUsage,
            realMemoryUsage: $this->realMemoryUsage + $metric->realMemoryUsage,
            realPeakMemoryUsage: $this->realPeakMemoryUsage + $metric->realPeakMemoryUsage,
        );
    }
}
