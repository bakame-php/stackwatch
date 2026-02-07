<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Exporter\CallbackDumper;
use Bakame\Stackwatch\Exporter\ViewExporter;
use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Traversable;

use function array_column;
use function array_filter;
use function array_key_last;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function is_callable;
use function trim;

/**
 * @implements IteratorAggregate<int, Span>
 * @phpstan-import-type SpanMap from Span
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    /** @var non-empty-string */
    private readonly string $identifier;
    private readonly Closure $callback;
    /** @var list<Span> */
    private array $spans;

    /**
     * @param ?non-empty-string $identifier
     */
    public function __construct(callable $callback, ?string $identifier = null, private LoggerInterface $logger = new NullLogger())
    {
        $identifier ??= self::generateLabel();
        $identifier = trim($identifier);
        '' !== $identifier || throw new InvalidArgument('The identifier must be a non-empty string.');

        $this->identifier = $identifier;
        $this->callback = $callback instanceof Closure ? $callback : $callback(...);
        $this->reset();
    }

    /**
     * @return non-empty-string
     */
    private static function generateLabel(): string
    {
        /** @var LabelGenerator $labelGenerator */
        static $labelGenerator;
        if (null === $labelGenerator) {
            $labelGenerator = new LabelGenerator();
        }

        return $labelGenerator->generate();
    }

    public function reset(): void
    {
        $this->spans = [];
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    /**
     * @throws InvalidArgument|Throwable
     */
    public function run(mixed ...$args): mixed
    {
        return $this->profile(self::generateLabel(), ...$args);
    }

    /**
     * @param non-empty-string $label
     *
     * @throws InvalidArgument|Throwable
     */
    public function profile(string $label, mixed ...$args): mixed
    {
        try {
            $this->logger->info('Profiler ['.$this->identifier.'] starting profiling for label: '.$label.'.', ['identifier' => $this->identifier, 'label' => $label]);
            $start = Snapshot::now('start');
            $returnValue = ($this->callback)(...$args);
            $end = Snapshot::now('end');
            $span = new Span($label, $start, $end);
            $this->logger->info('Profiler ['.$this->identifier.'] ending profiling for label: '.$label.'.', [...['identifier' => $this->identifier], ...$span->toArray()]);

            $profiled = new Result($returnValue, $span);
            $this->spans[] = $profiled->span;

            return $profiled->returnValue;

        } catch (Throwable $exception) {
            $this->logger->error('Profiler ['.$this->identifier.'] profiling aborted for label: '.$label.' due to an error in the executed code.', ['identifier' => $this->identifier, 'label' => $label, 'exception' => $exception]);

            throw $exception;
        }
    }

    public function count(): int
    {
        return count($this->spans);
    }

    /**
     * @return Traversable<Span>
     */
    public function getIterator(): Traversable
    {
        yield from $this->spans;
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     spans: list<SpanMap>
     * }
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'spans' => array_map(fn (Span $span): array => $span->toArray(), $this->spans),
        ];
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     spans: list<Span>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier,
            'spans' => $this->spans,
        ];
    }

    public function hasNoSpan(): bool
    {
        return [] === $this->spans;
    }

    public function hasSpans(): bool
    {
        return ! $this->hasNoSpan();
    }

    public function latest(): ?Span
    {
        return $this->nth(-1);
    }

    public function first(): ?Span
    {
        return $this->nth(0);
    }

    /**
     * Returns the Summary using its index.
     *
     * Negative offsets are supported
     */
    public function nth(int $offset): ?Span
    {
        if ($offset < 0) {
            $offset += count($this->spans);
        }

        return $this->spans[$offset] ?? null;
    }

    /**
     * Tells whether the label is present in the current profiler cache.
     */
    public function has(string $label): bool
    {
        foreach ($this->spans as $span) {
            if ($span->label === $label) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the last Profile with the provided label.
     */
    public function get(string $label): ?Span
    {
        $res = $this->getAll($label);

        return [] === $res ? null : $res[array_key_last($res)];
    }

    /**
     * Returns all the Profiles with the provided label.
     *
     * @return list<Span>
     */
    public function getAll(string $label): array
    {
        return $this->filter(fn (Span $span): bool => $span->label === $label);
    }

    /**
     * Returns the average metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function average(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::Average);
    }

    /**
     * Returns the median metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function median(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::Median);
    }

    /**
     * Returns the range metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function range(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::Range);
    }

    /**
     * Returns the minimum metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function min(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::Minimum);
    }

    /**
     * Returns the maximum metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function max(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::Maximum);
    }

    /**
     * Returns the Sum metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function sum(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::Sum);
    }

    /**
     * Returns the Coeff Var metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function coefVar(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::CoefVar);
    }

    /**
     * Returns the stdDev metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function stdDev(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::StdDev);
    }

    /**
     * Returns the variance metrics associated with the callback.
     *
     * @param (callable(Span): bool)|non-empty-string|null $label
     */
    public function variance(callable|string|null $label = null): AggregatedMetrics
    {
        return Report::fromMetrics(...match (true) {
            null === $label => $this->spans,
            is_callable($label) => $this->filter($label),
            default => $this->getAll($label),
        })->column(AggregationType::Variance);
    }

    /**
     * @param callable(Span): bool $filter
     *
     * @return list<Span>
     */
    public function filter(callable $filter): array
    {
        return array_values(array_filter($this->spans, $filter));
    }

    /**
     * Returns the list of all distinct label present in the Profiler.
     *
     * @return list<string>
     */
    public function labels(): array
    {
        return array_values(array_unique(array_column($this->spans, 'label')));
    }

    /**
     * @param (callable(Span): bool)|string|null $label
     */
    public function dump(callable|string|null $label = null): self
    {
        (new ViewExporter())->exportProfiler($this, $label);

        return $this;
    }

    /**
     * @param (callable(Span): bool)|string|null $label
     */
    public function dd(callable|string|null $label = null): never
    {
        CallbackDumper::dd(fn () => $this->dump($label));
    }
}
