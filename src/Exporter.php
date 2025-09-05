<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

interface Exporter
{
    public function exportEnvironment(Environment $environment): void;
    public function exportSnapshot(Snapshot $snapshot): void;
    public function exportMetrics(Metrics $metrics): void;
    public function exportSpan(Span $span): void;
    public function exportStatistics(Statistics $statistics): void;
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
