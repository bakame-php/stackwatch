<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Throwable;
use Traversable;

use function array_filter;
use function array_key_last;
use function bin2hex;
use function count;
use function gc_collect_cycles;
use function random_bytes;

/**
 * @implements  IteratorAggregate<int, ProfilingData>
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    private readonly Closure $callback;
    private readonly ?LoggerInterface $logger;
    /** @var list<ProfilingData> */
    private array $profilingDataList;
    /** @var array<string, 1> */
    private array $labels;

    public function __construct(callable $callback, ?LoggerInterface $logger = null)
    {
        $this->callback = $callback instanceof Closure ? $callback : $callback(...);
        $this->logger = $logger;
        $this->reset();
    }

    public function reset(): void
    {
        $this->profilingDataList = [];
        $this->labels = [];
    }

    /**
     * Returns the value and the profiling data of the callback execution.
     *
     * @throws Throwable
     */
    public static function execute(callable $callback): ProfilingResult
    {
        return self::profile(null, $callback);
    }

    /**
     * @param ?non-empty-string $label
     *
     * @throws Throwable
     */
    private static function profile(
        ?string $label,
        callable $callback,
        ?LoggerInterface $logger = null,
        mixed ...$args
    ): ProfilingResult {
        gc_collect_cycles();

        $label ??= self::randomLabel();
        try {
            $logger?->info('Starting profiling for label: '.$label.'.', ['label' => $label]);
            $start = Snapshot::now();
            $result = ($callback)(...$args);
            $end = Snapshot::now();
            $profilingData = new ProfilingData($start, $end, $label);
            $logger?->info('Finished profiling for label: '.$label.'.', $profilingData->stats());

            return new ProfilingResult($result, $profilingData);
        } catch (Throwable $exception) {
            $logger?->error('Profiling aborted for label: '.$label.' due to an error in the executed code.', ['label' => $label, 'exception' => $exception]);

            throw $exception;
        }
    }

    /**
     * @throws InvalidArgument
     *
     * @return non-empty-string
     */
    public static function randomLabel(): string
    {
        try {
            return bin2hex(random_bytes(6));
        } catch (RandomException $exception) {
            throw new InvalidArgument('Unable to generate a random label.', previous: $exception);
        }
    }

    /**
     * Returns the metrics associated with the callback.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function metrics(callable $callback, int $iterations = 1): Metrics
    {
        1 <= $iterations || throw new InvalidArgument('The iterations argument must be a positive integer greater than or equal to 1.');

        $new = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $new[] = self::execute($callback);
        }

        return Metrics::avg(...$new);
    }

    /**
     * Returns the CPU time in nanoseconds.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function cpuTime(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->cpuTime;
    }

    /**
     * Returns the execution time in nanoseconds.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function executionTime(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->executionTime;
    }

    /**
     * Returns the memory usage in bytes.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function memoryUsage(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->memoryUsage;
    }

    /**
     * Returns the real memory usage in bytes.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function realMemoryUsage(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->realMemoryUsage;
    }

    /**
     * Returns the peak memory usage in bytes.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function peakMemoryUsage(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->peakMemoryUsage;
    }

    /**
     * Returns the real peak memory usage in bytes.
     *
     * @param int<1, max> $iterations
     *
     * @throws InvalidArgument|Throwable
     */
    public static function realPeakMemoryUsage(callable $callback, int $iterations = 1): float
    {
        return self::metrics($callback, $iterations)->realPeakMemoryUsage;
    }

    /**
     * @throws Throwable
     */
    public function __invoke(mixed ...$args): mixed
    {
        return $this->runWithLabel(null, ...$args);
    }

    /**
     * @param ?non-empty-string $label
     *
     * @throws Throwable
     */
    public function runWithLabel(?string $label, mixed ...$args): mixed
    {
        $result = self::profile($label, $this->callback, $this->logger, ...$args);
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
        return $this->nth(-1);
    }

    public function first(): ?ProfilingData
    {
        return $this->nth(0);
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
                fn (ProfilingData $profilingData): bool => $profilingData->label === $label
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
}
