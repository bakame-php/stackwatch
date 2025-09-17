<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Exporter\CallbackDumper;
use Bakame\Stackwatch\Exporter\ViewExporter;
use Generator;
use JsonSerializable;
use Throwable;

use function array_column;
use function array_diff_key;
use function array_keys;
use function array_map;
use function array_unique;
use function implode;
use function iterator_to_array;

/**
 * @phpstan-import-type StatsMap from Statistics
 * @phpstan-import-type StatsHumanReadable from Statistics
 * @phpstan-import-type MetricsHumanReadable from Metrics
 * @phpstan-type ReportMap array{
 *      cpu_time: StatsMap,
 *      execution_time: StatsMap,
 *      memory_usage: StatsMap,
 *      memory_usage_growth: StatsMap,
 *      real_memory_usage: StatsMap,
 *      real_memory_usage_growth: StatsMap,
 *      peak_memory_usage: StatsMap,
 *      peak_memory_usage_growth: StatsMap,
 *      real_peak_memory_usage: StatsMap,
 *      real_peak_memory_usage_growth: StatsMap,
 *  }
 * @phpstan-type ReportHumanReadable array{
 *       cpu_time: StatsHumanReadable,
 *       execution_time: StatsHumanReadable,
 *       memory_usage: StatsHumanReadable,
 *       memory_usage_growth: StatsHumanReadable,
 *       real_memory_usage: StatsHumanReadable,
 *       real_memory_usage_growth: StatsHumanReadable,
 *       peak_memory_usage: StatsHumanReadable,
 *       peak_memory_usage_growth: StatsHumanReadable,
 *       real_peak_memory_usage: StatsHumanReadable,
 *       real_peak_memory_usage_growth: StatsHumanReadable,
 *  }
 */
final class Report implements JsonSerializable
{
    private readonly int $iterations;

    /**
     * @var array<AggregationType::value, AggregatedMetrics>
     */
    private array $columns = [];

    public function __construct(
        private readonly Statistics $cpuTime,
        private readonly Statistics $executionTime,
        private readonly Statistics $memoryUsage,
        private readonly Statistics $memoryUsageGrowth,
        private readonly Statistics $peakMemoryUsage,
        private readonly Statistics $peakMemoryUsageGrowth,
        private readonly Statistics $realMemoryUsage,
        private readonly Statistics $realMemoryUsageGrowth,
        private readonly Statistics $realPeakMemoryUsage,
        private readonly Statistics $realPeakMemoryUsageGrowth,
    ) {
        MetricType::CpuTime === $this->cpuTime->type || throw new InvalidArgument('Invalid cpu time statistics specified');
        MetricType::ExecutionTime === $this->executionTime->type || throw new InvalidArgument('Invalid execution time statistics specified');
        MetricType::MemoryUsage === $this->memoryUsage->type || throw new InvalidArgument('Invalid memory usage statistics specified');
        MetricType::MemoryUsageGrowth === $this->memoryUsageGrowth->type || throw new InvalidArgument('Invalid memory usage statistics specified');
        MetricType::PeakMemoryUsage === $this->peakMemoryUsage->type || throw new InvalidArgument('Invalid peak memory usage statistics specified');
        MetricType::PeakMemoryUsageGrowth === $this->peakMemoryUsageGrowth->type || throw new InvalidArgument('Invalid memory usage statistics specified');
        MetricType::RealMemoryUsage === $this->realMemoryUsage->type || throw new InvalidArgument('Invalid real memory usage statistics specified');
        MetricType::RealMemoryUsageGrowth === $this->realMemoryUsageGrowth->type || throw new InvalidArgument('Invalid real memory usage statistics specified');
        MetricType::RealPeakMemoryUsage === $this->realPeakMemoryUsage->type || throw new InvalidArgument('Invalid real peak memory usage statistics specified');
        MetricType::RealPeakMemoryUsageGrowth === $this->realPeakMemoryUsageGrowth->type || throw new InvalidArgument('Invalid real peak memory usage statistics specified');

        $iterations = array_unique(array_column($this->toArray(), 'iterations'));
        1 === count($iterations) || throw new InvalidArgument('Invalid iterations specified');
        $this->iterations = $iterations[0];
    }

