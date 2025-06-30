<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use DateTimeImmutable;
use JsonSerializable;

use function getrusage;
use function hrtime;
use function memory_get_peak_usage;
use function memory_get_usage;

/**
 * @phpstan-type CpuStat array{'ru_utime.tv_sec': int, 'ru_utime.tv_usec': int, 'ru_stime.tv_sec': int, 'ru_stime.tv_usec': int}
 * @phpstan-type SnapshotStat array{
 *     timestamp: string,
 *     metrics: array{
 *         execution_time: float,
 *         cpu: CpuStat,
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
     * @param CpuStat $cpu
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

    /**
     * @throws UnableToProfile
     */
    public static function now(): self
    {
        /** @var CpuStat|false $cpu */
        $cpu = getrusage();
        false !== $cpu || throw new UnableToProfile('Unable to get the current resource usage.');

        return new self(
            new DateTimeImmutable(),
            hrtime(true),
            $cpu,
            memory_get_usage(),
            memory_get_usage(true),
            memory_get_peak_usage(),
            memory_get_peak_usage(true),
        );
    }

    /**
     * @return SnapshotStat
     */
    public function jsonSerialize(): array
    {
        return $this->stats();
    }

    /**
     * @return SnapshotStat
     */
    public function stats(): array
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
            && $this->timestamp->format('U.u') === $other->timestamp->format('U.u');
    }
}
