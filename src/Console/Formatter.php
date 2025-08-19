<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Throwable;

interface Formatter
{
    /**
     * @param iterable<UnitOfWork> $unitOfWorks
     *
     * @throws Throwable
     */
    public function format(iterable $unitOfWorks): void;
}
