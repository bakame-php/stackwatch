<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

final class ProfilingResult
{
    public function __construct(
        public readonly Profile $profile,
        public readonly mixed $value
    ) {
    }

    public static function profile(?string $label, callable $callback, mixed ...$args): self
    {
        $profile = new Profile($label);
        $profile->beginProfiling();
        $result = ($callback)(...$args);
        $profile->endProfiling();

        return new self($profile, $result);
    }
}
