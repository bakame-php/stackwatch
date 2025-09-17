<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Test;

use Bakame\Stackwatch\Stack;
use Throwable;

trait PerformanceAssertions
{
    /** @var int<1, max> */
    private int $performanceIterations = 1;
    /** @var int<0, max> */
    private int $performanceWarmup = 0;

    /**
     * Set the number of iterations (fluent).
     *
     * @param int<1, max> $iterations
     */
    public function iterations(int $iterations): self
    {
        $this->performanceIterations = $iterations;

        return $this;
    }

    /**
     * Set the number of warmup runs (fluent).
     *
     * @param int<0, max> $warmup
     */
    public function warmup(int $warmup): self
    {
        $this->performanceWarmup = $warmup;

        return $this;
    }

    /**
     * Profile a callback and return a fluent MetricsAssert object.
     *
     * @throws Throwable
     */
    public function assertPerformance(callable $callback): PerformanceResult
    {
        $report = Stack::report($callback, $this->performanceIterations, $this->performanceWarmup);

        $this->resetPerformanceConfig();

        return new PerformanceResult($report);
    }

    /**
     * Returns the metrics from executing a callback and return a fluent MetricsAssert object.
     *
     * @throws Throwable
     */
    public function assertOnce(callable $callback): MetricsAssert
    {
        $this->resetPerformanceConfig();

        return new MetricsAssert(Stack::call($callback)->span->metrics);
    }

    // ------------------------------------------------
    // Aggregation presets
    // ------------------------------------------------

    public function assertMedian(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->median();
    }

    public function assertAverage(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->average();
    }

    public function assertMin(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->min();
    }

    public function assertMax(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->max();
    }

    public function assertVariance(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->variance();
    }

    public function assertStdDev(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->stdDev();
    }

    public function assertCoefVar(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->coefVar();
    }

    public function assertSum(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->sum();
    }

    public function range(callable $callback): MetricsAssert
    {
        return $this->assertPerformance($callback)->range();
    }

    /**
     * Override this in your TestCase to provide project-wide defaults.
     *
     * @return array{iterations? : int<1, max>, warmup? : int<0, max>}
     */
    protected function defaultMetricsConfig(): array
    {
        return ['iterations' => 1, 'warmup' => 0];
    }

    private function resetPerformanceConfig(): void
    {
        $defaults = $this->defaultMetricsConfig();

        $this->performanceIterations = $defaults['iterations'] ?? 1;
        $this->performanceWarmup = $defaults['warmup'] ?? 0;
    }
}
