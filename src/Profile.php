<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Attribute;
use JsonSerializable;

use function in_array;
use function json_encode;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
final class Profile implements JsonSerializable
{
    public const REPORT = 'report';
    public const METRICS = 'metrics';

    /**
     * @param self::REPORT|self::METRICS $type
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     */
    public function __construct(
        public readonly string $type = self::METRICS,
        public readonly int $iterations = 1,
        public readonly int $warmup = 0,
    ) {
        self::isValidReport($type);
        self::isValidIterations($iterations);
        self::isValidWarmup($warmup);
    }

    public static function isValidReport(string $type): void
    {
        in_array($type, [self::REPORT, self::METRICS], true) || throw new InvalidArgument('The defined type is not supported.');
    }

    public static function isValidIterations(int $iterations): void
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');
    }

    public static function isValidWarmup(int $warmup): void
    {
        0 <= $warmup || throw new InvalidArgument('The warmup must be a positive integer or zero.');
    }

    public function __toString(): string
    {
        return (string) json_encode($this->toArray());
    }

    /**
     * @return array{type:string, iterations: int, warmup:int}
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
     * @return array{type:string, iterations: int, warmup:int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
