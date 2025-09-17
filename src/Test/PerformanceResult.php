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
    private MetricsAssert $average;
    private MetricsAssert $median;
    private MetricsAssert $minimum;
    private MetricsAssert $maximum;
    private MetricsAssert $range;
    private MetricsAssert $sum;
    private MetricsAssert $variance;
    private MetricsAssert $stdDev;
    private MetricsAssert $coefVar;

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
        return $this->average ??= $this->column(AggregationType::Average);
    }

    public function median(): MetricsAssert
    {
        return $this->median ??= $this->column(AggregationType::Median);
    }

    public function min(): MetricsAssert
    {
        return $this->minimum ??= $this->column(AggregationType::Minimum);
    }

    public function max(): MetricsAssert
    {
        return $this->maximum ??= $this->column(AggregationType::Maximum);
    }

    public function sum(): MetricsAssert
    {
        return $this->sum ??= $this->column(AggregationType::Sum);
    }

    public function range(): MetricsAssert
    {
        return $this->range ??= $this->column(AggregationType::Range);
    }

    public function variance(): MetricsAssert
    {
        return $this->variance ??= $this->column(AggregationType::Variance);
    }

    public function stdDev(): MetricsAssert
    {
        return $this->stdDev ??= $this->column(AggregationType::StdDev);
    }

    public function coefVar(): MetricsAssert
    {
        return $this->coefVar ??= $this->column(AggregationType::CoefVar);
    }
}
