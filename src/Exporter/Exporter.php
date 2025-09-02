<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Timeline;

interface Exporter
{
    public function exportProfiler(Profiler $profiler, ?string $label = null): void;
    public function exportTimeline(Timeline $timeline): void;
}
