<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

interface Exporter
{
    public function exportMetrics(Metrics $metrics): void;
    public function exportReport(Report $report): void;
    public function exportEnvironment(Environment $environment): void;
    public function exportStatistics(Statistics $statistics): void;
    public function exportSnapshot(Snapshot $snapshot): void;
    public function exportSpanAggregator(SpanAggregator $spanAggregator, callable|string|null $label = null): void;
    public function exportTimeline(Timeline $timeline): void;
}
