<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use DateTimeImmutable;
use JsonSerializable;

use function array_flip;
use function array_intersect_key;
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
 * }
 * @phpstan-type SnapshotStat array{
 *     label: non-empty-string,
 *     timestamp: string,
 *     hrtime: float,
 *     cpu: CpuStat,
 *     memory_usage: int,
 *     real_memory_usage: int,
 *     peak_memory_usage: int,
 *     real_peak_memory_usage: int
 * }
 * @phpstan-type SnapshotHumanReadable array{
 *     label: non-empty-string,
 *     timestamp: string,
 *     memory_usage: string,
 *     real_memory_usage: string,
 *     peak_memory_usage: string,
 *     real_peak_memory_usage: string,
 *     cpu:string
 * }
 *
 */
final class Snapshot implements JsonSerializable
{
    /** @var CpuStat $default */
    private const CPU_STAT = [
        'ru_utime.tv_sec' => 0,
        'ru_utime.tv_usec' => 0,
        'ru_stime.tv_sec' => 0,
        'ru_stime.tv_usec' => 0,
    ];

    /**
     * @param non-empty-string $label
     * @param CpuStat $cpu
     */
    public function __construct(
        public readonly string $label,
        public readonly DateTimeImmutable $timestamp,
        public readonly float $hrtime,
        public readonly array $cpu,
        public readonly int $memoryUsage,
        public readonly int $realMemoryUsage,
        public readonly int $peakMemoryUsage,
        public readonly int $realPeakMemoryUsage,
    ) {
        '' !== $label || throw new InvalidArgument('The label can not be empty.');
        $missingKeys = array_diff_key(array_flip(array_keys(self::CPU_STAT)), $this->cpu);
        if ([] !== $missingKeys) {
            throw new InvalidArgument('The cpu data is missing the following keys: '.implode(', ', array_keys($missingKeys)));
        }
    }

    /**
     * @param non-empty-string $label
     *
     * @throws UnableToProfile
     */
    public static function now(string $label): self
    {
        return new self(
            Label::fromString($label),
            new DateTimeImmutable(),
            hrtime(true),
            self::getRawCpuData(),
            memory_get_usage(),
            memory_get_usage(true),
            memory_get_peak_usage(),
            memory_get_peak_usage(true),
        );
    }

    /**
     * @return CpuStat
     */
    public static function getRawCpuData(): array
    {
        /** @var CpuStat|false $cpu */
        $cpu = getrusage();
        if (false !== $cpu) {
            return array_intersect_key($cpu, array_flip(array_keys(self::CPU_STAT)));
        }

        return self::CPU_STAT;
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
            'label' => $this->label,
            'timestamp' => $this->timestamp->format("Y-m-d\TH:i:s.uP"),
            'hrtime' => $this->hrtime,
            'cpu' => $this->cpu,
            'memory_usage' => $this->memoryUsage,
            'real_memory_usage' => $this->realMemoryUsage,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'real_peak_memory_usage' => $this->realPeakMemoryUsage,
        ];
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self
            && $other->label === $this->label
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
            'label' => $this->label,
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
