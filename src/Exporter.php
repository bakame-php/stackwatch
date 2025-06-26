<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

interface Exporter
{
    public function exportProfile(Profile $profile): void;

    public function exportProfiler(Profiler $profiler): void;
}
