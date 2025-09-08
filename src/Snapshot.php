<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use DateTimeImmutable;
use JsonSerializable;
use Throwable;

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function getrusage;
use function hrtime;
use function implode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function preg_replace;
use function strtolower;

/**
 * Represents an immutable profiling snapshot of CPU usage, memory usage, and call location
 * at a specific point in time.
 *
 * @phpstan-import-type CallLocationMap from CallLocation
 *
 * @phpstan-type CpuMap array{
 *     'ru_utime.tv_sec': int,
 *     'ru_utime.tv_usec': int,
 *     'ru_stime.tv_sec': int,
 *     'ru_stime.tv_usec': int,
 * }
 * @phpstan-type SnapshotMap array{
 *     label: non-empty-string,
 *     timestamp: string,
 *     hrtime: float,
 *     cpu_user_time: float,
 *     cpu_system_time: float,
 *     memory_usage: int,
 *     real_memory_usage: int,
 *     peak_memory_usage: int,
 *     real_peak_memory_usage: int,
 *     origin_path: ?string,
 *     origin_line: ?int,
 * }
 * @phpstan-type SnapshotHumanReadable array{
 *     label: non-empty-string,
 *     timestamp: string,
 *     memory_usage: string,
 *     real_memory_usage: string,
 *     peak_memory_usage: string,
 *     real_peak_memory_usage: string,
 *     cpu_user_time: string,
 *     cpu_system_time: string,
 *     origin_path: string,
 *     origin_line: string,
 * }
 */
final class Snapshot implements JsonSerializable
{
    public const DATE_FORMAT = "Y-m-d\TH:i:s.uP";

    /**
     * @param non-empty-string $label
     */
    public function __construct(
        public readonly string $label,
        public readonly DateTimeImmutable $timestamp,
        public readonly float $hrtime,
        public readonly float $cpuUserTime,
        public readonly float $cpuSystemTime,
        public readonly int $memoryUsage,
        public readonly int $realMemoryUsage,
        public readonly int $peakMemoryUsage,
        public readonly int $realPeakMemoryUsage,
        public readonly ?string $originPath = null,
        public readonly ?int $originLine = null,
    ) {
        LabelGenerator::sanitize($this->label) === $this->label || throw new InvalidArgument('the label `'.$this->label.'` is invalid');
    }

