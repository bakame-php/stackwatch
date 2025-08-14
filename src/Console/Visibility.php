<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

enum Visibility
{
    case Show;
    case Hide;

    public function isVisible(): bool
    {
        return self::Show === $this;
    }

    public function isHidden(): bool
    {
        return self::Hide === $this;
    }

    public static function fromBool(bool $visible): self
    {
        return $visible ? self::Show : self::Hide;
    }

    public function equals(mixed $visibility): bool
    {
        return $visibility instanceof self
            && $visibility === $this;
    }
}
