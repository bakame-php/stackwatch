<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Traversable;

use function array_filter;
use function array_key_last;
use function count;

/**
 * @implements  IteratorAggregate<int, ProfilingData>
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    /** @var array<ProfilingData> */
    private array $profilingDataList;
    /** @var array<string, 1> */
    private array $labels;
    private Closure $callback;

    public static function execute(callable $callback): ProfilingResult
    {
        return ProfilingResult::profile(null, $callback);
    }

    /**
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument if the iterations are lesser than 1
     */
    public static function metrics(callable $callback, int $iterations = 1): Metrics
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');

        $new = new self($callback);
        if (1 === $iterations) {
            $new();

            return $new->last()->metrics ?? Metrics::none();
        }

        for ($i = 0; $i < $iterations; ++$i) {
            $new();
        }

        return Metrics::avg($new);
    }

    /**
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument if the iterations are lesser than 1
     */
    public static function cpuTime(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->cpuTime;
    }

    /**
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument if the iterations are lesser than 1
     */
    public static function executionTime(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->executionTime;
    }

    /**
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument if the iterations are lesser than 1
     */
    public static function memoryUsage(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->memoryUsage;
    }

    /**
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument if the iterations are lesser than 1
     */
    public static function realMemoryUsage(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->realMemoryUsage;
    }

    /**
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument if the iterations are lesser than 1
     */
    public static function peakMemoryUsage(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->peakMemoryUsage;
    }

    /**
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument if the iterations are lesser than 1
     */
    public static function realPeakMemoryUsage(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->realPeakMemoryUsage;
    }

    public function __construct(callable $callback, private LoggerInterface $logger = new NullLogger())
    {
        $this->callback = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);
        $this->reset();
    }

    public function __invoke(mixed ...$args): mixed
    {
        return $this->runWithLabel(null, ...$args);
    }

    public function runWithLabel(?string $label, mixed ...$args): mixed
    {
        $result = ProfilingResult::profile($label, $this->callback, $this->logger, ...$args);
        $this->profilingDataList[] = $result->profilingData;
        $this->labels[$result->profilingData->label] = 1;

        return $result->result;
    }

    public function count(): int
    {
        return count($this->profilingDataList);
    }

    /**
     * @return Traversable<ProfilingData>
     */
    public function getIterator(): Traversable
    {
        yield from $this->profilingDataList;
    }

    /**
     * @return array<ProfilingData>
     */
    public function jsonSerialize(): array
    {
        return $this->profilingDataList;
    }

    public function isEmpty(): bool
    {
        return [] === $this->profilingDataList;
    }

    public function last(): ?ProfilingData
    {
        return [] === $this->profilingDataList ? null : $this->profilingDataList[array_key_last($this->profilingDataList)];
    }

    public function first(): ?ProfilingData
    {
        return [] === $this->profilingDataList ? null : $this->profilingDataList[0];
    }

    public function nth(int $offset): ?ProfilingData
    {
        if ($offset < 0) {
            $offset += count($this->profilingDataList);
        }

        return $this->profilingDataList[$offset] ?? null;
    }

    /**
     * Tells whether the label is present in the current profiler cache.
     */
    public function has(string $label): bool
    {
        return array_key_exists($label, $this->labels);
    }

    /**
     * Returns the last Profile with the provided label.
     */
    public function get(string $label): ?ProfilingData
    {
        $res = $this->getAll($label);

        return [] === $res ? null : $res[array_key_last($res)];
    }

    /**
     * Returns all the Profiles with the provided label.
     *
     * @return list<ProfilingData>
     */
    public function getAll(string $label): array
    {
        return array_values(
            array_filter(
                $this->profilingDataList,
                fn (ProfilingData $profile) => $profile->label === $label
            )
        );
    }

    /**
     * Returns the list of all distinct label present in the Profiler.
     *
     * @return list<string>
     */
    public function labels(): array
    {
        return array_keys($this->labels);
    }

    public function reset(): void
    {
        $this->profilingDataList = [];
        $this->labels = [];
    }
}
