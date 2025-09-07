<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Generator;
use JsonSerializable;
use Throwable;

use function array_column;
use function array_diff_key;
use function array_keys;
use function array_map;
use function array_shift;
use function count;
use function header;
use function headers_sent;
use function implode;
use function intdiv;
use function iterator_to_array;
use function ob_get_clean;
use function ob_start;
use function preg_replace;
use function strtolower;

/**
 * @phpstan-type MetricsMap array{
 *     cpu_time: float,
 *     execution_time: float,
 *     memory_usage: float,
 *     memory_usage_growth: float,
 *     real_memory_usage: float,
 *     real_memory_usage_growth: float,
 *     peak_memory_usage: float,
 *     peak_memory_usage_growth: float,
 *     real_peak_memory_usage: float,
 *     real_peak_memory_usage_growth: float,
 * }
 * @phpstan-type MetricsHumanReadable array{
 *      cpu_time: string,
 *      execution_time: string,
 *      memory_usage: string,
 *      memory_usage_growth: string,
 *      real_memory_usage: string,
 *      real_memory_usage_growth: string,
 *      peak_memory_usage: string,
 *      peak_memory_usage_growth: string,
 *      real_peak_memory_usage: string,
 *      real_peak_memory_usage_growth: string,
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
        public readonly float $memoryUsageGrowth,
        public readonly float $peakMemoryUsage,
        public readonly float $peakMemoryUsageGrowth,
        public readonly float $realMemoryUsage,
        public readonly float $realMemoryUsageGrowth,
        public readonly float $realPeakMemoryUsage,
        public readonly float $realPeakMemoryUsageGrowth,
    ) {
    }

    public static function none(): self
    {
        return new self(
            cpuTime: 0,
            executionTime: 0,
            memoryUsage: 0,
            memoryUsageGrowth: 0,
            peakMemoryUsage: 0,
            peakMemoryUsageGrowth: 0,
            realMemoryUsage: 0,
            realMemoryUsageGrowth: 0,
            realPeakMemoryUsage: 0,
            realPeakMemoryUsageGrowth: 0,
        );
    }

    public static function fromSnapshots(Snapshot $start, Snapshot $end): self
    {
        $start->isBeforeOrAtSameTime($end) || throw new UnableToProfile('The ending snapshot was taken before the starting snapshot.');

        return new self(
            cpuTime: $end->cpuUserTime + $end->cpuSystemTime - $start->cpuUserTime - $start->cpuSystemTime,
            executionTime: $end->hrtime - $start->hrtime,
            memoryUsage: $end->memoryUsage,
            memoryUsageGrowth: $end->memoryUsage - $start->memoryUsage,
            peakMemoryUsage: $end->peakMemoryUsage,
            peakMemoryUsageGrowth: $end->peakMemoryUsage - $start->peakMemoryUsage,
            realMemoryUsage: $end->realMemoryUsage,
            realMemoryUsageGrowth: $end->realMemoryUsage - $start->realMemoryUsage,
            realPeakMemoryUsage: $end->realPeakMemoryUsage,
            realPeakMemoryUsageGrowth: $end->realPeakMemoryUsage - $start->realPeakMemoryUsage,
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
            'memory_usage_growth' => 1,
            'real_memory_usage' => 1,
            'real_memory_usage_growth' => 1,
            'peak_memory_usage' => 1,
            'peak_memory_usage_growth' => 1,
            'real_peak_memory_usage' => 1,
            'real_peak_memory_usage_growth' => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        try {
            return new self(
                $data['cpu_time'],
                $data['execution_time'],
                $data['memory_usage'],
                $data['memory_usage_growth'],
                $data['real_memory_usage'],
                $data['real_memory_usage_growth'],
                $data['peak_memory_usage'],
                $data['peak_memory_usage_growth'],
                $data['real_peak_memory_usage'],
                $data['real_peak_memory_usage_growth'],
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

    public static function aggregate(AggregationType $type, Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        return match ($type) {
            AggregationType::Average => self::average(...$metrics),
            AggregationType::Median => self::median(...$metrics),
            AggregationType::Sum => self::sum(...$metrics),
            AggregationType::Maximum => self::max(...$metrics),
            AggregationType::Minimum => self::min(...$metrics),
            AggregationType::Range => self::range(...$metrics),
            AggregationType::CoefVar => self::coefVar(...$metrics),
            AggregationType::StdDev => self::stdDev(...$metrics),
            AggregationType::Variance => self::variance(...$metrics),
        };
    }

    public static function median(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        /** @var array<Metrics> $all */
        $all = iterator_to_array(self::yieldFrom(...$metrics));
        $count = count($all);
        if (0 === $count) {
            return self::none();
        }

        if (1 === $count) {
            return $all[0];
        }

        /**
         * @param array<int|float> $values
         */
        $medianFn = static function (array $values): float|int {
            $count = count($values);
            $mid = intdiv($count, 2);
            sort($values, SORT_NUMERIC);
            if (!array_key_exists($mid, $values)) {
                throw new UnableToProfile('Could not calculate median.');
            }

            if (0 !== $count % 2 || !array_key_exists($mid - 1, $values)) {
                return $values[$mid]; /* @phpstan-ignore-line */
            }

            return ($values[$mid - 1] + $values[$mid]) / 2; /* @phpstan-ignore-line */
        };

        return new self(
            cpuTime: $medianFn(array_column($all, 'cpuTime')),
            executionTime: $medianFn(array_column($all, 'executionTime')),
            memoryUsage: $medianFn(array_column($all, 'memoryUsage')),
            memoryUsageGrowth: $medianFn(array_column($all, 'memoryUsageGrowth')),
            peakMemoryUsage: $medianFn(array_column($all, 'peakMemoryUsage')),
            peakMemoryUsageGrowth: $medianFn(array_column($all, 'peakMemoryUsageGrowth')),
            realMemoryUsage: $medianFn(array_column($all, 'realMemoryUsage')),
            realMemoryUsageGrowth: $medianFn(array_column($all, 'realMemoryUsageGrowth')),
            realPeakMemoryUsage: $medianFn(array_column($all, 'realPeakMemoryUsage')),
            realPeakMemoryUsageGrowth: $medianFn(array_column($all, 'realPeakMemoryUsageGrowth')),
        );
    }

    public static function average(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        $cpuTime = 0.0;
        $executionTime = 0.0;
        $memoryUsage = 0;
        $memoryUsageGrowth = 0;
        $peakMemoryUsage = 0;
        $peakMemoryUsageGrowth = 0;
        $realMemoryUsage = 0;
        $realMemoryUsageGrowth = 0;
        $realPeakMemoryUsage = 0;
        $realPeakMemoryUsageGrowth = 0;
        $count = 0;

        foreach (self::yieldFrom(...$metrics) as $metric) {
            $cpuTime += $metric->cpuTime;
            $executionTime += $metric->executionTime;

            $memoryUsage += $metric->memoryUsage;
            $memoryUsageGrowth += $metric->memoryUsageGrowth;
            $peakMemoryUsage += $metric->peakMemoryUsage;
            $peakMemoryUsageGrowth += $metric->peakMemoryUsageGrowth;

            $realMemoryUsage += $metric->realMemoryUsage;
            $realMemoryUsageGrowth += $metric->realMemoryUsageGrowth;
            $realPeakMemoryUsage += $metric->realPeakMemoryUsage;
            $realPeakMemoryUsageGrowth += $metric->realPeakMemoryUsageGrowth;
            ++$count;
        }

        return 0 === $count ? self::none() : new self(
            cpuTime: $cpuTime / $count,
            executionTime: $executionTime / $count,
            memoryUsage: $memoryUsage / $count,
            memoryUsageGrowth: $memoryUsageGrowth / $count,
            peakMemoryUsage: $peakMemoryUsage / $count,
            peakMemoryUsageGrowth: $peakMemoryUsageGrowth / $count,
            realMemoryUsage: $realMemoryUsage / $count,
            realMemoryUsageGrowth: $realMemoryUsageGrowth / $count,
            realPeakMemoryUsage: $realPeakMemoryUsage / $count,
            realPeakMemoryUsageGrowth: $realPeakMemoryUsageGrowth / $count,
        );
    }

    public static function min(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        if ([] === $metrics) {
            return self::none();
        }

        $all = iterator_to_array(self::yieldFrom(...$metrics));
        if (1 === count($all)) {
            return array_shift($all);
        }

        $cpuTime = INF;
        $executionTime = INF;
        $memoryUsage = PHP_INT_MAX;
        $memoryUsageGrowth = PHP_INT_MAX;
        $peakMemoryUsage = PHP_INT_MAX;
        $peakMemoryUsageGrowth = PHP_INT_MAX;
        $realMemoryUsage = PHP_INT_MAX;
        $realMemoryUsageGrowth = PHP_INT_MAX;
        $realPeakMemoryUsage = PHP_INT_MAX;
        $realPeakMemoryUsageGrowth = PHP_INT_MAX;

        foreach ($all as $metric) {
            $cpuTime = min($cpuTime, $metric->cpuTime);
            $executionTime = min($executionTime, $metric->executionTime);
            $memoryUsage = min($memoryUsage, $metric->memoryUsage);
            $memoryUsageGrowth = min($memoryUsageGrowth, $metric->memoryUsageGrowth);
            $peakMemoryUsage = min($peakMemoryUsage, $metric->peakMemoryUsage);
            $peakMemoryUsageGrowth = min($peakMemoryUsageGrowth, $metric->peakMemoryUsageGrowth);
            $realMemoryUsage = min($realMemoryUsage, $metric->realMemoryUsage);
            $realMemoryUsageGrowth = min($realMemoryUsageGrowth, $metric->realMemoryUsageGrowth);
            $realPeakMemoryUsage = min($realPeakMemoryUsage, $metric->realPeakMemoryUsage);
            $realPeakMemoryUsageGrowth = min($realPeakMemoryUsageGrowth, $metric->realPeakMemoryUsageGrowth);
        }

        return new self(
            cpuTime: $cpuTime,
            executionTime: $executionTime,
            memoryUsage: $memoryUsage,
            memoryUsageGrowth: $memoryUsageGrowth,
            peakMemoryUsage: $peakMemoryUsage,
            peakMemoryUsageGrowth: $peakMemoryUsageGrowth,
            realMemoryUsage: $realMemoryUsage,
            realMemoryUsageGrowth: $realMemoryUsageGrowth,
            realPeakMemoryUsage: $realPeakMemoryUsage,
            realPeakMemoryUsageGrowth: $realPeakMemoryUsageGrowth,
        );
    }

    public static function max(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        if ([] === $metrics) {
            return self::none();
        }

        $all = iterator_to_array(self::yieldFrom(...$metrics));
        if (1 === count($all)) {
            return array_shift($all);
        }

        $cpuTime = -INF;
        $executionTime = -INF;
        $memoryUsage = PHP_INT_MIN;
        $memoryUsageGrowth = PHP_INT_MIN;
        $peakMemoryUsage = PHP_INT_MIN;
        $peakMemoryUsageGrowth = PHP_INT_MIN;
        $realMemoryUsage = PHP_INT_MIN;
        $realMemoryUsageGrowth = PHP_INT_MIN;
        $realPeakMemoryUsage = PHP_INT_MIN;
        $realPeakMemoryUsageGrowth = PHP_INT_MIN;

        foreach (self::yieldFrom(...$metrics) as $metric) {
            $cpuTime = max($cpuTime, $metric->cpuTime);
            $executionTime = max($executionTime, $metric->executionTime);
            $memoryUsage = max($memoryUsage, $metric->memoryUsage);
            $memoryUsageGrowth = max($memoryUsageGrowth, $metric->memoryUsageGrowth);
            $peakMemoryUsage = max($peakMemoryUsage, $metric->peakMemoryUsage);
            $peakMemoryUsageGrowth = max($peakMemoryUsageGrowth, $metric->peakMemoryUsageGrowth);
            $realMemoryUsage = max($realMemoryUsage, $metric->realMemoryUsage);
            $realMemoryUsageGrowth = max($realMemoryUsageGrowth, $metric->realMemoryUsageGrowth);
            $realPeakMemoryUsage = max($realPeakMemoryUsage, $metric->realPeakMemoryUsage);
            $realPeakMemoryUsageGrowth = max($realPeakMemoryUsageGrowth, $metric->realPeakMemoryUsageGrowth);
        }

        return new self(
            cpuTime: $cpuTime,
            executionTime: $executionTime,
            memoryUsage: $memoryUsage,
            memoryUsageGrowth: $memoryUsageGrowth,
            peakMemoryUsage: $peakMemoryUsage,
            peakMemoryUsageGrowth: $peakMemoryUsageGrowth,
            realMemoryUsage: $realMemoryUsage,
            realMemoryUsageGrowth: $realMemoryUsageGrowth,
            realPeakMemoryUsage: $realPeakMemoryUsage,
            realPeakMemoryUsageGrowth: $realPeakMemoryUsageGrowth,
        );
    }

    public static function range(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        $min = self::min(...$metrics);
        $max = self::max(...$metrics);

        return new self(
            cpuTime: $max->cpuTime - $min->cpuTime,
            executionTime: $max->executionTime - $min->executionTime,
            memoryUsage: $max->memoryUsage - $min->memoryUsage,
            memoryUsageGrowth: $max->memoryUsageGrowth - $min->memoryUsageGrowth,
            peakMemoryUsage: $max->peakMemoryUsage - $min->peakMemoryUsage,
            peakMemoryUsageGrowth: $max->peakMemoryUsageGrowth - $min->peakMemoryUsageGrowth,
            realMemoryUsage: $max->realMemoryUsage - $min->realMemoryUsage,
            realMemoryUsageGrowth: $max->realMemoryUsageGrowth - $min->realMemoryUsageGrowth,
            realPeakMemoryUsage: $max->realPeakMemoryUsage - $min->realPeakMemoryUsage,
            realPeakMemoryUsageGrowth: $max->realPeakMemoryUsageGrowth - $min->realPeakMemoryUsageGrowth,
        );
    }

    public static function variance(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        $values = self::collectValues(...$metrics);
        if ([] === $values['cpuTime']) {
            return self::none();
        }

        $variance = function (array $arr): float {
            $n = count($arr);
            if ($n < 2) {
                return 0.0;
            }
            $mean = array_sum($arr) / $n;
            $sumSq = 0.0;
            foreach ($arr as $v) {
                $sumSq += ($v - $mean) ** 2; /* @phpstan-ignore-line */
            }
            return $sumSq / ($n - 1); // sample variance
        };

        return new self(
            cpuTime: $variance($values['cpuTime']),
            executionTime: $variance($values['executionTime']),
            memoryUsage: $variance($values['memoryUsage']),
            memoryUsageGrowth: $variance($values['memoryUsageGrowth']),
            peakMemoryUsage: $variance($values['peakMemoryUsage']),
            peakMemoryUsageGrowth: $variance($values['peakMemoryUsageGrowth']),
            realMemoryUsage: $variance($values['realMemoryUsage']),
            realMemoryUsageGrowth: $variance($values['realMemoryUsageGrowth']),
            realPeakMemoryUsage: $variance($values['realPeakMemoryUsage']),
            realPeakMemoryUsageGrowth: $variance($values['realPeakMemoryUsageGrowth']),
        );
    }

    public static function stdDev(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        $values = self::collectValues(...$metrics);
        if ([] === $values['cpuTime']) {
            return self::none();
        }

        $std = function (array $arr): float {
            $n = count($arr);
            if ($n < 2) {
                return 0.0;
            }
            $mean = array_sum($arr) / $n;
            $variance = 0.0;
            foreach ($arr as $v) {
                $variance += ($v - $mean) ** 2; /* @phpstan-ignore-line */
            }
            return sqrt($variance / ($n - 1)); // sample stddev
        };

        return new self(
            cpuTime: $std($values['cpuTime']),
            executionTime: $std($values['executionTime']),
            memoryUsage: $std($values['memoryUsage']),
            memoryUsageGrowth: $std($values['memoryUsageGrowth']),
            peakMemoryUsage: $std($values['peakMemoryUsage']),
            peakMemoryUsageGrowth: $std($values['peakMemoryUsageGrowth']),
            realMemoryUsage: $std($values['realMemoryUsage']),
            realMemoryUsageGrowth: $std($values['realMemoryUsageGrowth']),
            realPeakMemoryUsage: $std($values['realPeakMemoryUsage']),
            realPeakMemoryUsageGrowth: $std($values['realPeakMemoryUsageGrowth']),
        );
    }

    public static function coefVar(Timeline|Profiler|Result|Span|Metrics ...$metrics): self
    {
        $values = self::collectValues(...$metrics);
        if ([] === $values['cpuTime']) {
            return self::none();
        }

        $cv = function (array $arr): float {
            $n = count($arr);
            if ($n < 2) {
                return 0.0;
            }
            $mean = array_sum($arr) / $n;
            if (0.0 === $mean) {
                return 0.0; // avoid division by zero
            }
            $sumSq = 0.0;
            foreach ($arr as $v) {
                $sumSq += ($v - $mean) ** 2; /* @phpstan-ignore-line */
            }
            $stddev = sqrt($sumSq / ($n - 1));
            return $stddev / $mean;
        };

        return new self(
            cpuTime: $cv($values['cpuTime']),
            executionTime: $cv($values['executionTime']),
            memoryUsage: $cv($values['memoryUsage']),
            memoryUsageGrowth: $cv($values['memoryUsageGrowth']),
            peakMemoryUsage: $cv($values['peakMemoryUsage']),
            peakMemoryUsageGrowth: $cv($values['peakMemoryUsageGrowth']),
            realMemoryUsage: $cv($values['realMemoryUsage']),
            realMemoryUsageGrowth: $cv($values['realMemoryUsageGrowth']),
            realPeakMemoryUsage: $cv($values['realPeakMemoryUsage']),
            realPeakMemoryUsageGrowth: $cv($values['realPeakMemoryUsageGrowth']),
        );
    }

    /**
     * Small helper to avoid repeating collection logic.
     *
     * @return array{
     *     cpuTime: list<int|float>,
     *     executionTime: list<int|float>,
     *     memoryUsage: list<int|float>,
     *     memoryUsageGrowth: list<int|float>,
     *     peakMemoryUsage: list<int|float>,
     *     peakMemoryUsageGrowth: list<int|float>,
     *     realMemoryUsage: list<int|float>,
     *     realMemoryUsageGrowth: list<int|float>,
     *     realPeakMemoryUsage: list<int|float>,
     *     realPeakMemoryUsageGrowth: list<int|float>,
     * }
     */
    private static function collectValues(Timeline|Profiler|Result|Span|Metrics ...$metrics): array
    {
        $values = [
            'cpuTime' => [],
            'executionTime' => [],
            'memoryUsage' => [],
            'memoryUsageGrowth' => [],
            'peakMemoryUsage' => [],
            'peakMemoryUsageGrowth' => [],
            'realMemoryUsage' => [],
            'realMemoryUsageGrowth' => [],
            'realPeakMemoryUsage' => [],
            'realPeakMemoryUsageGrowth' => [],
        ];

        foreach (self::yieldFrom(...$metrics) as $metric) {
            $values['cpuTime'][] = $metric->cpuTime;
            $values['executionTime'][] = $metric->executionTime;
            $values['memoryUsage'][] = $metric->memoryUsage;
            $values['memoryUsageGrowth'][] = $metric->memoryUsageGrowth;
            $values['peakMemoryUsage'][] = $metric->peakMemoryUsage;
            $values['peakMemoryUsageGrowth'][] = $metric->peakMemoryUsageGrowth;
            $values['realMemoryUsage'][] = $metric->realMemoryUsage;
            $values['realMemoryUsageGrowth'][] = $metric->realMemoryUsageGrowth;
            $values['realPeakMemoryUsage'][] = $metric->realPeakMemoryUsage;
            $values['realPeakMemoryUsageGrowth'][] = $metric->realPeakMemoryUsageGrowth;
        }

        return $values;
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
            memoryUsage: max($this->memoryUsage, $metric->memoryUsage),
            memoryUsageGrowth: $this->memoryUsageGrowth + $metric->memoryUsageGrowth,
            peakMemoryUsage: max($this->peakMemoryUsage, $metric->peakMemoryUsage),
            peakMemoryUsageGrowth: $this->peakMemoryUsageGrowth + $metric->peakMemoryUsageGrowth,
            realMemoryUsage: max($this->realMemoryUsage, $metric->realMemoryUsage),
            realMemoryUsageGrowth: $this->realMemoryUsageGrowth + $metric->realMemoryUsageGrowth,
            realPeakMemoryUsage: max($this->realPeakMemoryUsage, $metric->realPeakMemoryUsage),
            realPeakMemoryUsageGrowth: $this->realPeakMemoryUsageGrowth + $metric->realPeakMemoryUsageGrowth,
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
            'memory_usage_growth' => $this->memoryUsageGrowth,
            'real_memory_usage' => $this->realMemoryUsage,
            'real_memory_usage_growth' => $this->realMemoryUsageGrowth,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'peak_memory_usage_growth' => $this->peakMemoryUsageGrowth,
            'real_peak_memory_usage' => $this->realPeakMemoryUsage,
            'real_peak_memory_usage_growth' => $this->realPeakMemoryUsageGrowth,
        ];
    }

    /**
     * @return MetricsHumanReadable
     */
    public function toHuman(): array
    {
        return [
            'cpu_time' => DurationUnit::format($this->cpuTime, 3),
            'execution_time' => DurationUnit::format($this->executionTime, 3),
            'memory_usage' => MemoryUnit::format($this->memoryUsage, 1),
            'memory_usage_growth' =>  MemoryUnit::format($this->memoryUsageGrowth, 1),
            'real_memory_usage' => MemoryUnit::format($this->realMemoryUsage, 1),
            'real_memory_usage_growth' => MemoryUnit::format($this->realMemoryUsageGrowth, 1),
            'peak_memory_usage' => MemoryUnit::format($this->peakMemoryUsage, 1),
            'peak_memory_usage_growth' => MemoryUnit::format($this->peakMemoryUsageGrowth, 1),
            'real_peak_memory_usage' => MemoryUnit::format($this->realPeakMemoryUsage, 1),
            'real_peak_memory_usage_growth' => MemoryUnit::format($this->realPeakMemoryUsageGrowth, 1),
        ];
    }

    /**
     * Returns a human-readable version of a property.
     *
     * @param non-empty-string $property
     *
     * @throws InvalidArgument if the property is unknown
     */
    public function human(string $property): string
    {
        $humans = $this->toHuman();
        $propertyNormalized = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property));

        return $humans[$propertyNormalized] ?? throw new InvalidArgument('Unknown metrics name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
    }

    public function dump(?AggregationType $type = null): self
    {
        (new Renderer())->renderAggregatedMetrics($this, $type);

        return $this;
    }

    public function dd(?AggregationType $type = null): never
    {
        ob_start();
        self::dump($type);
        $dumpOutput = ob_get_clean();

        if (Environment::current()->isCli()) {
            echo $dumpOutput;
            exit(1);
        }

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $dumpOutput;
        exit(1);
    }
}
