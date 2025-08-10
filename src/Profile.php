<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Attribute;
use JsonSerializable;

use function in_array;

/**
 * @phpstan-type ProfileMap array{type: 'detailed'|'summary', iterations: int<1, max>, warmup:int<0, max>}
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class Profile implements JsonSerializable
{
    public const DETAILED = 'detailed';
    public const SUMMARY = 'summary';

    /**
     * @param self::DETAILED|self::SUMMARY $type
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     */
    public function __construct(
        public readonly string $type = self::SUMMARY,
        public readonly int $iterations = 3,
        public readonly int $warmup = 0,
    ) {
        self::isValidReport($type);
        self::isValidIterations($iterations);
        self::isValidWarmup($warmup);
    }

    /**
     * @param ProfileMap $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            iterations: $data['iterations'],
            warmup: $data['warmup'],
        );
    }

    public static function isValidReport(string $type): void
    {
        in_array($type, [self::DETAILED, self::SUMMARY], true) || throw new InvalidArgument('The defined type is not supported.');
    }

    public static function isValidIterations(int $iterations): void
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');
    }

    public static function isValidWarmup(int $warmup): void
    {
        0 <= $warmup || throw new InvalidArgument('The warmup must be a positive integer or zero.');
    }

    /**
     * @return ProfileMap
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'iterations' => $this->iterations,
            'warmup' => $this->warmup,
        ];
    }

    /**
     * @return ProfileMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
