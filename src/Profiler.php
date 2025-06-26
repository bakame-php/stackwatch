<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

use function array_key_last;
use function bin2hex;
use function random_bytes;

/**
 * @phpstan-import-type ProfileMetrics from Profile
 * @implements  IteratorAggregate<int, Profile>
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    /** @var array<Profile> */
    private array $profiles = [];
    private Closure $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);
        $this->reset();
    }

    public function __invoke(mixed ...$args): mixed
    {
        return $this->runWithLabel(bin2hex(random_bytes(6)), ...$args);
    }

    public function runWithLabel(string $label, mixed ...$args): mixed
    {
        $profilingResult = ProfilingResult::profile($label, $this->callback, ...$args);
        $this->profiles[] = $profilingResult->profile;

        return $profilingResult->value;
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
    public function profiles(): array
    {
        return $this->profiles;
    }

    public function lastProfile(): ?Profile
    {
        return [] === $this->profiles ? null : $this->profiles[array_key_last($this->profiles)];
    }

    public function reset(): void
    {
        $this->profiles = [];
    }

    /**
     * @return array<ProfileMetrics>
     */
    public function jsonSerialize(): array
    {
        return array_map(static fn (Profile $profile): array => $profile->metrics(), $this->profiles);
    }
}
