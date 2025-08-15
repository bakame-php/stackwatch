<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;
use Throwable;

use function array_diff_key;
use function array_keys;
use function implode;

/**
 * @phpstan-import-type CallRangeMap from CallRange
 * @phpstan-import-type MetricsMap from Metrics
 * @phpstan-import-type SnapshotMap from Snapshot
 * @phpstan-type SummaryMap array{
 *     label: non-empty-string,
 *     snapshots: array{0: SnapshotMap, 1: SnapshotMap},
 *     range: CallRangeMap,
 *     metrics: MetricsMap,
 * }
 */
final class Summary implements JsonSerializable
{
    public readonly Metrics $metrics;
    public readonly Snapshot $start;
    public readonly Snapshot $end;
    public readonly CallRange $range;
    /** @var non-empty-string */
    public readonly string $label;

    /**
     * @param non-empty-string $label
     *
     * @throws InvalidArgument
     */
    public function __construct(string $label, Snapshot $start, Snapshot $end)
    {
        $this->metrics = Metrics::fromSnapshots($start, $end);
        $this->start = $start;
        $this->end = $end;
        $this->label = LabelGenerator::sanitize($label);
        $this->range = CallRange::fromSnapshots($start, $end);
    }

    /**
     * @return SummaryMap
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'snapshots' => [
                $this->start->toArray(),
                $this->end->toArray(),
            ],
            'range' => $this->range->toArray(),
            'metrics' => $this->metrics->toArray(),
        ];
    }

    /**
     * @param SummaryMap $data
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key(['label' => 1, 'snapshots' => 1], $data);
        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));
        (is_array($data['snapshots']) && 2 === count($data['snapshots'])) || throw new InvalidArgument('The "snapshots" array must contain exactly 2 elements.');

        try {
            return new self(
                $data['label'],
                Snapshot::fromArray($data['snapshots'][0]),
                Snapshot::fromArray($data['snapshots'][1]),
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a summary from the payload', previous: $exception);
        }
    }

    /**
     * @return array{
     *     label: non-empty-string,
     *     snapshots: array{0: Snapshot, 1: Snapshot},
     *     range: CallRange,
     *     metrics: Metrics
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'snapshots' => [
                $this->start,
                $this->end,
            ],
            'range' => $this->range,
            'metrics' => $this->metrics,
        ];
    }
}
