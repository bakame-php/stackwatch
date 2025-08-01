<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

/**
 * @phpstan-type TargetList list<Target>
 */
interface Processor
{
    /** @param iterable<Target> $targetList */
    public function process(iterable $targetList): void;
}
