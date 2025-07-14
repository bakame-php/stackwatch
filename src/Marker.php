<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Traversable;

use function array_column;
use function array_key_exists;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function gc_collect_cycles;
use function implode;
use function trim;

/**
 * @implements IteratorAggregate<non-empty-string, Snapshot>
 * @phpstan-import-type SnapshotStat from Snapshot
 */
final class Marker implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var non-empty-string */
    private readonly string $identifier;
    /** @var array<non-empty-string, Snapshot> */
    private array $snapshots;
    private bool $isComplete;
    private ?LoggerInterface $logger;

    /**
     * @param ?non-empty-string $identifier
     */
    public function __construct(?string $identifier = null, ?LoggerInterface $logger = null)
    {
        $identifier ??= (new LabelGenerator())->generate();
        $identifier = trim($identifier);
        '' !== $identifier || throw new InvalidArgument('The idenrifier must be a non-empty string.');

        $this->logger = $logger;
        $this->identifier = $identifier;
        $this->reset();
    }

    public function reset(): void
    {
        gc_collect_cycles();
        $this->snapshots = [];
        $this->isComplete = false;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function complete(): void
    {
        $this->isComplete = true;
    }

    public function isComplete(): bool
    {
        return $this->isComplete;
    }

    /**
     * @param non-empty-string $label
     *
     * @throws UnableToProfile if the marker is in complete state
     * @throws InvalidArgument if the label is invalid
     */
    public function mark(string $label): void
    {
        !$this->isComplete || throw new UnableToProfile('The instance is complete no further snapshot can be taken.');

        $newSnapshot = Snapshot::now(LabelGenerator::sanitize($label));
        ! $this->has($newSnapshot->label) || throw new InvalidArgument('The label "'.$label.'" already exists.');

        $from = array_key_last($this->snapshots);
        $lastSnapshot = $this->snapshots[$from] ?? null;
        (null === $lastSnapshot || $lastSnapshot->hrtime <= $newSnapshot->hrtime) || throw new InvalidArgument('"'.$from.'" must come before "'.$label.'".');

        $this->logger?->info('Marker ['.$this->identifier.'] snapshot for label: '.$label.'.', [
            'identifier' => $this->identifier,
            'label' => $newSnapshot->label,
            'snapshot' => $newSnapshot->toArray(),
        ]);

        $this->snapshots[$newSnapshot->label] = $newSnapshot;
    }

    public function get(string $label): Snapshot
    {
        return $this->snapshots[$label] ?? throw new InvalidArgument('The label "'.$label.'" does not exist.');
    }

    public function delta(string $from, string $to, ?string $metric = null): Summary|float
    {
        ($this->has($from) && $this->has($to)) || throw new InvalidArgument('The labels "'.$from.'" and/or "'.$to.'" do not exist.');

        $summary = new Summary($from.'_'.$to, $this->snapshots[$from], $this->snapshots[$to]);
        if (null === $metric) {
            return $summary;
        }

        $metrics = $summary->metrics->toArray();

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

    public function hasSnapshots(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Tells whether a report or a summary can be generated.
     */
    public function canSummarize(): bool
    {
        return 2 <= count($this->snapshots);
    }

    /**
     * @return Generator<Snapshot>
     */
    public function getIterator(): Traversable
    {
        yield from array_values($this->snapshots);
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     snapshots: list<SnapshotStat>
     * }
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'snapshots' => array_map(fn (Snapshot $snapshot) => $snapshot->toArray(), array_values($this->snapshots)),
        ];
    }

    /**
     * @return array{
     *     identifier: non-empty-string,
     *     snapshots: list<Snapshot>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier,
            'snapshots' => array_values($this->snapshots),
       ];
    }

    /**
     * @return list<non-empty-string>
     */
    public function labels(): array
    {
        return array_values(
            array_unique(
                array_column($this->snapshots, 'label')
            )
        );
    }

    /**
     *  Returns a sequence of Summary instances computed from each successive
     *  pair of snapshots (e.g., delta(label[0], label[1]), delta(label[1], label[2]), ...).
     *
     * @return iterable<Summary>
     */
    public function deltas(): iterable
    {
        $labels = $this->labels();
        $count = count($this->snapshots);
        for ($i = 1; $i < $count; $i++) {
            /** @var Summary $summary */
            $summary = $this->delta($labels[$i - 1], $labels[$i]);

            yield $summary;
        }
    }

    public function latest(): ?Snapshot
    {
        return $this->nth(-1);
    }

    public function first(): ?Snapshot
    {
        return $this->nth(0);
    }

    /**
     * Returns the snapshot at the given position, or null if out of range.
     * Negative offsets count from the end (e.g., -1 is latest).
     */
    public function nth(int $offset): ?Snapshot
    {
        if ([] === $this->snapshots) {
            return null;
        }

        $labels = $this->labels();
        if ($offset < 0) {
            $offset += count($labels);
        }

        if (!array_key_exists($offset, $labels)) {
            return null;
        }

        return$this->snapshots[$labels[$offset]];
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
    public function summary(?string $label = null): ?Summary
    {
        if (!$this->canSummarize()) {
            return null;
        }

        $from = array_key_first($this->snapshots);
        $to = array_key_last($this->snapshots);

        return new Summary(LabelGenerator::sanitize($label ?? $from.'_'.$to), $this->snapshots[$from], $this->snapshots[$to]);
    }

    /**
     * @param non-empty-string $label
     * @param ?non-empty-string $identifier
     */
    public static function start(string $label = 'start', ?string $identifier = null, ?LoggerInterface $logger = null): self
    {
        $marker = new self($identifier, $logger);
        $marker->mark($label);

        return $marker;
    }

    /**
     * Takes a final snapshot (default: 'end') and returns the summary profiling data.
     *
     * @param non-empty-string $label
     * @param ?non-empty-string $summaryLabel
     *
     * @throws UnableToProfile if the marker is in complete state
     * @throws InvalidArgument if labels are invalid
     */
    public function finish(string $label = 'end', ?string $summaryLabel = null): Summary
    {
        $this->hasSnapshots() || throw new UnableToProfile('Marking can not be finished; no starting snapshot found.');
        $this->mark($label);
        /** @var Summary $profiling */
        $profiling = $this->summary($summaryLabel);

        $this->logger?->info('Marker ['.$this->identifier.'] summary', [
            'identifier' => $this->identifier,
            'label' => $profiling->label,
            'metrics' => $profiling->metrics->toArray(),
        ]);

        return $profiling;
    }
}