    public static function fromMetrics(Timeline|Profiler|Span|Metrics ...$metrics): self
    {
        $statistics = [
            MetricType::CpuTime->value => [],
            MetricType::ExecutionTime->value => [],
            MetricType::MemoryUsage->value => [],
            MetricType::MemoryUsageGrowth->value => [],
            MetricType::RealMemoryUsage->value => [],
            MetricType::RealMemoryUsageGrowth->value => [],
            MetricType::PeakMemoryUsage->value => [],
            MetricType::PeakMemoryUsageGrowth->value => [],
            MetricType::RealPeakMemoryUsage->value => [],
            MetricType::RealPeakMemoryUsageGrowth->value => [],
        ];

        foreach (self::yieldFrom(...$metrics) as $metric) {
            $statistics[MetricType::CpuTime->value][] = $metric->cpuTime;
            $statistics[MetricType::ExecutionTime->value][] = $metric->executionTime;
            $statistics[MetricType::MemoryUsage->value][] = $metric->memoryUsage;
            $statistics[MetricType::MemoryUsageGrowth->value][] = $metric->memoryUsageGrowth;
            $statistics[MetricType::PeakMemoryUsage->value][] = $metric->peakMemoryUsage;
            $statistics[MetricType::PeakMemoryUsageGrowth->value][] = $metric->peakMemoryUsageGrowth;
            $statistics[MetricType::RealMemoryUsage->value][] = $metric->realMemoryUsage;
            $statistics[MetricType::RealMemoryUsageGrowth->value][] = $metric->realMemoryUsageGrowth;
            $statistics[MetricType::RealPeakMemoryUsage->value][] = $metric->realPeakMemoryUsage;
            $statistics[MetricType::RealPeakMemoryUsageGrowth->value][] = $metric->realPeakMemoryUsageGrowth;
        }

        return new self(
            cpuTime: Statistics::fromValues(MetricType::CpuTime, $statistics[MetricType::CpuTime->value]),
            executionTime: Statistics::fromValues(MetricType::ExecutionTime, $statistics[MetricType::ExecutionTime->value]),
            memoryUsage: Statistics::fromValues(MetricType::MemoryUsage, $statistics[MetricType::MemoryUsage->value]),
            memoryUsageGrowth: Statistics::fromValues(MetricType::MemoryUsageGrowth, $statistics[MetricType::MemoryUsageGrowth->value]),
            peakMemoryUsage: Statistics::fromValues(MetricType::PeakMemoryUsage, $statistics[MetricType::PeakMemoryUsage->value]),
            peakMemoryUsageGrowth: Statistics::fromValues(MetricType::PeakMemoryUsageGrowth, $statistics[MetricType::PeakMemoryUsageGrowth->value]),
            realMemoryUsage: Statistics::fromValues(MetricType::RealMemoryUsage, $statistics[MetricType::RealMemoryUsage->value]),
            realMemoryUsageGrowth: Statistics::fromValues(MetricType::RealMemoryUsageGrowth, $statistics[MetricType::RealMemoryUsageGrowth->value]),
            realPeakMemoryUsage: Statistics::fromValues(MetricType::RealPeakMemoryUsage, $statistics[MetricType::RealPeakMemoryUsage->value]),
            realPeakMemoryUsageGrowth: Statistics::fromValues(MetricType::RealPeakMemoryUsageGrowth, $statistics[MetricType::RealPeakMemoryUsageGrowth->value]),
        );
    }

    /**
     * Lazily yields Metrics instances from one or more input objects.
     *
     * This method accepts any combination of the following types:
     * - `Metrics` — yields the object itself.
     * - `Span` — yields the `metrics` property.
     * - `Result` — yields the `metrics` of its `span`.
     * - `SpanAggregator` — yields the `metrics` of each contained `Span`.
     * - `Timeline` — yields the `metrics` of each `Span` returned by `deltas()`.
     *
     * The returned generator is **lazy**, meaning no arrays are built internally,
     * and metrics are yielded as they are iterated.
     *
     * @return Generator<Metrics>
     */
    private static function yieldFrom(Timeline|Profiler|Result|Span|Metrics ...$metrics): Generator
    {
        foreach ($metrics as $metric) {
            yield from match ($metric::class) {
                Metrics::class  => [$metric],
                Span::class => [$metric->metrics],
                Result::class => [$metric->span->metrics],
                Profiler::class => array_map(fn (Span $span) => $span->metrics, iterator_to_array($metric, false)),
                Timeline::class => array_map(fn (Span $span) => $span->metrics, iterator_to_array($metric->deltas(), false)),
            };
        }
    }

