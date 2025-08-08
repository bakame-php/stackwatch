<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;
use Stringable;
use Throwable;

use function array_diff_key;
use function array_keys;
use function implode;
use function sprintf;

/**
 * @phpstan-import-type MetricsStat from Metrics
 * @phpstan-import-type SnapshotStat from Snapshot
 * @phpstan-type SummaryStat array{
 *     label: non-empty-string,
 *     snapshots: array{0: SnapshotStat, 1: SnapshotStat},
 *     metrics: MetricsStat
 * }
 */
final class Summary implements JsonSerializable, Stringable
{
    public readonly Metrics $metrics;
    public readonly Snapshot $start;
    public readonly Snapshot $end;
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
    }

    /**
     * @return SummaryStat
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'snapshots' => [
                $this->start->toArray(),
                $this->end->toArray(),
            ],
            'metrics' => $this->metrics->toArray(),
        ];
    }

    /**
     * @param SummaryStat $data
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key(['label' => 1, 'snapshots' => 1], $data);
        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

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
            'metrics' => $this->metrics,
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            "Summary '%s':\nStart Snapshot:\n%s\nEnd Snapshot:\n%s\nMetrics:\n%s",
            $this->label,
            $this->start,
            $this->end,
            $this->metrics
        );
    }
}
