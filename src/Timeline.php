<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

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
use function array_map;
use function array_search;
use function array_values;
use function count;
use function trim;

/**
 * @implements IteratorAggregate<non-empty-string, Snapshot>
 * @phpstan-import-type SnapshotMap from Snapshot
 */
final class Timeline implements Countable, IteratorAggregate, JsonSerializable
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
        $identifier = trim($identifier ?? (new LabelGenerator())->generate());
        '' !== $identifier || throw new InvalidArgument('The identifier must be a non-empty string.');

        $this->identifier = $identifier;
        $this->logger = $logger;
        $this->reset();
    }

    public function reset(): void
    {
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

    public function count(): int
    {
        return count($this->snapshots);
    }

    public function hasNoSnapshots(): bool
    {
        return [] === $this->snapshots;
    }

    public function hasSnapshots(): bool
    {
        return ! $this->hasNoSnapshots();
    }

    /**
     * Tells whether a report or a summary can be generated.
     */
    public function hasEnoughSnapshots(): bool
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
     *     snapshots: list<SnapshotMap>
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
        return array_keys($this->snapshots);
    }

    public function get(string $label): Snapshot
    {
        return $this->snapshots[$label] ?? throw new InvalidArgument('The label "'.$label.'" does not exist.');
    }

    /**
     * @param non-empty-string $label
     *
     * @throws UnableToProfile if the timeline is in complete state
     * @throws InvalidArgument if the label is invalid
     */
    public function capture(string $label): void
    {
        !$this->isComplete || throw new UnableToProfile('The instance is complete no further snapshot can be taken.');

        $newSnapshot = Snapshot::now(LabelGenerator::sanitize($label));
        ! $this->hasLabel($newSnapshot->label) || throw new InvalidArgument('The label "'.$label.'" already exists.');

        $this->log('snapshot for label: '.$newSnapshot->label, $newSnapshot->toArray());

        $this->snapshots[$newSnapshot->label] = $newSnapshot;
    }

    /**
     * Returns the summary between two snapshots.
     * If the second snapshot is not defined, the latest snapshot will be used.
     *
     * @param string $from the first snapshot label
     * @param ?string $to the last snapshot to compare from if missing the next label after the from label if it exists or from itself
     *
     * @throws InvalidArgument If the labels could not be found
     */
    public function delta(string $from, ?string $to = null): Summary
    {
        $this->hasLabel($from) || throw new InvalidArgument('The label "'.$from.'" do not exist.');
        $keys = $this->labels();
        if (null === $to) {
            $pos = array_search($from, $keys, true);
            if (false !== $pos) {
                $to = $keys[(int) $pos + 1] ?? null;
            }

            if (null === $to) {
                return new Summary($from.'_'.$from, $this->snapshots[$from], $this->snapshots[$from]);
            }
        }

        $this->hasLabel($to) || throw new InvalidArgument('The label "'.$to.'" do not exist.');

        return new Summary($from.'_'.$to, $this->snapshots[$from], $this->snapshots[$to]);
    }

    public function metrics(string $from, ?string $to = null): Metrics
    {
        return $this->delta($from, $to)->metrics;
    }

    /**
     *  Returns a sequence of Summary instances computed from each successive pair of snapshots.
     *
     * @return iterable<Summary>
     */
    public function deltas(): iterable
    {
        $labels = $this->labels();
        $count = count($this->snapshots);
        for ($i = 1; $i < $count; $i++) {
            yield $this->delta($labels[$i - 1], $labels[$i]);
        }
    }

    /**
     * Returns the latest snapshot or null if there are none yet taken.
     */
    public function latest(): ?Snapshot
    {
        return $this->nth(-1);
    }

    /**
     * Returns the first snapshot taken or null if there are none yet taken.
     */
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

        return $this->snapshots[$labels[$offset]];
    }

    /**
     * Tells whether the label is present in the current profiler cache.
     */
    public function hasLabel(string $label): bool
    {
        return array_key_exists($label, $this->snapshots);
    }

    /**
     * Returns a summary between the first and last available snapshots.
     *
     * @param ?non-empty-string $label
     */
    public function summarize(?string $label = null): Summary
    {
        $from = array_key_first($this->snapshots);
        $to = array_key_last($this->snapshots);

        return new Summary(LabelGenerator::sanitize($label ?? $from.'_'.$to), $this->snapshots[$from], $this->snapshots[$to]);
    }

    /**
     * Creates a new instance and automatically take the first snapshot.
     *
     * @param non-empty-string $label
     * @param ?non-empty-string $identifier
     */
    public static function start(string $label = 'start', ?string $identifier = null, ?LoggerInterface $logger = null): self
    {
        $timeline = new self($identifier, $logger);
        $timeline->capture($label);

        return $timeline;
    }

    /**
     * Takes a snapshot and returns the summary profiling data from the start until the new label.
     *
     * @param non-empty-string $label
     * @param ?non-empty-string $summaryLabel
     *
     * @throws UnableToProfile if the timeline is in complete state
     * @throws InvalidArgument if labels are invalid
     */
    public function take(string $label, ?string $summaryLabel = null): Summary
    {
        $this->capture($label);

        return $this->summarize($summaryLabel);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $message, array $context = []): void
    {
        $this->logger?->info('Timeline ['.$this->identifier.'] '.$message, [
            'identifier' => $this->identifier,
            ...$context,
        ]);
    }
}
