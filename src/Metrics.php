<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;

use function array_keys;
use function array_reduce;
use function count;
use function implode;

/**
 * @phpstan-type MetricsStat array{
 *     cpu_time: float,
 *     execution_time: float,
 *     memory_usage: float,
 *     real_memory_usage: float,
 *     peak_memory_usage: float,
 *     real_peak_memory_usage: float
 * }
 * @phpstan-type MetricsHumanReadable array{
 *      cpu_time: string,
 *      execution_time: string,
 *      memory_usage: string,
 *      real_memory_usage: string,
 *      peak_memory_usage: string,
 *     real_peak_memory_usage: string
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
        ($excutionTime = $end->hrtime - $start->hrtime) >= 0 || throw new UnableToProfile('The ending snapshot was taken before the starting snapshot.');

        return new self(
            cpuTime: self::calculateCpuTime($start, $end),
            executionTime: $excutionTime,
            memoryUsage: $end->memoryUsage - $start->memoryUsage,
            peakMemoryUsage: $end->peakMemoryUsage - $start->peakMemoryUsage,
            realMemoryUsage: $end->realMemoryUsage - $start->realMemoryUsage,
            realPeakMemoryUsage: $end->realPeakMemoryUsage - $start->realPeakMemoryUsage,
        );
    }

    public static function average(Profiler|ProfilingResult|ProfilingData|Metrics ...$metrics): self
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

        return 2 > $count ? $sum : new Metrics(
            cpuTime: $sum->cpuTime / $count,
            executionTime: $sum->executionTime / $count,
            memoryUsage: $sum->memoryUsage / $count,
            peakMemoryUsage: $sum->peakMemoryUsage / $count,
            realMemoryUsage: $sum->realMemoryUsage / $count,
            realPeakMemoryUsage: $sum->realPeakMemoryUsage / $count,
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

    private static function calculateCpuTime(Snapshot $start, Snapshot $end): float
    {
        $cpuStart = $start->cpu;
        $cpuEnd = $end->cpu;

        return ($cpuEnd['ru_utime.tv_sec'] * 1000 ** 3) - ($cpuStart['ru_utime.tv_sec'] * 1000 ** 3)
            + ($cpuEnd['ru_utime.tv_usec'] * 1000) - ($cpuStart['ru_utime.tv_usec'] * 1000)
            + ($cpuEnd['ru_stime.tv_sec'] * 1000 ** 3) - ($cpuStart['ru_stime.tv_sec'] * 1000 ** 3)
            + ($cpuEnd['ru_stime.tv_usec'] * 1000) - ($cpuStart['ru_stime.tv_usec'] * 1000);
    }

    /**
     * @return MetricsStat
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return MetricsStat
     */
    public function toArray(): array
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

    /**
     * @throws InvalidArgument if the metrics property is unknown or not supported.
     *
     * @return MetricsHumanReadable|string
     */
    public function forHuman(?string $property = null): array|string
    {
        $humans =  [
            'cpu_time' => DurationUnit::format($this->cpuTime, 3),
            'execution_time' => DurationUnit::format($this->executionTime, 3),
            'memory_usage' => MemoryUnit::format($this->memoryUsage, 1),
            'real_memory_usage' => MemoryUnit::format($this->realMemoryUsage, 1),
            'peak_memory_usage' => MemoryUnit::format($this->peakMemoryUsage, 1),
            'real_peak_memory_usage' => MemoryUnit::format($this->realPeakMemoryUsage, 1),
        ];

        if (null === $property) {
            return $humans;
        }

        return $humans[$property] ?? throw new InvalidArgument('Unknown metrics name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
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
