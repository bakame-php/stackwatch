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
 * @implements  IteratorAggregate<int, Profile>
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    /** @var array<Profile> */
    private array $profiles;
    /** @var array<string, 1> */
    private array $labels;
    private Closure $callback;

    public static function metrics(callable $callback): Metrics
    {
        $new = new self($callback, new NullLogger());
        $new->__invoke();

        return $new->last()->metrics ?? Metrics::none();
    }

    public static function cpuTime(callable $callback): float
    {
        return self::metrics($callback)->cpuTime;
    }

    public static function executionTime(callable $callback): float
    {
        return self::metrics($callback)->executionTime;
    }

    public static function memoryUsage(callable $callback): float
    {
        return self::metrics($callback)->memoryUsage;
    }

    public static function realMemoryUsage(callable $callback): float
    {
        return self::metrics($callback)->realMemoryUsage;
    }

    public static function peakMemoryUsage(callable $callback): float
    {
        return self::metrics($callback)->peakMemoryUsage;
    }

    public static function realPeakMemoryUsage(callable $callback): float
    {
        return self::metrics($callback)->realPeakMemoryUsage;
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
        $this->profiles[] = $result->profile;
        $this->labels[$result->profile->label] = 1;

        return $result->value;
    }

    public function count(): int
    {
        return count($this->profiles);
    }

    /**
     * @return Traversable<Profile>
     */
    public function getIterator(): Traversable
    {
        yield from $this->profiles;
    }

    /**
     * @return array<Profile>
     */
    public function jsonSerialize(): array
    {
        return $this->profiles;
    }

    public function isEmpty(): bool
    {
        return [] === $this->profiles;
    }

    public function last(): ?Profile
    {
        return [] === $this->profiles ? null : $this->profiles[array_key_last($this->profiles)];
    }

    public function first(): ?Profile
    {
        return [] === $this->profiles ? null : $this->profiles[0];
    }

    public function nth(int $offset): ?Profile
    {
        if ($offset < 0) {
            $offset += count($this->profiles);
        }

        return $this->profiles[$offset] ?? null;
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
    public function get(string $label): ?Profile
    {
        $res = $this->getAll($label);

        return [] === $res ? null : $res[array_key_last($res)];
    }

    /**
     * Returns all the Profiles with the provided label.
     *
     * @return list<Profile>
     */
    public function getAll(string $label): array
    {
        return array_values(
            array_filter(
                $this->profiles,
                fn (Profile $profile) => $profile->label === $label
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
        $this->profiles = [];
        $this->labels = [];
    }
}
