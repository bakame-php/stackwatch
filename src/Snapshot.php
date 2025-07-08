<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use DateTimeImmutable;
use JsonSerializable;

use function array_keys;
use function getrusage;
use function hrtime;
use function implode;
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;

use const JSON_PRETTY_PRINT;

/**
 * @phpstan-type CpuStat array{
 *     'ru_utime.tv_sec': int,
 *     'ru_utime.tv_usec': int,
 *     'ru_stime.tv_sec': int,
 *     'ru_stime.tv_usec': int,
 *     ru_inblock: int,
 *     ru_oublock: int,
 * }
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
 * @phpstan-type SnapshotHumanReadable array{
 *      timestamp: string,
 *      memory_usage: string,
 *      real_memory_usage: string,
 *      peak_memory_usage: string,
 *      real_peak_memory_usage: string,
 *      cpu:string
 * }
 *
 */
final class Snapshot implements JsonSerializable
{
    /**
     * @param CpuStat $cpu
     */
    public function __construct(
        public readonly DateTimeImmutable $timestamp,
        public readonly float $hrtime,
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
        if (false === $cpu) {
            ! Environment::current()->isUnixLike() || throw new UnableToProfile('Unable to get the current resource usage.');

            $cpu = [
                'ru_utime.tv_sec' => 0,
                'ru_utime.tv_usec' => 0,
                'ru_stime.tv_sec' => 0,
                'ru_stime.tv_usec' => 0,
                'ru_inblock' => 0,
                'ru_oublock' => 0,
            ];
        }

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
        return $this->toArray();
    }

    /**
     * @return SnapshotStat
     */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp->format("Y-m-d\TH:i:s.uP"),
            'metrics' => [
                'execution_time' => $this->hrtime,
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
            && $this->hrtime === $other->hrtime
            && $this->cpu === $other->cpu
            && $this->memoryUsage === $other->memoryUsage
            && $this->peakMemoryUsage === $other->peakMemoryUsage
            && $this->realMemoryUsage === $other->realMemoryUsage
            && $this->realPeakMemoryUsage === $other->realPeakMemoryUsage
            && $this->timestamp->format('U.u') === $other->timestamp->format('U.u');
    }

    /**
     * @throws InvalidArgument if the specified property is not supported
     *
     * @return SnapshotHumanReadable|string
     */
    public function forHuman(?string $property = null): array|string
    {
        $humans = [
            'timestamp' => $this->timestamp->format('Y-m-d\TH:i:s.uP'),
            'memory_usage' => MemoryUnit::format($this->memoryUsage, 3),
            'real_memory_usage' => MemoryUnit::format($this->realMemoryUsage, 3),
            'peak_memory_usage' => MemoryUnit::format($this->peakMemoryUsage, 3),
            'real_peak_memory_usage' => MemoryUnit::format($this->realPeakMemoryUsage, 3),
            'cpu' => (string) json_encode($this->cpu, JSON_PRETTY_PRINT),
        ];

        if (null === $property) {
            return $humans;
        }

        return $humans[$property] ?? throw new InvalidArgument('Unknown snapshot name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
    }
}
