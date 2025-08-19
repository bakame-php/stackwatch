<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

enum Feature: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';

    public function isEnabled(): bool
    {
        return self::Enabled === $this;
    }

    public function isDisabled(): bool
    {
        return self::Disabled === $this;
    }

    public static function fromBool(bool $visible): self
    {
        return $visible ? self::Enabled : self::Disabled;
    }

    public function equals(mixed $state): bool
    {
        return $state instanceof self
            && $state === $this;
    }
}
