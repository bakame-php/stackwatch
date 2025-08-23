<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Exporter\Exporter as BasicExporter;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Statistics;

interface Exporter extends BasicExporter
{
    public function exportMetrics(Metrics $metrics): void;

    public function exportReport(Report $report): void;

    public function exportEnvironment(Environment $environment): void;

    public function exportStatistics(Statistics $statistics): void;
}
