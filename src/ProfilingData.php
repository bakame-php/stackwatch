<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;

use function preg_match;

/**
 * @phpstan-import-type MetricsStat from Metrics
 * @phpstan-import-type SnapshotStat from Snapshot
 */
final class ProfilingData implements JsonSerializable
{
    public readonly Metrics $metrics;

    /**
     * @param non-empty-string $label
     *
     * @throws InvalidArgument
     */
    public function __construct(
        public readonly Snapshot $start,
        public readonly Snapshot $end,
        public readonly string $label
    ) {
        1 === preg_match('/^[a-z0-9][a-z0-9_]*$/', $this->label) || throw new InvalidArgument('The label must start with a lowercased letter or a digit and only contain lowercased letters, digits, or underscores.');

        $this->metrics = Metrics::fromSnapshots($this->start, $this->end);
    }

    /**
     * @return array{label: non-empty-string, start: SnapshotStat, end: SnapshotStat, metrics: MetricsStat}
     */
    public function stats(): array
    {
        return [
            'label' => $this->label,
            'start' => $this->start->stats(),
            'end' => $this->end->stats(),
            'metrics' => $this->metrics->stats(),
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
