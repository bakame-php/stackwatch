<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

interface Handler
{
    public function handle(StackwatchInput $input): void;
}
