<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Closure;
use ReflectionFunctionAbstract;

/**
 * @phpstan-type Target array{closure: Closure, profile: Profile, method: ReflectionFunctionAbstract}
 * @phpstan-type TargetList list<Target>
 */
interface Processor
{
    /** @param TargetList $targetList */
    public function process(iterable $targetList): void;
}
