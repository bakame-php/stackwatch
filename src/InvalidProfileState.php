<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use RuntimeException;

class InvalidProfileState extends RuntimeException implements ProfilingException
{
}
