<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

interface ExtendedExporter extends Exporter
{
    public function exportMetrics(Metrics $metrics): void;

    public function exportEnvironment(Environment $environment): void;

    public function exportStatistics(Statistics $statistics): void;

    public function exportReport(Report $report): void;
}
