<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Exporter\CallbackDumper;
use Bakame\Stackwatch\Exporter\Translator;
use Bakame\Stackwatch\Exporter\ViewExporter;
use JsonSerializable;
use Throwable;

use function number_format;

/**
 * @phpstan-type AggregatedMetricsMap array{
 *       type: string,
 *       iterations: int,
 *       cpu_time: float,
 *       execution_time: float,
 *       memory_usage: float,
 *       memory_usage_growth: float,
 *       real_memory_usage: float,
 *       real_memory_usage_growth: float,
 *       peak_memory_usage: float,
 *       peak_memory_usage_growth: float,
 *       real_peak_memory_usage: float,
 *       real_peak_memory_usage_growth: float,
 * }
 * @phpstan-type AggregatedMetricsHumanReadable array{
 *        type: string,
 *        iterations: string,
 *        cpu_time: string,
 *        execution_time: string,
 *        memory_usage: string,
 *        memory_usage_growth: string,
 *        real_memory_usage: string,
 *        real_memory_usage_growth: string,
 *        peak_memory_usage: string,
 *        peak_memory_usage_growth: string,
 *        real_peak_memory_usage: string,
 *        real_peak_memory_usage_growth: string,
 * }
 */
final class AggregatedMetrics implements JsonSerializable
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
        public readonly AggregationType $type,
        public readonly int $iterations,
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

    public static function none(AggregationType $type): self
    {
        return new self(
            type: $type,
            iterations: 0,
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

    /**
     * @param AggregatedMetricsMap $data
     *
     * @throws InvalidArgument
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key([
            'type' => 1,
            'iterations' => 1,
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
                type: AggregationType::from($data['type']),
                iterations: (int) $data['iterations'],
                cpuTime: $data[MetricType::CpuTime->value],
                executionTime: $data[MetricType::ExecutionTime->value],
                memoryUsage: $data[MetricType::MemoryUsage->value],
                memoryUsageGrowth: $data[MetricType::MemoryUsageGrowth->value],
                peakMemoryUsage: $data[MetricType::PeakMemoryUsage->value],
                peakMemoryUsageGrowth: $data[MetricType::PeakMemoryUsageGrowth->value],
                realMemoryUsage: $data[MetricType::RealMemoryUsage->value],
                realMemoryUsageGrowth: $data[MetricType::RealMemoryUsageGrowth->value],
                realPeakMemoryUsage: $data[MetricType::RealPeakMemoryUsage->value],
                realPeakMemoryUsageGrowth: $data[MetricType::RealPeakMemoryUsageGrowth->value],
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a metrics from the payload', previous: $exception);
        }
    }

    /**
     * @return AggregatedMetricsMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return AggregatedMetricsMap
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'iterations' => $this->iterations,
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
     * @return AggregatedMetricsHumanReadable
     */
    public function toHuman(): array
    {
        $humanType = (new Translator())->translate($this->type->value);
        $iterations = (string) $this->iterations;

        return match ($this->type) {
            AggregationType::Average,
            AggregationType::Range,
            AggregationType::Sum,
            AggregationType::Maximum,
            AggregationType::Minimum,
            AggregationType::Median,
            AggregationType::StdDev => [
                'type' => $humanType,
                'iterations' => $iterations,
                MetricType::CpuTime->value => DurationUnit::format($this->cpuTime, 3),
                MetricType::ExecutionTime->value => DurationUnit::format($this->executionTime, 3),
                MetricType::MemoryUsage->value => MemoryUnit::format($this->memoryUsage, 1),
                MetricType::MemoryUsageGrowth->value =>  MemoryUnit::format($this->memoryUsageGrowth, 1),
                MetricType::RealMemoryUsage->value => MemoryUnit::format($this->realMemoryUsage, 1),
                MetricType::RealMemoryUsageGrowth->value => MemoryUnit::format($this->realMemoryUsageGrowth, 1),
                MetricType::PeakMemoryUsage->value => MemoryUnit::format($this->peakMemoryUsage, 1),
                MetricType::PeakMemoryUsageGrowth->value => MemoryUnit::format($this->peakMemoryUsageGrowth, 1),
                MetricType::RealPeakMemoryUsage->value => MemoryUnit::format($this->realPeakMemoryUsage, 1),
                MetricType::RealPeakMemoryUsageGrowth->value => MemoryUnit::format($this->realPeakMemoryUsageGrowth, 1),
            ],
            AggregationType::Variance => [
                'type' => $humanType,
                'iterations' => $iterations,
                MetricType::CpuTime->value => DurationUnit::formatSquared($this->cpuTime, 3),
                MetricType::ExecutionTime->value => DurationUnit::formatSquared($this->executionTime, 3),
                MetricType::MemoryUsage->value => MemoryUnit::formatSquared($this->memoryUsage, 1),
                MetricType::MemoryUsageGrowth->value =>  MemoryUnit::formatSquared($this->memoryUsageGrowth, 1),
                MetricType::RealMemoryUsage->value => MemoryUnit::formatSquared($this->realMemoryUsage, 1),
                MetricType::RealMemoryUsageGrowth->value => MemoryUnit::formatSquared($this->realMemoryUsageGrowth, 1),
                MetricType::PeakMemoryUsage->value => MemoryUnit::formatSquared($this->peakMemoryUsage, 1),
                MetricType::PeakMemoryUsageGrowth->value => MemoryUnit::formatSquared($this->peakMemoryUsageGrowth, 1),
                MetricType::RealPeakMemoryUsage->value => MemoryUnit::formatSquared($this->realPeakMemoryUsage, 1),
                MetricType::RealPeakMemoryUsageGrowth->value => MemoryUnit::formatSquared($this->realPeakMemoryUsageGrowth, 1),
            ],
            AggregationType::CoefVar => [
                'type' => $humanType,
                'iterations' => $iterations,
                MetricType::CpuTime->value => number_format($this->cpuTime * 100, 4).' %',
                MetricType::ExecutionTime->value =>  number_format($this->executionTime * 100, 4).' %',
                MetricType::MemoryUsage->value => number_format($this->memoryUsage * 100, 4).' %',
                MetricType::MemoryUsageGrowth->value =>   number_format($this->memoryUsageGrowth * 100, 4).' %',
                MetricType::RealMemoryUsage->value =>  number_format($this->realMemoryUsage * 100, 4).' %',
                MetricType::RealMemoryUsageGrowth->value =>  number_format($this->realMemoryUsageGrowth * 100, 4).' %',
                MetricType::PeakMemoryUsage->value =>  number_format($this->peakMemoryUsage * 100, 4).' %',
                MetricType::PeakMemoryUsageGrowth->value =>  number_format($this->peakMemoryUsageGrowth * 100, 4).' %',
                MetricType::RealPeakMemoryUsage->value =>  number_format($this->realPeakMemoryUsage * 100, 4).' %',
                MetricType::RealPeakMemoryUsageGrowth->value =>  number_format($this->realPeakMemoryUsageGrowth * 100, 4).' %',
            ],
        };
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

    public function dump(): self
    {
        (new ViewExporter())->exportMetrics($this);

        return $this;
    }

    public function dd(): never
    {
        CallbackDumper::dd(fn () => $this->dump());
    }
}
