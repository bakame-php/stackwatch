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
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function str_replace;
use function strtolower;

use const JSON_PRETTY_PRINT;

/**
 * Represents an immutable profiling snapshot of CPU usage, memory usage, and call location
 * at a specific point in time.
 *
 * A snapshot contains:
 *  - A label (human-readable identifier for the measurement)
 *  - A timestamp (high-resolution and wall-clock time)
 *  - CPU usage statistics (user/system time in seconds and microseconds)
 *  - Memory usage statistics (current, real, and peak usage values)
 *  - The originating call location (file path and line number)
 *
 * Typical usage:
 *  - `Snapshot::now()` — capture the current execution state with optional custom label.
 *  - `Snapshot::fromArray()` — reconstruct a snapshot from serialized array data.
 *  - `toArray()` / `jsonSerialize()` — export the snapshot in a structured format.
 *  - `forHuman()` — obtain a human-readable representation of the snapshot data.
 *
 * Validation:
 *  - The label must be a non-empty string matching `LabelGenerator::sanitize()`.
 *  - CPU data must include all keys defined in {@see self::CPU_STAT}.
 *  - The timestamp must follow the {@see self::DATE_FORMAT} format.
 *
 * Immutability:
 *  - All properties are readonly and set only via the constructor.
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
 *     cpu: CpuMap,
 *     memory_usage: int,
 *     real_memory_usage: int,
 *     peak_memory_usage: int,
 *     real_peak_memory_usage: int,
 *     call_location: CallLocationMap,
 * }
 * @phpstan-type SnapshotHumanReadable array{
 *     label: non-empty-string,
 *     timestamp: string,
 *     memory_usage: string,
 *     real_memory_usage: string,
 *     peak_memory_usage: string,
 *     real_peak_memory_usage: string,
 *     cpu:string,
 *     call_location: string,
 * }
 */
final class Snapshot implements JsonSerializable
{
    public const DATE_FORMAT = "Y-m-d\TH:i:s.uP";

    /** @var CpuMap $default */
    private const CPU_STAT = [
        'ru_utime.tv_sec' => 0,
        'ru_utime.tv_usec' => 0,
        'ru_stime.tv_sec' => 0,
        'ru_stime.tv_usec' => 0,
    ];

    /**
     * @param non-empty-string $label
     * @param CpuMap $cpu
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
        public readonly CallLocation $callLocation = new CallLocation(),
    ) {
        LabelGenerator::sanitize($this->label) === $this->label || throw new InvalidArgument('the label `'.$this->label.'` is invalid');
        $missingKeys = array_diff_key(array_flip(array_keys(self::CPU_STAT)), $this->cpu);
        if ([] !== $missingKeys) {
            throw new InvalidArgument('The cpu data is missing the following keys: '.implode(', ', array_keys($missingKeys)));
        }
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
            'cpu' => 1,
            'memory_usage' => 1,
            'real_memory_usage' => 1,
            'peak_memory_usage' => 1,
            'real_peak_memory_usage' => 1,
            'call_location' => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        try {
            $datetime = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $data['timestamp']);
            false !== $datetime || throw new InvalidArgument('The timestamp uses an unsupported date format.');
        } catch (Throwable $exception) {
            throw new InvalidArgument('The "timestamp" is invalid.', previous: $exception);
        }

        $callLocation = new CallLocation();
        try {
            if (isset($data['call_location']) && is_array($data['call_location']) && [] !== $data['call_location']) {
                $callLocation = CallLocation::fromArray($data['call_location']);
            }
        } catch (Throwable $exception) {
            throw new InvalidArgument('The "timestamp" is invalid.', previous: $exception);
        }

        try {
            return new self(
                $data['label'],
                $datetime,
                $data['hrtime'],
                $data['cpu'],
                $data['memory_usage'],
                $data['real_memory_usage'],
                $data['peak_memory_usage'],
                $data['real_peak_memory_usage'],
                $callLocation,
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
        return new self(
            null === $label ? (new LabelGenerator())->generate() : LabelGenerator::sanitize($label),
            new DateTimeImmutable(),
            hrtime(true),
            self::getRawCpuData(),
            memory_get_usage(),
            memory_get_usage(true),
            memory_get_peak_usage(),
            memory_get_peak_usage(true),
            CallLocation::fromLastInternalCall(__NAMESPACE__, ['*Test.php']),
        );
    }

    /**
     * @return CpuMap
     */
    private static function getRawCpuData(): array
    {
        /** @var CpuMap|false $cpu */
        $cpu = getrusage();
        if (false !== $cpu) {
            return array_intersect_key($cpu, array_flip(array_keys(self::CPU_STAT)));
        }

        return self::CPU_STAT;
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
            'cpu' => $this->cpu,
            'memory_usage' => $this->memoryUsage,
            'real_memory_usage' => $this->realMemoryUsage,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'real_peak_memory_usage' => $this->realPeakMemoryUsage,
            'call_location' => $this->callLocation->toArray(),
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
            && $this->timestamp->format('U.u') === $other->timestamp->format('U.u')
            && $this->callLocation->equals($other->callLocation);
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
            'timestamp' => $this->timestamp->format(self::DATE_FORMAT),
            'memory_usage' => MemoryUnit::format($this->memoryUsage, 3),
            'real_memory_usage' => MemoryUnit::format($this->realMemoryUsage, 3),
            'peak_memory_usage' => MemoryUnit::format($this->peakMemoryUsage, 3),
            'real_peak_memory_usage' => MemoryUnit::format($this->realPeakMemoryUsage, 3),
            'cpu' => (string) json_encode($this->cpu, JSON_PRETTY_PRINT),
            'call_location' => (string) json_encode($this->callLocation, JSON_PRETTY_PRINT),
        ];

        if (null === $property) {
            return $humans;
        }

        $propertyNormalized = str_replace(' ', '_', strtolower($property));

        return $humans[$propertyNormalized] ?? throw new InvalidArgument('Unknown snapshot name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
    }
}
