<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Attribute;
use JsonSerializable;

use function array_keys;

/**
 * @phpstan-type ProfileMap array{
 *     type: ?string,
 *     iterations: int<1, max>,
 *     warmup:int<0, max>,
 *     tags:list<non-empty-string>
 *}
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class Profile implements JsonSerializable
{
    /** @var list<non-empty-string> */
    public readonly array $tags;

    /**
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     * @param array<string> $tags
     */
    public function __construct(
        public readonly ?AggregationType $type = null,
        public readonly int $iterations = 3,
        public readonly int $warmup = 0,
        array $tags = [],
    ) {
        self::isValidIterations($iterations);
        self::isValidWarmup($warmup);

        $this->tags = self::filterTags($tags);
    }

    /**
     * @param ProfileMap $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: null !== $data['type'] ? AggregationType::from($data['type']) : null,
            iterations: $data['iterations'],
            warmup: $data['warmup'],
            tags: $data['tags']
        );
    }

    private static function isValidIterations(int $iterations): void
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');
    }

    private static function isValidWarmup(int $warmup): void
    {
        0 <= $warmup || throw new InvalidArgument('The warmup must be a positive integer or zero.');
    }

    /**
     * @return ProfileMap
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type?->value,
            'iterations' => $this->iterations,
            'warmup' => $this->warmup,
            'tags' => $this->tags,
        ];
    }

    /**
     * @return ProfileMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<mixed> $tags
     *
     * @return list<non-empty-string>
     */
    private static function filterTags(array $tags): array
    {
        $res = [];
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }

            $tag = trim(strtolower($tag));
            if ('' === $tag) {
                continue;
            }

            $res[$tag] = 1;
        }

        return array_keys($res);
    }
}
