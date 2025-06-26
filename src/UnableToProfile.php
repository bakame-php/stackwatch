<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use LogicException;

class UnableToProfile extends LogicException implements ProfilingException
{
}
