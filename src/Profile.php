<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Attribute;
use Bakame\Stackwatch\Exporter\StatsExporter;
use JsonSerializable;
use Throwable;

use function array_keys;
use function in_array;

use const STDOUT;

/**
 * @phpstan-type ProfileMap array{
 *     type: 'detailed'|'summary',
 *     iterations: int<1, max>,
 *     warmup:int<0, max>,
 *     tags:list<non-empty-string>
 *}
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class Profile implements JsonSerializable
{
    public const DETAILED = 'detailed';
    public const SUMMARY = 'summary';
    /** @var list<non-empty-string> */
    public readonly array $tags;

    /**
     * @param self::DETAILED|self::SUMMARY $type
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     * @param array<string> $tags
     */
    public function __construct(
        public readonly string $type = self::SUMMARY,
        public readonly int $iterations = 3,
        public readonly int $warmup = 0,
        array $tags = [],
    ) {
        self::isValidReport($type);
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
            type: $data['type'],
            iterations: $data['iterations'],
            warmup: $data['warmup'],
            tags: $data['tags']
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

    /**
     * Profile a callable and dump the stats to console.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    public static function dump(callable $callback, int $iterations = 3, int $warmup = 0, string $type = self::SUMMARY): void
    {
        in_array($type, [self::DETAILED, self::SUMMARY], true) || throw new InvalidArgument('The defined type is not supported.');

        $stats = self::SUMMARY === $type
            ? Profiler::metrics($callback, $iterations, $warmup)
            : Profiler::report($callback, $iterations, $warmup);
        $callLocation = CallLocation::fromLastInternalCall(__NAMESPACE__);
        $exporter = new StatsExporter(STDOUT);

        $exporter->writeln(
            Ansi::write('Path: ', AnsiStyle::Green)
            .Ansi::writeln($callLocation->path.' on line '.$callLocation->line, AnsiStyle::Yellow)
            .Ansi::write('Iterations: ', AnsiStyle::Green)
            .Ansi::write($iterations, AnsiStyle::Yellow)
            .'; '
            .Ansi::writeln('Warmup: ', AnsiStyle::Green)
            .Ansi::write($warmup, AnsiStyle::Yellow)
        );
        $stats instanceof Report ? $exporter->exportReport($stats) : $exporter->exportMetrics($stats);
    }

    /**
     * Profile a callable, dump the stats to console and die.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    public static function dd(callable $callback, int $iterations = 3, int $warmup = 0, string $type = self::SUMMARY): never
    {
        self::dump($callback, $iterations, $warmup, $type);

        exit(0);
    }
}
