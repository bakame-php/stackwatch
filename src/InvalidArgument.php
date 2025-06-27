<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use InvalidArgumentException;

final class InvalidArgument extends InvalidArgumentException implements ProfilingException
{
}
