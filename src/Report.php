<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;
use Throwable;

use function array_diff_key;
use function array_keys;
use function array_map;
use function implode;

/**
 * @phpstan-import-type StatsMap from Statistics
 * @phpstan-type ReportMap array{
 *      cpu_time: StatsMap,
 *      execution_time: StatsMap,
 *      memory_usage: StatsMap,
 *      real_memory_usage: StatsMap,
 *      peak_memory_usage: StatsMap,
 *      real_peak_memory_usage: StatsMap,
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

    public static function fromMetrics(Metrics ...$metrics): self
    {
        $statistics = [
            'cpu_time' => [
                'unit' => Unit::Nanoseconds,
                'data' => [],
            ],
            'execution_time' => [
                'unit' => Unit::Nanoseconds,
                'data' => [],
            ],
            'memory_usage' => [
                'unit' => Unit::Bytes,
                'data' => [],
            ],
            'peak_memory_usage' => [
                'unit' => Unit::Bytes,
                'data' => [],
            ],
            'real_memory_usage' => [
                'unit' => Unit::Bytes,
                'data' => [],
            ],
            'real_peak_memory_usage' => [
                'unit' => Unit::Bytes,
                'data' => [],
            ],
        ];

        foreach ($metrics as $metric) {
            $statistics['cpu_time']['data'][] = $metric->cpuTime;
            $statistics['execution_time']['data'][] = $metric->executionTime;
            $statistics['memory_usage']['data'][] = $metric->memoryUsage;
            $statistics['peak_memory_usage']['data'][] = $metric->peakMemoryUsage;
            $statistics['real_memory_usage']['data'][] = $metric->realMemoryUsage;
            $statistics['real_peak_memory_usage']['data'][] = $metric->realPeakMemoryUsage;
        }

        $data = array_map(
            fn (array $values): Statistics => Statistics::fromValues($values['unit'], $values['data']),
            $statistics
        );

        return new self(
            cpuTime: $data['cpu_time'],
            executionTime: $data['execution_time'],
            memoryUsage: $data['memory_usage'],
            peakMemoryUsage: $data['peak_memory_usage'],
            realMemoryUsage: $data['real_memory_usage'],
            realPeakMemoryUsage: $data['real_peak_memory_usage'],
        );
    }

    public static function none(): self
    {
        return new self(
            cpuTime: Statistics::none(Unit::Nanoseconds),
            executionTime: Statistics::none(Unit::Nanoseconds),
            memoryUsage: Statistics::none(Unit::Bytes),
            peakMemoryUsage: Statistics::none(Unit::Bytes),
            realMemoryUsage: Statistics::none(Unit::Bytes),
            realPeakMemoryUsage: Statistics::none(Unit::Bytes),
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
            $cpuTime = Statistics::fromArray($data['cpu_time']);
            $executionTime = Statistics::fromArray($data['execution_time']);
            $memoryUsage = Statistics::fromArray($data['memory_usage']);
            $realMemoryUsage = Statistics::fromArray($data['real_memory_usage']);
            $peakMemoryUsage = Statistics::fromArray($data['peak_memory_usage']);
            $realPeakMemoryUsage = Statistics::fromArray($data['real_peak_memory_usage']);
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a metrics from the payload', previous: $exception);
        }

        return new self(
            $cpuTime,
            $executionTime,
            $memoryUsage,
            $realMemoryUsage,
            $peakMemoryUsage,
            $realPeakMemoryUsage,
        );
    }
}
