<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

interface Processor
{
    /** @param iterable<UnitOfWork> $unitOfWorks */
    public function process(iterable $unitOfWorks): void;
}
