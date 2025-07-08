<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Traversable;

use function array_key_exists;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function count;
use function gc_collect_cycles;
use function implode;

/**
 * @implements IteratorAggregate<non-empty-string, Snapshot>
 * @phpstan-import-type SnapshotStat from Snapshot
 */
final class Timeline implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var array<non-empty-string, Snapshot> */
    private array $snapshots = [];
    /** @var array<non-empty-string, 1> */
    private array $labels = [];
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        gc_collect_cycles();
        $this->logger = $logger;
    }

    public function reset(): void
    {
        gc_collect_cycles();
        $this->snapshots = [];
        $this->labels = [];
    }

    /**
     * @param non-empty-string $label
     */
    public function take(string $label): void
    {
        $label = Label::fromString($label);
        if ($this->has($label)) {
            throw new InvalidArgument('The label "'.$label.'" already exists.');
        }

        $from = array_key_last($this->snapshots);
        $lastSnapshot = $this->snapshots[$from] ?? null;
        $newSnapshot = Snapshot::now();
        if (null !== $lastSnapshot && $lastSnapshot->hrtime > $newSnapshot->hrtime) {
            throw new InvalidArgument('"'.$from.'" must come before "'.$label.'".');
        }

        $this->logger?->info('snapshot for label: '.$label.'.', ['snapshot' => $newSnapshot->toArray()]);
        $this->snapshots[$label] = $newSnapshot;
        $this->labels[$label] = 1;
    }

    public function get(string $label): Snapshot
    {
        return $this->snapshots[$label] ?? throw new InvalidArgument('The label "'.$label.'" does not exist.');
    }

    public function delta(string $from, string $to, ?string $metric = null): ProfilingData|float
    {
        if (!$this->has($from) || !$this->has($to)) {
            throw new InvalidArgument('The labels "'.$from.'" and/or "'.$to.'" do not exist.');
        }

        $profilingData = new ProfilingData($this->snapshots[$from], $this->snapshots[$to], $from.'_'.$to);
        if (null === $metric) {
            return $profilingData;
        }

        $metrics = $profilingData->metrics->toArray();

        return $metrics[$metric] ?? throw new InvalidArgument('Unknown metrics name: "'.$metric.'"; expected one of "'.implode('", "', array_keys($metrics)).'"');

    }

    public function count(): int
    {
        return count($this->snapshots);
    }

    public function isEmpty(): bool
    {
        return [] === $this->snapshots;
    }

    public function hasStarted(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Tells whether a report or a summary can be generated.
     */
    public function hasIntervals(): bool
    {
        return 2 <= count($this->snapshots);
    }

    /**
     * @return Generator<non-empty-string,Snapshot>
     */
    public function getIterator(): Traversable
    {
        yield from $this->snapshots;
    }

    /**
     * @return array<non-empty-string, SnapshotStat>
     */
    public function toArray(): array
    {
        return array_map(fn (Snapshot $snapshot) => $snapshot->toArray(), $this->snapshots);
    }

    /**
     * @return array<non-empty-string, Snapshot>
     */
    public function jsonSerialize(): array
    {
        return $this->snapshots;
    }

    /**
     * @return list<non-empty-string>
     */
    public function labels(): array
    {
        return array_keys($this->labels);
    }

    /**
     * @return iterable<ProfilingData>
     */
    public function reports(): iterable
    {
        if (! $this->hasIntervals()) {
            return;
        }

        $labels = $this->labels();
        $count = count($labels);
        for ($i = 1; $i < $count; $i++) {
            /** @var ProfilingData $profilingData */
            $profilingData = $this->delta($labels[$i - 1], $labels[$i]);

            yield $profilingData;
        }
    }

    public function latest(): ?Snapshot
    {
        return $this->snapshots[array_key_last($this->snapshots)] ?? null;
    }

    public function first(): ?Snapshot
    {
        return $this->nth(0);
    }

    public function nth(int $offset): ?Snapshot
    {
        $labels = $this->labels();
        if ([] === $labels) {
            return null;
        }

        if ($offset < 0) {
            $offset += count($labels);
        }

        $label = $labels[$offset] ?? null;

        return null !== $label ? $this->snapshots[$label] : null;
    }

    /**
     * Tells whether the label is present in the current profiler cache.
     */
    public function has(string $label): bool
    {
        return array_key_exists($label, $this->snapshots);
    }

    /**
     * @param non-empty-string|null $label
     */
    public function summary(?string $label = null): ?ProfilingData
    {
        if (!$this->hasIntervals()) {
            return null;
        }

        $from = array_key_first($this->labels);
        $to = array_key_last($this->labels);

        return new ProfilingData($this->snapshots[$from], $this->snapshots[$to], Label::fromString($label ?? $from.'_'.$to));
    }

    /**
     * @param non-empty-string $label
     */
    public static function start(string $label = 'start', ?LoggerInterface $logger = null): self
    {
        $timeline = new self($logger);
        $timeline->take($label);

        return $timeline;
    }

    /**
     * Takes a final snapshot (default: 'end') and returns the summary profiling data.
     *
     * @param non-empty-string $label
     * @param ?non-empty-string $summaryLabel
     */
    public function finish(string $label = 'end', ?string $summaryLabel = null): ProfilingData
    {
        $this->hasStarted() || throw new InvalidArgument('The timeline can not be finished; no starting snapshot found.');

        $this->take($label);
        /** @var ProfilingData $profiling */
        $profiling = $this->summary($summaryLabel);

        $this->logger?->info('timeline summary', ['label' => $profiling->label, 'metrics' => $profiling->metrics->toArray()]);

        return $profiling;
    }
}
