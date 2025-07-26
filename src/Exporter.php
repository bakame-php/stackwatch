<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

interface Exporter
{
    public function exportSnapshot(Snapshot $snapshot): void;

    public function exportSummary(Result|Summary $summary, Profiler|Marker|null $parent = null): void;

    public function exportProfiler(Profiler $profiler, ?string $label = null): void;

    public function exportMarker(Marker $marker): void;
}
