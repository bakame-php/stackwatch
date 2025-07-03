<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

interface Exporter
{
    public function exportSnapshot(Snapshot $snapshot): void;

    public function exportProfilingData(ProfilingResult|ProfilingData $profilingData): void;

    public function exportProfiler(Profiler $profiler): void;
}
