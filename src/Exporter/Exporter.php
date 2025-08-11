<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Summary;
use Bakame\Stackwatch\Timeline;

interface Exporter
{
    public function exportSnapshot(Snapshot $snapshot): void;

    public function exportSummary(Result|Summary $summary, Profiler|Timeline|null $parent = null): void;

    public function exportProfiler(Profiler $profiler, ?string $label = null): void;

    public function exportTimeline(Timeline $timeline): void;
}
