<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Traversable;

use function array_key_last;
use function array_map;
use function bin2hex;
use function count;
use function random_bytes;

/**
 * @phpstan-import-type ProfileMetrics from Profile
 * @implements  IteratorAggregate<int, Profile>
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    /** @var array<Profile> */
    private array $profiles;
    /** @var array<string, 1> */
    private array $labels;
    private Closure $callback;

    public function __construct(callable $callback, private LoggerInterface $logger = new NullLogger())
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
        $this->logger->info("Starting profiling for label: {$label}");
        try {
            $result = ProfilingResult::profile($label, $this->callback, ...$args);

            $this->profiles[] = $result->profile;
            $this->labels[$result->profile->label()] = 1;

            $this->logger->info("Finished profiling for label: {$label}", $result->profile->metrics()['metrics']);

            return $result->value;
        } catch (ProfilingException $exception) {
            $this->logger->error('Profiling aborted for label: {label} due to an error in the profiling processus.', ['label' => $label, 'exception' => $exception]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error('Profiling aborted for label: {label} due to an error in the executed code.', ['label' => $label, 'exception' => $exception]);

            throw $exception;
        }
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
     * @return array<ProfileMetrics>
     */
    public function jsonSerialize(): array
    {
        return array_map(static fn (Profile $profile): array => $profile->metrics(), $this->profiles);
    }

    public function isEmpty(): bool
    {
        return [] === $this->profiles;
    }

    public function lastProfile(): ?Profile
    {
        return [] === $this->profiles ? null : $this->profiles[array_key_last($this->profiles)];
    }

    /**
     * Tells whether the label is present in the current profiler cache.
     */
    public function has(string $label): bool
    {
        return array_key_exists($label, $this->labels);
    }

    /**
     * Returns the first Profile with the provided label.
     */
    public function get(string $label): ?Profile
    {
        foreach ($this->profiles as $profile) {
            if ($profile->label() === $label) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * Returns all the Profiles with the provided label.
     *
     * @return list<Profile>
     */
    public function getAll(string $label): array
    {
        $result = [];
        foreach ($this->profiles as $profile) {
            if ($profile->label() === $label) {
                $result[] = $profile;
            }
        }

        return $result;
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
