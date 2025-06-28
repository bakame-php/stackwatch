<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

interface Exporter
{
    public function exportProfilingData(ProfilingData $profilingData): void;

    public function exportProfiler(Profiler $profiler): void;
}