    /**
     * @return array{
     *     cpu_time: Statistics,
     *     execution_time: Statistics,
     *     memory_usage: Statistics,
     *     memory_usage_growth: Statistics,
     *     real_memory_usage: Statistics,
     *     real_memory_usage_growth: Statistics,
     *     peak_memory_usage: Statistics,
     *     peak_memory_usage_growth: Statistics,
     *     real_peak_memory_usage: Statistics,
     *     real_peak_memory_usage_growth: Statistics,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            MetricType::CpuTime->value => $this->cpuTime,
            MetricType::ExecutionTime->value => $this->executionTime,
            MetricType::MemoryUsage->value => $this->memoryUsage,
            MetricType::MemoryUsageGrowth->value => $this->memoryUsageGrowth,
            MetricType::RealMemoryUsage->value => $this->realMemoryUsage,
            MetricType::RealMemoryUsageGrowth->value => $this->realMemoryUsageGrowth,
            MetricType::PeakMemoryUsage->value => $this->peakMemoryUsage,
            MetricType::PeakMemoryUsageGrowth->value => $this->peakMemoryUsageGrowth,
            MetricType::RealPeakMemoryUsage->value => $this->realPeakMemoryUsage,
            MetricType::RealPeakMemoryUsageGrowth->value => $this->realPeakMemoryUsageGrowth,
        ];
    }

    /**
     * @return ReportMap
     */
    public function toArray(): array
    {
        return array_map(fn (Statistics $statistics): array => $statistics->toArray(), $this->jsonSerialize());
    }

    public function column(AggregationType $type): AggregatedMetrics
    {
        return $this->columns[$type->value] ??= new AggregatedMetrics(
            type: $type,
            iterations: $this->cpuTime->iterations,
            cpuTime: $this->cpuTime->toArray()[$type->value],
            executionTime: $this->executionTime->toArray()[$type->value],
            memoryUsage: $this->memoryUsage->toArray()[$type->value],
            memoryUsageGrowth: $this->memoryUsageGrowth->toArray()[$type->value],
            peakMemoryUsage: $this->realPeakMemoryUsage->toArray()[$type->value],
            peakMemoryUsageGrowth: $this->realPeakMemoryUsageGrowth->toArray()[$type->value],
            realMemoryUsage: $this->realMemoryUsage->toArray()[$type->value],
            realMemoryUsageGrowth: $this->realMemoryUsageGrowth->toArray()[$type->value],
            realPeakMemoryUsage: $this->realPeakMemoryUsage->toArray()[$type->value],
            realPeakMemoryUsageGrowth: $this->realPeakMemoryUsageGrowth->toArray()[$type->value],
        );
    }

    public function row(MetricType $type): Statistics
    {
        return $this->jsonSerialize()[$type->value] ?? throw new InvalidArgument('Unknown or unsupported metric type `'.$type->value.'`.');
    }

    public function iterations(): int
    {
        return $this->iterations;
    }

