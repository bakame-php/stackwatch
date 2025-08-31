<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Exporter\StatsExporter;
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
use function in_array;
use function trim;

use const STDOUT;

/**
 * @implements IteratorAggregate<int, Span>
 * @phpstan-import-type SpanMap from Span
 */
final class Profiler implements JsonSerializable, IteratorAggregate, Countable
{
    /** @var non-empty-string */
    private readonly string $identifier;
    private readonly Closure $callback;
    private readonly ?LoggerInterface $logger;
    /** @var list<Span> */
    private array $spans;

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
        $this->spans = [];
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
            $span = new Span($label, $start, $end);
            $logger?->info('Profiler ['.$identifier.'] ending profiling for label: '.$label.'.', [...['identifier' => $identifier], ...$span->toArray()]);

            return new Result($returnValue, $span);
        } catch (Throwable $exception) {
            $logger?->error('Profiler ['.$identifier.'] profiling aborted for label: '.$label.' due to an error in the executed code.', ['identifier' => $identifier, 'label' => $label, 'exception' => $exception]);

            throw $exception;
        }
    }

    /**
     * Profile a callable and dump the stats to console.
     *
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     */
    public static function dump(callable $callback, int $iterations = 3, int $warmup = 0, string $type = Profile::SUMMARY): void
    {
        in_array($type, [Profile::DETAILED, Profile::SUMMARY], true) || throw new InvalidArgument('The defined type is not supported.');

        $stats = Profile::SUMMARY === $type
            ? self::metrics($callback, $iterations, $warmup)
            : self::report($callback, $iterations, $warmup);
        $callLocation = CallLocation::fromLastInternalCall(__NAMESPACE__);
        $exporter = new StatsExporter(STDOUT);

        $exporter->writeln(
            Ansi::write('Path: ', AnsiStyle::Green)
            .Ansi::writeln($callLocation->path.':'.$callLocation->line, AnsiStyle::Yellow)
            .Ansi::write('Iterations: ', AnsiStyle::Green)
            .Ansi::write($iterations, AnsiStyle::Yellow)
            .'; '
            .Ansi::write('Warmup: ', AnsiStyle::Green)
            .Ansi::write($warmup, AnsiStyle::Yellow)
            ."\n"
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
    public static function dd(callable $callback, int $iterations = 3, int $warmup = 0, string $type = Profile::SUMMARY): never
    {
        self::dump($callback, $iterations, $warmup, $type);

        exit(1);
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
            $metrics[] = self::execute($callback, $logger)->span->metrics;
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
        $this->spans[] = $profiled->span;

        return $profiled->returnValue;
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
     *     summaries: list<SpanMap>
     * }
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'summaries' => array_map(fn (Span $span): array => $span->toArray(), $this->spans),
        ];
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     summaries: list<Span>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier,
            'summaries' => $this->spans,
        ];
    }

    public function isEmpty(): bool
    {
        return [] === $this->spans;
    }

    public function hasSummaries(): bool
    {
        return ! $this->isEmpty();
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
     */
    public function average(?string $label = null): Metrics
    {
        if (null === $label) {
            return Metrics::average($this);
        }

        return Metrics::average(...$this->getAll($label));
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
        return array_values(
            array_unique(
                array_column($this->spans, 'label')
            )
        );
    }
}