    /**
     * @param SnapshotMap $data
     *
     * @throws InvalidArgument
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key([
            'label' => 1,
            'timestamp' => 1,
            'hrtime' => 1,
            'cpu_user_time' => 1,
            'cpu_system_time' => 1,
            'memory_usage' => 1,
            'real_memory_usage' => 1,
            'peak_memory_usage' => 1,
            'real_peak_memory_usage' => 1,
            'origin_path' => 1,
            'origin_line' => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        try {
            $datetime = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $data['timestamp']);
            false !== $datetime || throw new InvalidArgument('The timestamp uses an unsupported date format.');
        } catch (Throwable $exception) {
            throw new InvalidArgument('The "timestamp" is invalid.', previous: $exception);
        }

        try {
            $callLocation = new CallLocation($data['origin_path'] ?? null, $data['origin_line'] ?? null);
        } catch (Throwable $exception) {
            throw new InvalidArgument('The "origin" keys are invalid.', previous: $exception);
        }

        try {
            return new self(
                $data['label'],
                $datetime,
                $data['hrtime'],
                $data['cpu_user_time'],
                $data['cpu_system_time'],
                $data['memory_usage'],
                $data['real_memory_usage'],
                $data['peak_memory_usage'],
                $data['real_peak_memory_usage'],
                $callLocation->path,
                $callLocation->line,
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a snapshot from the payload', previous: $exception);
        }
    }

    /**
     * @param ?non-empty-string $label
     *
     * @throws UnableToProfile
     */
    public static function now(?string $label = null): self
    {
        $callLocation = CallLocation::fromLastInternalCall(__NAMESPACE__, ['*Test.php']);
        [$cpuUserTime, $cpuSystemTime] = self::getRawCpuData();

        return new self(
            null === $label ? (new LabelGenerator())->generate() : LabelGenerator::sanitize($label),
            new DateTimeImmutable(),
            hrtime(true),
            $cpuUserTime,
            $cpuSystemTime,
            memory_get_usage(),
            memory_get_usage(true),
            memory_get_peak_usage(),
            memory_get_peak_usage(true),
            $callLocation->path,
            $callLocation->line,
        );
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function getRawCpuData(): array
    {
        /** @var CpuMap $default */
        static $default = [
            'ru_utime.tv_sec' => 0,
            'ru_utime.tv_usec' => 0,
            'ru_stime.tv_sec' => 0,
            'ru_stime.tv_usec' => 0,
        ];

        /** @var CpuMap|false $cpu */
        $cpu = getrusage();
        $cpuData = false !== $cpu ? array_intersect_key($cpu, array_flip(array_keys($default))) : $default;

        return [
            ($cpuData['ru_utime.tv_sec'] * 1_000 ** 3) + ($cpuData['ru_utime.tv_usec'] * 1_000),
            ($cpuData['ru_stime.tv_sec'] * 1_000 ** 3) + ($cpuData['ru_stime.tv_usec'] * 1_000),
        ];
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self
            && $other->label === $this->label
            && $this->hrtime === $other->hrtime
            && $this->cpuUserTime === $other->cpuUserTime
            && $this->cpuSystemTime === $other->cpuSystemTime
            && $this->memoryUsage === $other->memoryUsage
            && $this->peakMemoryUsage === $other->peakMemoryUsage
            && $this->realMemoryUsage === $other->realMemoryUsage
            && $this->realPeakMemoryUsage === $other->realPeakMemoryUsage
            && $this->timestamp->format('U.u') === $other->timestamp->format('U.u')
            && $this->originPath === $other->originPath
            && $this->originLine === $other->originLine;
    }

    public function isBefore(Snapshot $other): bool
    {
        return -1 === $this->compareTo($other);
    }

    public function isAfter(Snapshot $other): bool
    {
        return 1 === $this->compareTo($other);
    }

    public function isAtSameTime(Snapshot $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    public function isAfterOrAtSameTime(Snapshot $other): bool
    {
        return 0 <= $this->compareTo($other);
    }

    public function isBeforeOrAtSameTime(Snapshot $other): bool
    {
        return 0 >= $this->compareTo($other);
    }

    public function compareTo(Snapshot $other): int
    {
        return $this->hrtime <=> $other->hrtime;
    }

    /**
     * @return SnapshotMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return SnapshotMap
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'timestamp' => $this->timestamp->format(self::DATE_FORMAT),
            'hrtime' => $this->hrtime,
            'cpu_user_time' => $this->cpuUserTime,
            'cpu_system_time' => $this->cpuSystemTime,
            'memory_usage' => $this->memoryUsage,
            'real_memory_usage' => $this->realMemoryUsage,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'real_peak_memory_usage' => $this->realPeakMemoryUsage,
            'origin_path' => $this->originPath,
            'origin_line' => $this->originLine,
        ];
    }

    /**
     * @throws InvalidArgument if the specified property is not supported
     *
     * @return SnapshotHumanReadable
     */
    public function toHuman(): array
    {
        return [
            'label' => $this->label,
            'timestamp' => $this->timestamp->format(self::DATE_FORMAT),
            'memory_usage' => MemoryUnit::format($this->memoryUsage, 3),
            'real_memory_usage' => MemoryUnit::format($this->realMemoryUsage, 3),
            'peak_memory_usage' => MemoryUnit::format($this->peakMemoryUsage, 3),
            'real_peak_memory_usage' => MemoryUnit::format($this->realPeakMemoryUsage, 3),
            'cpu_user_time' => DurationUnit::format($this->cpuUserTime, 3),
            'cpu_system_time' => DurationUnit::format($this->cpuSystemTime, 3),
            'origin_path' => (string) $this->originPath,
            'origin_line' => (string) $this->originLine,
        ];
    }

    /**
     * Returns a human-readable version of a property.
     *
     * @param non-empty-string $property
     *
     * @throws InvalidArgument if the property is unknown
     */
    public function human(string $property): string
    {
        $humans = $this->toHuman();
        $propertyNormalized = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
        return $humans[$propertyNormalized] ?? throw new InvalidArgument('Unknown snapshot property name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
    }

    public function dump(): self
    {
        (new ViewExporter())->exportSnapshot($this);

        return $this;
    }

    public function dd(): never
    {
        CallbackDumper::dd($this->dump(...));
    }
}
