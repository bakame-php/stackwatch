<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use InvalidArgumentException;

final class InvalidArgument extends InvalidArgumentException implements ProfilingException
{
}
