<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Generator;
use JsonSerializable;
use Throwable;

use function array_diff_key;
use function array_keys;
use function array_map;
use function implode;
use function iterator_to_array;
use function preg_replace;
use function strtolower;

/**
 * @phpstan-type MetricsMap array{
 *     cpu_time: float,
 *     execution_time: float,
 *     memory_usage: float,
 *     real_memory_usage: float,
 *     peak_memory_usage: float,
 *     real_peak_memory_usage: float,
 * }
 * @phpstan-type MetricsHumanReadable array{
 *      cpu_time: string,
 *      execution_time: string,
 *      memory_usage: string,
 *      real_memory_usage: string,
 *      peak_memory_usage: string,
 *      real_peak_memory_usage: string,
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
            cpuTime: $end->cpuUserTime + $end->cpuSystemTime - $start->cpuUserTime - $start->cpuSystemTime,
            executionTime: $excutionTime,
            memoryUsage: $end->memoryUsage - $start->memoryUsage,
            peakMemoryUsage: $end->peakMemoryUsage - $start->peakMemoryUsage,
            realMemoryUsage: $end->realMemoryUsage - $start->realMemoryUsage,
            realPeakMemoryUsage: $end->realPeakMemoryUsage - $start->realPeakMemoryUsage,
        );
    }

    /**
     * @param MetricsMap $data
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
                $data['cpu_time'],
                $data['execution_time'],
                $data['memory_usage'],
                $data['real_memory_usage'],
                $data['peak_memory_usage'],
                $data['real_peak_memory_usage']
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a metrics from the payload', previous: $exception);
        }
    }

    public static function sum(Timeline|Profiler|Result|Span|Metrics ...$items): self
    {
        $sum = self::none();
        foreach (self::yieldFrom(...$items) as $metric) {
            $sum = $sum->add($metric);
        }

        return $sum;
    }

    public static function average(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        $sum = self::none();
        $count = 0;
        foreach (self::yieldFrom(...$metrics) as $metric) {
            $sum = $sum->add($metric);
            $count++;
        }

        return 2 > $count ? $sum : new self(
            cpuTime: $sum->cpuTime / $count,
            executionTime: $sum->executionTime / $count,
            memoryUsage: $sum->memoryUsage / $count,
            peakMemoryUsage: $sum->peakMemoryUsage / $count,
            realMemoryUsage: $sum->realMemoryUsage / $count,
            realPeakMemoryUsage: $sum->realPeakMemoryUsage / $count,
        );
    }

    /**
     * Lazily yields Metrics instances from one or more input objects.
     *
     * This method accepts any combination of the following types:
     * - `Metrics` — yields the object itself.
     * - `Span` — yields the `metrics` property.
     * - `Result` — yields the `metrics` of its `span`.
     * - `Profiler` — yields the `metrics` of each contained `Span`.
     * - `Timeline` — yields the `metrics` of each `Span` returned by `deltas()`.
     *
     * The returned generator is **lazy**, meaning no arrays are built internally,
     * and metrics are yielded as they are iterated.
     *
     * @return Generator<Metrics>
     */
    public static function yieldFrom(Timeline|Profiler|Result|Span|Metrics ...$metrics): Generator
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

    /**
     * @return MetricsMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return MetricsMap
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

        $propertyNormalized = strtolower((string) preg_replace('/[\s_\-]+/', '_', $property));

        return $humans[$propertyNormalized] ?? throw new InvalidArgument('Unknown metrics name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
    }
}
