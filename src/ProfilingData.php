<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;
use Random\RandomException;

use function bin2hex;
use function preg_match;
use function random_bytes;
use function strtolower;
use function trim;

/**
 * @phpstan-import-type MetricsStat from Metrics
 * @phpstan-import-type SnapshotStat from Snapshot
 */
final class ProfilingData implements JsonSerializable
{
    /** @var non-empty-string */
    public readonly string $label;
    public readonly Snapshot $start;
    public readonly Snapshot $end;
    public readonly Metrics $metrics;

    /**
     * @throws InvalidArgument
     */
    public function __construct(?string $label, Snapshot $start, Snapshot $end)
    {
        $label ??= self::randomLabel();
        $label = strtolower(trim($label));
        if ('' === $label) {
            $label = self::randomLabel();
        }

        1 === preg_match('/^[a-z0-9][a-z0-9_]*$/', $label) || throw new InvalidArgument('The label must start with a lowercased letter or a digit and only contain lowercased letters, digits, or underscores.');

        $this->label = $label;
        $this->start = $start;
        $this->end = $end;
        $this->metrics = Metrics::fromSnapshots($start, $end);
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
