<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Test;

use Bakame\Stackwatch\AggregatedMetrics;
use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\Metrics;
use PHPUnit\Framework\Assert;
use ValueError;

/**
 * Fluent assertions for Profiler metrics.
 *
 * @method self executionTime()
 * @method self cpuTime()
 * @method self memoryUsage()
 * @method self memoryUsageGrowth()
 * @method self peakMemoryUsage()
 * @method self peakMemoryUsageGrowth()
 * @method self realMemoryUsage()
 * @method self realMemoryUsageGrowth()
 * @method self realPeakMemoryUsage()
 * @method self realPeakMemoryUsageGrowth()
 */
final class MetricsAssert
{
    private ?string $current = null;

    public function __construct(public readonly Metrics|AggregatedMetrics $metrics)
    {
    }

    /**
     * @param array<mixed> $args
     */
    public function __call(string $name, array $args = []): self
    {
        property_exists($this->metrics, $name) || throw new ValueError("Unknown metric: $name");
        $this->current = $name;

        return $this;
    }

    private function getValue(): float|int
    {
        null !== $this->current || throw new MetricNotSelected('No metric selected before assertion.');

        return $this->metrics->{$this->current}; /* @phpstan-ignore-line */
    }

    private function getOtherValue(string $name): float|int
    {
        property_exists($this->metrics, $name) || throw new ValueError("Unknown metric: $name");

        return $this->metrics->{$name}; /* @phpstan-ignore-line */
    }

    private function isDurationMetric(): bool
    {
        return in_array($this->current, ['executionTime', 'cpuTime'], true);
    }

    private function convertUnit(float|int $value, MemoryUnit|DurationUnit|string|null $unit = null): int
    {
        if ($this->isDurationMetric()) {
            $unit ??= DurationUnit::Nanosecond;
            if (is_string($unit)) {
                $unit = DurationUnit::fromUnit($unit);
            }

            $unit instanceof DurationUnit || throw new ValueError('The unit "'.$unit->value.'" is not a duration unit.');

            return $unit->convertToNano($value);
        }

        $unit ??= MemoryUnit::Byte;
        if (is_string($unit)) {
            $unit = MemoryUnit::fromUnit($unit);
        }

        $unit instanceof MemoryUnit || throw new ValueError('The unit "'.$unit->value.'" is not a memory unit.');

        return $unit->convertToBytes($value);
    }

    // ----------------------------
    // Numeric assertions
    // ----------------------------

    public function greaterThan(float|int $min, MemoryUnit|DurationUnit|string|null $unit = null, string $message = ''): self
    {
        $value = $this->getValue();
        Assert::assertGreaterThan($this->convertUnit($min, $unit), $value, '' !== $message ? $message : "Expected $this->current > $min, got $value");
        return $this;
    }

    public function greaterThanOrEqual(float|int $min, MemoryUnit|DurationUnit|string|null $unit = null, string $message = ''): self
    {
        $value = $this->getValue();
        Assert::assertGreaterThanOrEqual($this->convertUnit($min, $unit), $value, '' !== $message ? $message : "Expected $this->current >= $min, got $value");
        return $this;
    }

    public function lessThan(float|int $max, MemoryUnit|DurationUnit|string|null $unit = null, string $message = ''): self
    {
        $value = $this->getValue();
        Assert::assertLessThan($this->convertUnit($max, $unit), $value, '' !== $message ? $message : "Expected $this->current < $max, got $value");

        return $this;
    }

    public function lessThanOrEqual(float|int $max, MemoryUnit|DurationUnit|string|null $unit = null, string $message = ''): self
    {
        $value = $this->getValue();
        Assert::assertLessThanOrEqual($this->convertUnit($max, $unit), $value, '' !== $message ? $message : "Expected $this->current <= $max, got $value");

        return $this;
    }

    public function between(float|int $min, float|int $max, MemoryUnit|DurationUnit|string|null $unit = null, string $message = ''): self
    {
        $value = $this->getValue();
        $minBase = $this->convertUnit($min, $unit);
        $maxBase = $this->convertUnit($max, $unit);

        Assert::assertGreaterThanOrEqual($minBase, $value, '' !== $message ? $message : "Expected $this->current >= $min, got $value");
        Assert::assertLessThanOrEqual($maxBase, $value, '' !== $message ? $message : "Expected $this->current <= $max, got $value");

        return $this;
    }

    // ----------------------------
    // Relative assertions
    // ----------------------------

    public function greaterThanMetric(string $other, string $message = ''): self
    {
        $value = $this->getValue();
        $otherValue = $this->getOtherValue($other);

        Assert::assertGreaterThan(
            $otherValue,
            $value,
            '' !== $message ? $message : "Expected $this->current > $other ($otherValue), got $value"
        );

        return $this;
    }

    public function greaterThanOrEqualMetric(string $other, string $message = ''): self
    {
        $value = $this->getValue();
        $otherValue = $this->getOtherValue($other);

        Assert::assertGreaterThanOrEqual(
            $otherValue,
            $value,
            '' !== $message ? $message : "Expected $this->current >= $other ($otherValue), got $value"
        );

        return $this;
    }

    public function lessThanMetric(string $other, string $message = ''): self
    {
        $value = $this->getValue();
        $otherValue = $this->getOtherValue($other);

        Assert::assertLessThan(
            $otherValue,
            $value,
            '' !== $message ? $message : "Expected $this->current < $other ($otherValue), got $value"
        );

        return $this;
    }

    public function lessThanOrEqualMetric(string $other, string $message = ''): self
    {
        $value = $this->getValue();
        $otherValue = $this->getOtherValue($other);

        Assert::assertLessThanOrEqual(
            $otherValue,
            $value,
            '' !== $message ? $message : "Expected $this->current <= $other ($otherValue), got $value"
        );

        return $this;
    }

    // ----------------------------
    // Bulk checks
    // ----------------------------

    public function allNonNegative(): self
    {
        foreach ($this->metrics->toArray() as $name => $value) {
            Assert::assertGreaterThanOrEqual(0, $value, "Metric [$name] should be non-negative, got $value");
        }

        return $this;
    }

    /**
     * Built-in semantic consistency checks:
     * - executionTime >= cpuTime
     * - peakMemoryUsage >= memoryUsage
     * - realPeakMemoryUsage >= realMemoryUsage
     */
    public function assertConsistency(): self
    {
        Assert::assertGreaterThanOrEqual(
            $this->metrics->cpuTime,
            $this->metrics->executionTime,
            "Expected executionTime >= cpuTime ({$this->metrics->cpuTime}), got {$this->metrics->executionTime}"
        );

        Assert::assertGreaterThanOrEqual(
            $this->metrics->memoryUsage,
            $this->metrics->peakMemoryUsage,
            "Expected peakMemoryUsage >= memoryUsage ({$this->metrics->memoryUsage}), got {$this->metrics->peakMemoryUsage}"
        );

        Assert::assertGreaterThanOrEqual(
            $this->metrics->realMemoryUsage,
            $this->metrics->realPeakMemoryUsage,
            "Expected realPeakMemoryUsage >= realMemoryUsage ({$this->metrics->realMemoryUsage}), got {$this->metrics->realPeakMemoryUsage}"
        );

        return $this;
    }
}
