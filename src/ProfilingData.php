<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;

use function preg_match;

/**
 * @phpstan-import-type MetricsStat from Metrics
 * @phpstan-import-type SnapshotStat from Snapshot
 * @phpstan-type ProfilingDataStat array{label: non-empty-string, start: SnapshotStat, end: SnapshotStat, metrics: MetricsStat}
 */
final class ProfilingData implements JsonSerializable
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
    public function __construct(Snapshot $start, Snapshot $end, string $label)
    {
        1 === preg_match('/^[a-z0-9][a-z0-9_]*$/', $label) || throw new InvalidArgument('The label must start with a lowercased letter or a digit and only contain lowercased letters, digits, or underscores.');

        $this->metrics = Metrics::fromSnapshots($start, $end);
        $this->start = $start;
        $this->end = $end;
        $this->label = $label;
    }

    /**
     * @return ProfilingDataStat
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'start' => $this->start->toArray(),
            'end' => $this->end->toArray(),
            'metrics' => $this->metrics->toArray(),
        ];
    }

    /**
     * @return array{label: non-empty-string, start: Snapshot, end: Snapshot, metrics: Metrics}
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'start' => $this->start,
            'end' => $this->end,
            'metrics' => $this->metrics,
        ];
    }
}
