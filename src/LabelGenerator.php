<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Random\RandomException;

use function bin2hex;
use function intdiv;
use function preg_match;
use function random_bytes;
use function strtolower;
use function substr;
use function trim;

final class LabelGenerator
{
    private const REGEXP_LABEL = '/^
        (?!.*[._-]{2})             # disallow consecutive dots or underscore anywhere
        (?!.*[._-]$)               # disallow ending with a dot or underscore
        (?!^[._-])                 # disallow starting with a dot or underscore
        [a-z0-9]                   # first character must be a letter or a digit
        (?:[a-z0-9._-]*[a-z0-9])?  # middle optional, ending with letter or digit
    $/x';

    /**
     * @throws InvalidArgument
     *
     * @return non-empty-string
     */
    public static function sanitize(string $value): string
    {
        $value = strtolower(trim($value));

        ('' !== $value && 1 === preg_match(self::REGEXP_LABEL, $value)) || throw new InvalidArgument('The label must start with a lowercased letter or a digit and only contain lowercased letters, digits, point or underscores.');

        return $value;
    }

    /**
     * @param positive-int $length
     *
     * @throws InvalidArgument
     */
    public function __construct(public readonly int $length = 12)
    {
        0 < $this->length || throw new InvalidArgument('Length must be a positive integer.');
    }

    /**
     * @param positive-int $length
     */
    public function withLength(int $length): self
    {
        if ($length === $this->length) {
            return $this;
        }

        return new self($length);
    }

    /**
     * @return non-empty-string
     */
    public function generate(): string
    {
        try {
            /** @var positive-int $size */
            $size = intdiv($this->length + 1, 2);

            return substr(bin2hex(random_bytes($size)), 0, $this->length);
        } catch (RandomException $exception) {
            throw new InvalidArgument('Unable to generate a random label.', previous: $exception);
        }
    }
}
