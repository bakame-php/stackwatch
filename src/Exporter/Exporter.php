<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\AggregationType;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\MetricType;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Statistics;
use Bakame\Stackwatch\Timeline;

interface Exporter
{
    public function exportEnvironment(Environment $environment): void;
    public function exportSnapshot(Snapshot $snapshot): void;
    public function exportMetrics(Metrics $metrics, ?AggregationType $type = null): void;
    public function exportSpan(Span $span): void;
    public function exportStatistics(Statistics $statistics, ?MetricType $type = null): void;
    public function exportReport(Report $report): void;
    /**
     * @param (callable(Span): bool)|string|null $label
     */
    public function exportProfiler(Profiler $profiler, callable|string|null $label = null): void;
    /**
     * @param ?callable(Snapshot): bool $filter
     */
    public function exportTimeline(Timeline $timeline, ?callable $filter = null): void;
}
