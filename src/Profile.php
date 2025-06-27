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
    private readonly string $label;
    private ?Snapshot $start = null;
    private ?Snapshot $end = null;
    private Metrics $metrics;

    /**
     * @throws InvalidArgument
     * @throws RandomException
     */
    public function __construct(?string $label = null)
    {
        $label ??= self::randomLabel();
        $label = strtolower(trim($label));
        if ('' === $label) {
            $label = self::randomLabel();
        }

        1 === preg_match('/^[a-z0-9][a-z0-9_]*$/', $label) || throw new InvalidArgument('The label must start with a lowercased letter or a digit and only contain lowercased letters, digits, or underscores.');

        $this->label = $label;
        $this->metrics = Metrics::none();
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
     * @return non-empty-string
     */
    public function label(): string
    {
        return $this->label;
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

    public function hasNotBegun(): bool
    {
        return null === $this->start;
    }

    public function hasBegun(): bool
    {
        return null !== $this->start;
    }

    public function isRunning(): bool
    {
        return null !== $this->start
            && null === $this->end;
    }

    public function hasEnded(): bool
    {
        return null !== $this->start
            && null !== $this->end;
    }

    public function beginProfiling(): void
    {
        (null === $this->start && null === $this->end) || throw new InvalidProfileState('Profiling cannot be started if it has already started.');

        $this->start = Snapshot::now();
    }

    public function endProfiling(): void
    {
        (null !== $this->start && null === $this->end) || throw new InvalidProfileState('Profiling cannot be ended if it is not running.');

        $this->end = Snapshot::now();
        $this->metrics = Metrics::fromSnapshots($this->start, $this->end);
    }

    /**
     * @throws InvalidProfileState
     */
    public function start(): Snapshot
    {
        return $this->start ?? throw new InvalidProfileState('Profiling has yet to be started.');
    }

    /**
     * @throws InvalidProfileState
     */
    public function end(): Snapshot
    {
        return $this->end ?? throw new InvalidProfileState('Profiling has yet to be ended.');
    }

    /**
     * @throws InvalidProfileState
     */
    public function metrics(): Metrics
    {
        $this->hasEnded() || throw new InvalidProfileState('Profiling has yet to be ended.');

        return $this->metrics;
    }
}
