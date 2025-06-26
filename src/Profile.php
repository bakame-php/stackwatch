<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;

use function bin2hex;
use function preg_match;
use function random_bytes;
use function strtolower;
use function trim;

/**
 * @phpstan-type ProfileMetrics array{
 *     label: string,
 *     start: ?Snapshot,
 *     end: ?Snapshot,
 *     metrics: array{
 *         cpu_time: float,
 *         memory_usage: float,
 *         real_memory_usage: float,
 *         peak_memory_usage: float,
 *         real_peak_memory_usage: float
 *     }
 * }
 */
final class Profile implements JsonSerializable
{
    private ?Snapshot $start = null;
    private ?Snapshot $end = null;
    private float $cpuTime = 0;
    private float $executionTime = 0;
    private float $memoryUsage = 0;
    private float $peakMemoryUsage = 0;
    private float $realMemoryUsage = 0;
    private float $realPeakMemoryUsage = 0;
    /** @var non-empty-string */
    private readonly string $label;

    public function __construct(?string $label = null)
    {
        $label ??= bin2hex(random_bytes(6));
        $label = strtolower(trim($label));
        if ('' === $label) {
            $label = bin2hex(random_bytes(6));
        }

        1 === preg_match('/^[a-z0-9][a-z0-9_]*$/', $label) || throw new InvalidProfileState('The label must start with a lowercased letter or a digit and only contain lowercased letters, digits, or underscores.');

        $this->label = $label;
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
    public function metrics(): array
    {
        return [
            'label' => $this->label,
            'start' => $this->start,
            'end' => $this->end,
            'metrics' => [
                'cpu_time' => $this->cpuTime,
                'memory_usage' => $this->memoryUsage,
                'real_memory_usage' => $this->realMemoryUsage,
                'peak_memory_usage' => $this->peakMemoryUsage,
                'real_peak_memory_usage' => $this->realPeakMemoryUsage,
            ],
        ];
    }

    /**
     * @return ProfileMetrics
     */
    public function jsonSerialize(): array
    {
        return $this->metrics();
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
        $this->setMetrics();
    }

    private static function calculateCpuTime(Snapshot $start, Snapshot $end): float
    {
        $cpuStart = $start->cpu;
        $cpuEnd = $end->cpu;

        $utime = ($cpuEnd['ru_utime.tv_sec'] - $cpuStart['ru_utime.tv_sec']) + ($cpuEnd['ru_utime.tv_usec'] - $cpuStart['ru_utime.tv_usec']) / 1_000_000;
        $stime = ($cpuEnd['ru_stime.tv_sec'] - $cpuStart['ru_stime.tv_sec']) + ($cpuEnd['ru_stime.tv_usec'] - $cpuStart['ru_stime.tv_usec']) / 1_000_000;

        return $utime + $stime;
    }

    private function assertHasEnded(): void
    {
        $this->hasEnded() || throw new InvalidProfileState('Profiling must be completed before accessing statistics.');
    }

    public function executionTime(): float
    {
        $this->assertHasEnded();

        return $this->executionTime;
    }

    public function cpuTime(): float
    {
        $this->assertHasEnded();

        return $this->cpuTime;
    }

    public function memoryUsage(): float
    {
        $this->assertHasEnded();

        return $this->memoryUsage;
    }

    public function realMemoryUsage(): float
    {
        $this->assertHasEnded();

        return $this->realMemoryUsage;
    }

    public function peakMemoryUsage(): float
    {
        $this->assertHasEnded();

        return $this->peakMemoryUsage;
    }

    public function realPeakMemoryUsage(): float
    {
        $this->assertHasEnded();

        return $this->realPeakMemoryUsage;
    }

    public function start(): Snapshot
    {
        return $this->start ?? throw new InvalidProfileState('Profiling has yet to be started.');
    }

    public function end(): Snapshot
    {
        return $this->end ?? throw new InvalidProfileState('Profiling has yet to be ended.');
    }

    private function setMetrics(): void
    {
        $start = $this->start();
        $end = $this->end();

        $this->cpuTime = self::calculateCpuTime($start, $end);
        $this->executionTime = $end->executionTime - $start->executionTime;
        $this->memoryUsage = $end->memoryUsage - $start->memoryUsage;
        $this->peakMemoryUsage = $end->peakMemoryUsage - $start->peakMemoryUsage;
        $this->realMemoryUsage = $end->realMemoryUsage - $start->realMemoryUsage;
        $this->realPeakMemoryUsage = $end->realPeakMemoryUsage - $start->realPeakMemoryUsage;
    }
}
