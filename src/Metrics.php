<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;
use Throwable;

use function array_diff_key;
use function array_keys;
use function implode;
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
        (new ViewExporter())->exportMetrics($this, $type);

        return $this;
    }

    public function dd(?AggregationType $type = null): never
    {
        CallbackDumper::dd(fn () => $this->dump($type));
    }
}
