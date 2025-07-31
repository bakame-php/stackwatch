<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use LogicException;

class UnableToProfile extends LogicException implements ProfilingException
{
}
