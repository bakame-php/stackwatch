<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

enum State
{
    case Enabled;
    case Disabled;

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
