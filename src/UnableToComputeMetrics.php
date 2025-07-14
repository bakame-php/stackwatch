<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use LogicException;

final class UnableToComputeMetrics extends LogicException implements ProfilingException
{
}
