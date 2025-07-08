<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Random\RandomException;

use function bin2hex;
use function preg_match;
use function random_bytes;

final class Label
{
    /**
     * @throws InvalidArgument
     *
     * @return non-empty-string
     */
    public static function random(): string
    {
        try {
            return bin2hex(random_bytes(6));
        } catch (RandomException $exception) {
            throw new InvalidArgument('Unable to generate a random label.', previous: $exception);
        }
    }

    /**
     * @param non-empty-string $value
     *
     * @throws InvalidArgument
     *
     * @return non-empty-string
     */
    public static function fromString(string $value): string
    {
        ('' !== $value && 1 === preg_match('/^[a-z0-9][a-z0-9_]*$/', $value)) || throw new InvalidArgument('The label must start with a lowercased letter or a digit and only contain lowercased letters, digits, or underscores.');

        return $value;
    }
}
