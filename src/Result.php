<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

final class Result
{
    public function __construct(
        public readonly mixed $returnValue,
        public readonly Summary $summary
    ) {
    }
}
