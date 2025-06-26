<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

interface Renderer
{
    public function render(Profiler $profiler): void;
}
