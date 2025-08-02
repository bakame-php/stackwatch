<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

interface Processor
{
    /** @param iterable<Target> $targets */
    public function process(iterable $targets): void;
}
