<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

enum Display: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';

    public function isVisible(): bool
    {
        return self::Visible === $this;
    }

    public function isHidden(): bool
    {
        return self::Hidden === $this;
    }

    public static function fromBool(bool $visible): self
    {
        return $visible ? self::Visible : self::Hidden;
    }

    public function equals(mixed $visibility): bool
    {
        return $visibility instanceof self
            && $visibility === $this;
    }
}
