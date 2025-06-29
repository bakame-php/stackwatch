<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

final class ProfilingResult
{
    public function __construct(
        public readonly mixed $result,
        public readonly ProfilingData $profilingData
    ) {
    }
}
