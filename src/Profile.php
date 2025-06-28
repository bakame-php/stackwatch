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
 * @phpstan-type ProfileMetrics array{
 *     label: string,
 *     start: ?Snapshot,
 *     end: ?Snapshot,
 *     metrics: MetricsStat
 * }
 */
final class Profile implements JsonSerializable
{
    /** @var non-empty-string */
    public readonly string $label;
    public readonly Snapshot $start;
    public readonly Snapshot $end;
    public readonly Metrics $metrics;

    /**
     * @throws InvalidArgument
     * @throws RandomException
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
     * @throws RandomException
     *
     * @return non-empty-string
     */
    public static function randomLabel(): string
    {
        return bin2hex(random_bytes(6));
    }

    /**
     * @return ProfileMetrics
     */
    public function stats(): array
    {
        return [
            'label' => $this->label,
            'start' => $this->start,
            'end' => $this->end,
            'metrics' => $this->metrics->stats(),
        ];
    }

    /**
     * @return ProfileMetrics
     */
    public function jsonSerialize(): array
    {
        return $this->stats();
    }
}
