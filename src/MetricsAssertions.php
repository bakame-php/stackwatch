<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

trait MetricsAssertions
{
    /** @var int<1, max> */
    private int $metricsIterations = 1;
    /** @var int<0, max> */
    private int $metricsWarmup = 0;
    private AggregationType $metricsType = AggregationType::Average;

    /**
     * Set the number of iterations (fluent).
     *
     * @param int<1, max> $iterations
     */
    public function withIterations(int $iterations): self
    {
        $this->metricsIterations = $iterations;

        return $this;
    }

    /**
     * Set the number of iterations (fluent).
     *
     * @param int<1, max> $iterations
     */
    public function iter(int $iterations): self
    {
        return $this->withIterations($iterations);
    }

    /**
     * Set the number of warmup runs (fluent).
     *
     * @param int<0, max> $warmup
     */
    public function withWarmup(int $warmup): self
    {
        $this->metricsWarmup = $warmup;

        return $this;
    }

    /**
     * Set the number of warmup runs (fluent).
     *
     * @param int<0, max> $warmup
     */
    public function warmup(int $warmup): self
    {
        return $this->withWarmup($warmup);
    }

    /**
     * Set the aggregation type (fluent).
     */
    public function withAggregation(?AggregationType $type): self
    {
        $this->metricsType = $type ?? AggregationType::Average;

        return $this;
    }

    // ------------------------------------------------
    // Aggregation presets
    // ------------------------------------------------

    public function aggAverage(): self
    {
        return $this->withAggregation(AggregationType::Average);
    }

    public function aggMedian(): self
    {
        return $this->withAggregation(AggregationType::Median);
    }

    public function aggMin(): self
    {
        return $this->withAggregation(AggregationType::Minimum);
    }

    public function aggMax(): self
    {
        return $this->withAggregation(AggregationType::Maximum);
    }

    public function aggSum(): self
    {
        return $this->withAggregation(AggregationType::Sum);
    }

    /**
     * Profile a callback and return a fluent MetricsAssert object.
     */
    public function assertMetrics(callable $callback): MetricsAssert
    {
        $metrics = Stack::metrics(
            $callback,
            $this->metricsIterations,
            $this->metricsWarmup,
            $this->metricsType
        );

        // reset config so it doesn't leak into the next test
        $this->resetMetricsConfig();

        return new MetricsAssert($metrics);
    }

    /**
     * Override this in your TestCase to provide project-wide defaults.
     *
     * @return array{
     *     iterations? : int<1, max>,
     *     warmup? : int<0, max>,
     *     type? : ?AggregationType
     * }
     */
    protected function defaultMetricsConfig(): array
    {
        return [
            'iterations' => 1,
            'warmup' => 0,
            'type' => AggregationType::Average,
        ];
    }

    private function applyDefaultConfig(): void
    {
        $defaults = $this->defaultMetricsConfig();

        $this->metricsIterations = $defaults['iterations'] ?? 1;
        $this->metricsWarmup = $defaults['warmup'] ?? 0;
        $this->metricsType = $defaults['type'] ?? AggregationType::Average;
    }

    private function resetMetricsConfig(): void
    {
        $this->applyDefaultConfig();
    }
}
