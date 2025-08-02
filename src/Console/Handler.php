<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

interface Handler
{
    public function handle(Input $input): void;
}
