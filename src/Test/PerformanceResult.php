<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Test;

use Bakame\Stackwatch\AggregationType;
use Bakame\Stackwatch\Report;

/**
 * Fluent assertions for Profiler metrics.
 */
final class PerformanceResult
{
    public function __construct(public readonly Report $report)
    {
    }

    private function column(AggregationType $type): MetricsAssert
    {
        return new MetricsAssert($this->report->column($type));
    }

    // ------------------------------------------------
    // Aggregation presets
    // ------------------------------------------------

    public function average(): MetricsAssert
    {
        return $this->column(AggregationType::Average);
    }

    public function median(): MetricsAssert
    {
        return $this->column(AggregationType::Median);
    }

    public function min(): MetricsAssert
    {
        return $this->column(AggregationType::Minimum);
    }

    public function max(): MetricsAssert
    {
        return $this->column(AggregationType::Maximum);
    }

    public function sum(): MetricsAssert
    {
        return $this->column(AggregationType::Sum);
    }

    public function range(): MetricsAssert
    {
        return $this->column(AggregationType::Range);
    }

    public function variance(): MetricsAssert
    {
        return $this->column(AggregationType::Variance);
    }

    public function stdDev(): MetricsAssert
    {
        return $this->column(AggregationType::StdDev);
    }

    public function coefVar(): MetricsAssert
    {
        return $this->column(AggregationType::CoefVar);
    }
}
