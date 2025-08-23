<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;
use Throwable;

use function array_diff_key;
use function array_keys;
use function implode;

/**
 * @phpstan-import-type StatsMap from Statistics
 * @phpstan-import-type StatsHumanReadable from Statistics
 * @phpstan-import-type MetricsHumanReadable from Metrics
 * @phpstan-type ReportMap array{
 *      cpu_time: StatsMap,
 *      execution_time: StatsMap,
 *      memory_usage: StatsMap,
 *      real_memory_usage: StatsMap,
 *      peak_memory_usage: StatsMap,
 *      real_peak_memory_usage: StatsMap,
 *  }
 * @phpstan-type ReportHumanReadable array{
 *       cpu_time: StatsHumanReadable,
 *       execution_time: StatsHumanReadable,
 *       memory_usage: StatsHumanReadable,
 *       real_memory_usage: StatsHumanReadable,
 *       peak_memory_usage: StatsHumanReadable,
 *       real_peak_memory_usage: StatsHumanReadable,
 *  }
 */
final class Report implements JsonSerializable
{
    public function __construct(
        public readonly Statistics $cpuTime,
        public readonly Statistics $executionTime,
        public readonly Statistics $memoryUsage,
        public readonly Statistics $peakMemoryUsage,
        public readonly Statistics $realMemoryUsage,
        public readonly Statistics $realPeakMemoryUsage,
    ) {
        Unit::Nanoseconds === $this->cpuTime->unit || throw new InvalidArgument('Invalid cpu time unit specified');
        Unit::Nanoseconds === $this->executionTime->unit || throw new InvalidArgument('Invalid execution time unit specified');
        Unit::Bytes === $this->memoryUsage->unit || throw new InvalidArgument('Invalid memory usage unit specified');
        Unit::Bytes === $this->peakMemoryUsage->unit || throw new InvalidArgument('Invalid peak memory usage unit specified');
        Unit::Bytes === $this->realMemoryUsage->unit || throw new InvalidArgument('Invalid real memory usage unit specified');
        Unit::Bytes === $this->realPeakMemoryUsage->unit || throw new InvalidArgument('Invalid real peak memory usage unit specified');
    }

    /**
     * @return array{
     *     cpu_time: Statistics,
     *     execution_time: Statistics,
     *     memory_usage: Statistics,
     *     real_memory_usage: Statistics,
     *     peak_memory_usage: Statistics,
     *     real_peak_memory_usage: Statistics,
     * }
     */
    public function jsonSerialize(): array
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
     * @return ReportMap
     */
    public function toArray(): array
    {
        return [
            'cpu_time' => $this->cpuTime->toArray(),
            'execution_time' => $this->executionTime->toArray(),
            'memory_usage' => $this->memoryUsage->toArray(),
            'real_memory_usage' => $this->realMemoryUsage->toArray(),
            'peak_memory_usage' => $this->peakMemoryUsage->toArray(),
            'real_peak_memory_usage' => $this->realPeakMemoryUsage->toArray(),
        ];
    }

    public static function fromMetrics(Timeline|Profiler|Span|Metrics ...$metrics): self
    {
        $statistics = [
            'cpuTime' => [],
            'executionTime' => [],
            'memoryUsage' => [],
            'peakMemoryUsage' => [],
            'realMemoryUsage' => [],
            'realPeakMemoryUsage' => [],
        ];

        foreach (Metrics::yieldFrom(...$metrics) as $metric) {
            $statistics['cpuTime'][] = $metric->cpuTime;
            $statistics['executionTime'][] = $metric->executionTime;
            $statistics['memoryUsage'][] = $metric->memoryUsage;
            $statistics['peakMemoryUsage'][] = $metric->peakMemoryUsage;
            $statistics['realMemoryUsage'][] = $metric->realMemoryUsage;
            $statistics['realPeakMemoryUsage'][] = $metric->realPeakMemoryUsage;
        }

        return new self(
            cpuTime: Statistics::fromValues(Unit::Nanoseconds, $statistics['cpuTime']),
            executionTime: Statistics::fromValues(Unit::Nanoseconds, $statistics['executionTime']),
            memoryUsage: Statistics::fromValues(Unit::Bytes, $statistics['memoryUsage']),
            peakMemoryUsage: Statistics::fromValues(Unit::Bytes, $statistics['peakMemoryUsage']),
            realMemoryUsage: Statistics::fromValues(Unit::Bytes, $statistics['realMemoryUsage']),
            realPeakMemoryUsage: Statistics::fromValues(Unit::Bytes, $statistics['realPeakMemoryUsage']),
        );
    }

    public static function none(): self
    {
        $nanoStats = Statistics::none(Unit::Nanoseconds);
        $byteStats = Statistics::none(Unit::Bytes);

        return new self(
            cpuTime: $nanoStats,
            executionTime: $nanoStats,
            memoryUsage: $byteStats,
            peakMemoryUsage: $byteStats,
            realMemoryUsage: $byteStats,
            realPeakMemoryUsage: $byteStats,
        );
    }

    /**
     * @param ReportMap $data
     *
     * @throws InvalidArgument
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key([
            'cpu_time' => 1,
            'execution_time' => 1,
            'memory_usage' => 1,
            'real_memory_usage' => 1,
            'peak_memory_usage' => 1,
            'real_peak_memory_usage' => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        try {
            return new self(
                cpuTime: Statistics::fromArray($data['cpu_time']),
                executionTime: Statistics::fromArray($data['execution_time']),
                memoryUsage: Statistics::fromArray($data['memory_usage']),
                peakMemoryUsage: Statistics::fromArray($data['peak_memory_usage']),
                realMemoryUsage: Statistics::fromArray($data['real_memory_usage']),
                realPeakMemoryUsage: Statistics::fromArray($data['real_peak_memory_usage']),
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a report from the payload', previous: $exception);
        }
    }

    /**
     * @param ('average'|'coef_var'|'iterations'|'maximum'|'median'|'minimum'|'range'|'std_dev'|'sum'|'variance'|null) $property
     *
     * @throws InvalidArgument If the property value is unknown
     *
     * @return ($property is null ? ReportHumanReadable : MetricsHumanReadable)
     */
    public function forHuman(?string $property = null): array /* @phpstan-ignore-line */
    {
        return [/* @phpstan-ignore-line */
            'cpu_time' => $this->cpuTime->forHuman($property),
            'execution_time' => $this->executionTime->forHuman($property),
            'memory_usage' => $this->memoryUsage->forHuman($property),
            'real_memory_usage' => $this->realMemoryUsage->forHuman($property),
            'peak_memory_usage' => $this->peakMemoryUsage->forHuman($property),
            'real_peak_memory_sage' => $this->realPeakMemoryUsage->forHuman($property),
        ];
    }
}
