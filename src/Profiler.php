<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Throwable;
use Traversable;

use function array_column;
use function array_filter;
use function array_key_last;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function trim;

/**
 * @implements IteratorAggregate<int, Summary>
 * @phpstan-import-type SummaryStat from Summary
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    /** @var non-empty-string */
    private readonly string $identifier;
    private readonly Closure $callback;
    private readonly ?LoggerInterface $logger;
    /** @var list<Summary> */
    private array $summaries;

    /**
     * @param ?non-empty-string $identifier
     */
    public function __construct(callable $callback, ?string $identifier = null, ?LoggerInterface $logger = null)
    {
        $identifier ??= self::generateLabel();
        $identifier = trim($identifier);
        '' !== $identifier || throw new InvalidArgument('The identifier must be a non-empty string.');

        $this->identifier = $identifier;
        $this->callback = $callback instanceof Closure ? $callback : $callback(...);
        $this->logger = $logger;
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
        $this->summaries = [];
    }

    private static function warmup(int $warmup, callable $callback): void
    {
        if (0 < $warmup) {
            for ($i = 0; $i < $warmup; ++$i) {
                $callback();
            }
        }
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    /**
     * Returns the value and the profiling data of the callback execution.
     *
     * @throws InvalidArgument|Throwable
     */
    public static function execute(callable $callback, ?LoggerInterface $logger = null): Result
    {
        return self::profileOnce(self::generateLabel(), self::generateLabel(), $callback, $logger);
    }

    /**
     * @param non-empty-string $label
     *
     * @throws Throwable
     */
    private static function profileOnce(
        string $identifier,
        string $label,
        callable $callback,
        ?LoggerInterface $logger = null,
        mixed ...$args
    ): Result {
        gc_collect_cycles();
        try {
            $logger?->info('Profiler ['.$identifier.'] starting profiling for label: '.$label.'.', ['identifier' => $identifier, 'label' => $label]);
            $start = Snapshot::now('start');
            $returnValue = ($callback)(...$args);
            $end = Snapshot::now('end');
            $summary = new Summary($label, $start, $end);
            $logger?->info('Profiler ['.$identifier.'] ending profiling for label: '.$label.'.', [...['identifier' => $identifier], ...$summary->toArray()]);

            return new Result($returnValue, $summary);
        } catch (Throwable $exception) {
            $logger?->error('Profiler ['.$identifier.'] profiling aborted for label: '.$label.' due to an error in the executed code.', ['identifier' => $identifier, 'label' => $label, 'exception' => $exception]);

            throw $exception;
        }
    }

    /**
     * Returns the metrics associated with the callback.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws InvalidArgument|Throwable
     */
    public static function metrics(callable $callback, int $iterations = 1, int $warmup = 0, ?LoggerInterface $logger = null): Metrics
    {
        self::assertItCanBeRun($iterations, $warmup);
        self::warmup($warmup, $callback);

        $new = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $new[] = self::execute($callback, $logger);
        }

        return Metrics::average(...$new);
    }

    /**
     * Returns the metrics associated with the callback.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws InvalidArgument|Throwable
     */
    public static function report(callable $callback, int $iterations = 1, int $warmup = 0, ?LoggerInterface $logger = null): Report
    {
        self::assertItCanBeRun($iterations, $warmup);
        self::warmup($warmup, $callback);
        $metrics = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $metrics[] = self::execute($callback, $logger)->summary->metrics;
        }

        return Report::fromMetrics(...$metrics);
    }

    /**
     * @throws InvalidArgument
     */
    private static function assertItCanBeRun(int $iterations, int $warmup): void
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');
        0 <= $warmup || throw new InvalidArgument('The warmup argument must be an integer greater than or equal to 0.');
    }

    /**
     * @throws InvalidArgument|Throwable
     */
    public function run(mixed ...$args): mixed
    {
        return $this->profile(self::generateLabel(), ...$args);
    }

    /**
     * @throws InvalidArgument|Throwable
     */
    public function __invoke(mixed ...$args): mixed
    {
        return $this->run(...$args);
    }

    /**
     * @param non-empty-string $label
     *
     * @throws InvalidArgument|Throwable
     */
    public function profile(string $label, mixed ...$args): mixed
    {
        $profiled = self::profileOnce($this->identifier, $label, $this->callback, $this->logger, ...$args);
        $this->summaries[] = $profiled->summary;

        return $profiled->returnValue;
    }

    public function count(): int
    {
        return count($this->summaries);
    }

    /**
     * @return Traversable<Summary>
     */
    public function getIterator(): Traversable
    {
        yield from $this->summaries;
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     summaries: list<SummaryStat>
     * }
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'summaries' => array_map(fn (Summary $summary): array => $summary->toArray(), $this->summaries),
        ];
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     summaries: list<Summary>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier,
            'summaries' => $this->summaries,
        ];
    }

    public function isEmpty(): bool
    {
        return [] === $this->summaries;
    }

    public function hasSummaries(): bool
    {
        return ! $this->isEmpty();
    }

    public function latest(): ?Summary
    {
        return $this->nth(-1);
    }

    public function first(): ?Summary
    {
        return $this->nth(0);
    }

    /**
     * Returns the Summary using its index.
     *
     * Negative offsets are supported
     */
    public function nth(int $offset): ?Summary
    {
        if ($offset < 0) {
            $offset += count($this->summaries);
        }

        return $this->summaries[$offset] ?? null;
    }

    /**
     * Tells whether the label is present in the current profiler cache.
     */
    public function has(string $label): bool
    {
        foreach ($this->summaries as $summary) {
            if ($summary->label === $label) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the last Profile with the provided label.
     */
    public function get(string $label): ?Summary
    {
        $res = $this->getAll($label);

        return [] === $res ? null : $res[array_key_last($res)];
    }

    /**
     * Returns all the Profiles with the provided label.
     *
     * @return list<Summary>
     */
    public function getAll(string $label): array
    {
        return $this->filter(fn (Summary $summary): bool => $summary->label === $label);
    }

    /**
     * Returns the average metrics associated with the callback.
     */
    public function average(?string $label = null): Metrics
    {
        if (null === $label) {
            return Metrics::average($this);
        }

        return Metrics::average(...$this->getAll($label));
    }

    /**
     * @param callable(Summary): bool $filter
     *
     * @return list<Summary>
     */
    public function filter(callable $filter): array
    {
        return array_values(array_filter($this->summaries, $filter));
    }

    /**
     * Returns the list of all distinct label present in the Profiler.
     *
     * @return list<string>
     */
    public function labels(): array
    {
        return array_values(
            array_unique(
                array_column($this->summaries, 'label')
            )
        );
    }
}