    public static function none(): self
    {
        return new self(
            cpuTime: Statistics::none(MetricType::CpuTime),
            executionTime: Statistics::none(MetricType::ExecutionTime),
            memoryUsage: Statistics::none(MetricType::MemoryUsage),
            memoryUsageGrowth: Statistics::none(MetricType::MemoryUsageGrowth),
            peakMemoryUsage: Statistics::none(MetricType::PeakMemoryUsage),
            peakMemoryUsageGrowth: Statistics::none(MetricType::PeakMemoryUsageGrowth),
            realMemoryUsage: Statistics::none(MetricType::RealMemoryUsage),
            realMemoryUsageGrowth: Statistics::none(MetricType::RealMemoryUsageGrowth),
            realPeakMemoryUsage: Statistics::none(MetricType::RealPeakMemoryUsage),
            realPeakMemoryUsageGrowth: Statistics::none(MetricType::RealPeakMemoryUsageGrowth),
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
            MetricType::CpuTime->value => 1,
            MetricType::ExecutionTime->value => 1,
            MetricType::MemoryUsage->value => 1,
            MetricType::MemoryUsageGrowth->value => 1,
            MetricType::RealMemoryUsage->value => 1,
            MetricType::RealMemoryUsageGrowth->value => 1,
            MetricType::PeakMemoryUsage->value => 1,
            MetricType::PeakMemoryUsageGrowth->value => 1,
            MetricType::RealPeakMemoryUsage->value => 1,
            MetricType::RealPeakMemoryUsageGrowth->value => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        try {
            return new self(
                cpuTime: Statistics::fromArray($data[MetricType::CpuTime->value]),
                executionTime: Statistics::fromArray($data[MetricType::ExecutionTime->value]),
                memoryUsage: Statistics::fromArray($data[MetricType::MemoryUsage->value]),
                memoryUsageGrowth: Statistics::fromArray($data[MetricType::MemoryUsageGrowth->value]),
                peakMemoryUsage: Statistics::fromArray($data[MetricType::PeakMemoryUsage->value]),
                peakMemoryUsageGrowth: Statistics::fromArray($data[MetricType::PeakMemoryUsageGrowth->value]),
                realMemoryUsage: Statistics::fromArray($data[MetricType::RealMemoryUsage->value]),
                realMemoryUsageGrowth: Statistics::fromArray($data[MetricType::RealMemoryUsageGrowth->value]),
                realPeakMemoryUsage: Statistics::fromArray($data[MetricType::RealPeakMemoryUsage->value]),
                realPeakMemoryUsageGrowth: Statistics::fromArray($data[MetricType::RealPeakMemoryUsageGrowth->value]),
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a report from the payload', previous: $exception);
        }
    }

    /**
     * @return ReportHumanReadable
     */
    public function toHuman(): array
    {
        return [
            MetricType::CpuTime->value => $this->cpuTime->toHuman(),
            MetricType::ExecutionTime->value => $this->executionTime->toHuman(),
            MetricType::MemoryUsage->value => $this->memoryUsage->toHuman(),
            MetricType::MemoryUsageGrowth->value => $this->memoryUsageGrowth->toHuman(),
            MetricType::RealMemoryUsage->value => $this->realMemoryUsage->toHuman(),
            MetricType::RealMemoryUsageGrowth->value => $this->realMemoryUsageGrowth->toHuman(),
            MetricType::PeakMemoryUsage->value => $this->peakMemoryUsage->toHuman(),
            MetricType::PeakMemoryUsageGrowth->value => $this->peakMemoryUsageGrowth->toHuman(),
            MetricType::RealPeakMemoryUsage->value => $this->realPeakMemoryUsage->toHuman(),
            MetricType::RealPeakMemoryUsageGrowth->value => $this->realPeakMemoryUsageGrowth->toHuman(),
        ];
    }

    /**
     * @throws InvalidArgument if the property is unknown or unsupported
     *
     * @return MetricsHumanReadable
     */
    public function human(string $property): array
    {
        return [
            MetricType::CpuTime->value => $this->cpuTime->human($property),
            MetricType::ExecutionTime->value => $this->executionTime->human($property),
            MetricType::MemoryUsage->value => $this->memoryUsage->human($property),
            MetricType::MemoryUsageGrowth->value => $this->memoryUsageGrowth->human($property),
            MetricType::RealMemoryUsage->value => $this->realMemoryUsage->human($property),
            MetricType::RealMemoryUsageGrowth->value => $this->realMemoryUsageGrowth->human($property),
            MetricType::PeakMemoryUsage->value => $this->peakMemoryUsage->human($property),
            MetricType::PeakMemoryUsageGrowth->value => $this->peakMemoryUsageGrowth->human($property),
            MetricType::RealPeakMemoryUsage->value => $this->realPeakMemoryUsage->human($property),
            MetricType::RealPeakMemoryUsageGrowth->value => $this->realPeakMemoryUsageGrowth->human($property),
        ];
    }

    public function dump(): self
    {
        (new ViewExporter())->exportReport($this);

        return $this;
    }

    public function dd(): never
    {
        CallbackDumper::dd($this->dump(...));
    }
}
