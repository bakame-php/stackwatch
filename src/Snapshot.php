<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use DateTimeImmutable;
use JsonSerializable;
use ValueError;

use function getrusage;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;

/**
 * @phpstan-type SnapshotMetrics array{
 *     timestamp: string,
 *     metrics: array{
 *         execution_time: float,
 *         cpu: array<string, int|float>,
 *         memory_usage: int,
 *         real_memory_usage: int,
 *         peak_memory_usage: int,
 *         real_peak_memory_usage: int
 *     }
 * }
 */
final class Snapshot implements JsonSerializable
{
    /**
     * @param array<string, int|float> $cpu
     */
    public function __construct(
        public readonly DateTimeImmutable $timestamp,
        public readonly float $executionTime,
        public readonly array $cpu,
        public readonly int $memoryUsage,
        public readonly int $realMemoryUsage,
        public readonly int $peakMemoryUsage,
        public readonly int $realPeakMemoryUsage,
    ) {
    }

    public static function now(): self
    {
        $cpu = getrusage();
        false !== $cpu || throw new ValueError('Unable to take a snapshot because of the CPU.');

        return new self(
            new DateTimeImmutable(),
            microtime(true),
            $cpu,
            memory_get_usage(),
            memory_get_usage(true),
            memory_get_peak_usage(),
            memory_get_peak_usage(true),
        );
    }

    /**
     * @return SnapshotMetrics
     */
    public function jsonSerialize(): array
    {
        return $this->metrics();
    }

    /**
     * @return SnapshotMetrics
     */
    public function metrics(): array
    {
        return [
            'timestamp' => $this->timestamp->format("Y-m-d\TH:i:s.uP"),
            'metrics' => [
                'execution_time' => $this->executionTime,
                'cpu' => $this->cpu,
                'memory_usage' => $this->memoryUsage,
                'real_memory_usage' => $this->realMemoryUsage,
                'peak_memory_usage' => $this->peakMemoryUsage,
                'real_peak_memory_usage' => $this->realPeakMemoryUsage,
            ],
        ];
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self
            && $this->executionTime === $other->executionTime
            && $this->cpu === $other->cpu
            && $this->memoryUsage === $other->memoryUsage
            && $this->peakMemoryUsage === $other->peakMemoryUsage
            && $this->realMemoryUsage === $other->realMemoryUsage
            && $this->realPeakMemoryUsage === $other->realPeakMemoryUsage
            && $this->timestamp == $other->timestamp;
    }
}
