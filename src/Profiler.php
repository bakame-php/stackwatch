<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Throwable;
use Traversable;

use function array_filter;
use function array_key_last;
use function count;

/**
 * @implements IteratorAggregate<int, ProfilingData>
 * @phpstan-import-type ProfilingDataStat from ProfilingData
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    /** @var non-empty-string */
    private readonly string $identifier;
    private readonly Closure $callback;
    private readonly ?LoggerInterface $logger;
    /** @var list<ProfilingData> */
    private array $profilingDatas;
    /** @var array<string, 1> */
    private array $labels;

    /**
     * @param ?non-empty-string $identifier
     */
    public function __construct(callable $callback, ?string $identifier = null, ?LoggerInterface $logger = null)
    {
        $this->callback = $callback instanceof Closure ? $callback : $callback(...);
        $this->logger = $logger;
        $this->identifier = $identifier ?? Label::random();
        $this->reset();
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function reset(): void
    {
        $this->profilingDatas = [];
        $this->labels = [];
    }

    /**
     * Returns the value and the profiling data of the callback execution.
     *
     * @throws InvalidArgument|Throwable
     */
    public static function execute(callable $callback, ?LoggerInterface $logger = null): ProfilingResult
    {
        return self::profiling(Label::random(), Label::random(), $callback, $logger);
    }

    /**
     * @param non-empty-string $label
     *
     * @throws Throwable
     */
    private static function profiling(
        string $identifier,
        string $label,
        callable $callback,
        ?LoggerInterface $logger = null,
        mixed ...$args
    ): ProfilingResult {
        gc_collect_cycles();
        try {
            $logger?->info('Profiler ['.$identifier.'] starting profiling for label: '.$label.'.', ['identifier' => $identifier, 'label' => $label]);
            $start = Snapshot::now();
            $result = ($callback)(...$args);
            $end = Snapshot::now();
            $profilingData = new ProfilingData($start, $end, $label);
            $logger?->info('Profiler ['.$identifier.'] ending profiling for label: '.$label.'.', [...['identifier' => $identifier], ...$profilingData->toArray()]);

            return new ProfilingResult($result, $profilingData);
        } catch (Throwable $exception) {
            $logger?->error('Profiling aborted for label: '.$label.' due to an error in the executed code.', ['identifier' => $identifier, 'label' => $label, 'exception' => $exception]);

            throw $exception;
        }
    }

    /**
     * Returns the metrics associated with the callback.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function metrics(callable $callback, int $iterations = 1, ?LoggerInterface $logger = null): Metrics
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');

        $new = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $new[] = self::execute($callback, $logger);
        }

        return Metrics::average(...$new);
    }

    /**
     * Returns the CPU time in nanoseconds.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function cpuTime(callable $callback, int $iterations = 1, ?LoggerInterface $logger = null): float
    {
        return self::metrics($callback, $iterations, $logger)->cpuTime;
    }

    /**
     * Returns the execution time in nanoseconds.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function executionTime(callable $callback, int $iterations = 1, ?LoggerInterface $logger = null): float
    {
        return self::metrics($callback, $iterations, $logger)->executionTime;
    }

    /**
     * Returns the memory usage in bytes.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function memoryUsage(callable $callback, int $iterations = 1, ?LoggerInterface $logger = null): float
    {
        return self::metrics($callback, $iterations, $logger)->memoryUsage;
    }

    /**
     * Returns the real memory usage in bytes.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function realMemoryUsage(callable $callback, int $iterations = 1, ?LoggerInterface $logger = null): float
    {
        return self::metrics($callback, $iterations, $logger)->realMemoryUsage;
    }

    /**
     * Returns the peak memory usage in bytes.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function peakMemoryUsage(callable $callback, int $iterations = 1, ?LoggerInterface $logger = null): float
    {
        return self::metrics($callback, $iterations, $logger)->peakMemoryUsage;
    }

    /**
     * Returns the real peak memory usage in bytes.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function realPeakMemoryUsage(callable $callback, int $iterations = 1, ?LoggerInterface $logger = null): float
    {
        return self::metrics($callback, $iterations, $logger)->realPeakMemoryUsage;
    }

    /**
     * @throws InvalidArgument|Throwable
     */
    public function run(mixed ...$args): mixed
    {
        return $this->profile(Label::random(), ...$args);
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
        $result = self::profiling($this->identifier, $label, $this->callback, $this->logger, ...$args);
        $this->profilingDatas[] = $result->profilingData;
        $this->labels[$result->profilingData->label] = 1;

        return $result->result;
    }

    public function count(): int
    {
        return count($this->profilingDatas);
    }

    /**
     * @return Traversable<ProfilingData>
     */
    public function getIterator(): Traversable
    {
        yield from $this->profilingDatas;
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     profiling: list<ProfilingDataStat>
     * }
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'profiling' => array_map(fn (ProfilingData $profilingData): array => $profilingData->toArray(), $this->profilingDatas),
        ];
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     profiling: list<ProfilingData>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier,
            'profiling' => $this->profilingDatas,
        ];
    }

    public function isEmpty(): bool
    {
        return [] === $this->profilingDatas;
    }

    public function latest(): ?ProfilingData
    {
        return $this->nth(-1);
    }

    public function first(): ?ProfilingData
    {
        return $this->nth(0);
    }

    public function nth(int $offset): ?ProfilingData
    {
        if ($offset < 0) {
            $offset += count($this->profilingDatas);
        }

        return $this->profilingDatas[$offset] ?? null;
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
                $this->profilingDatas,
                fn (ProfilingData $profilingData): bool => $profilingData->label === $label
            )
        );
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
     * Returns the list of all distinct label present in the Profiler.
     *
     * @return list<string>
     */
    public function labels(): array
    {
        return array_keys($this->labels);
    }
}
