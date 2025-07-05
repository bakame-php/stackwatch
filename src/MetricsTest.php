<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function hrtime;

#[CoversClass(Metrics::class)]
final class MetricsTest extends TestCase
{
    /**
     * @param non-empty-string $label
     */
    private function createProfilingData(string $label): ProfilingData
    {
        $start = new Snapshot(new DateTimeImmutable(), hrtime(true), [
            'ru_utime.tv_sec' => 1,
            'ru_stime.tv_sec' => 1,
            'ru_utime.tv_usec' => 1,
            'ru_stime.tv_usec' => 1,
        ], 1000, 2000, 3000, 4000);
        $end = new Snapshot(new DateTimeImmutable(), hrtime(true) + 1, [
            'ru_utime.tv_sec' => 1,
            'ru_stime.tv_sec' => 1,
            'ru_utime.tv_usec' => 1,
            'ru_stime.tv_usec' => 1,
        ], 1100, 2100, 3100, 4100);
        return new ProfilingData($start, $end, $label);
    }

    #[Test]
    public function it_can_handle_the_avegare_of_zero_values(): void
    {
        self::assertEquals(
            Metrics::none(),
            Metrics::average(Metrics::none(), Metrics::none(), Metrics::none()),
        );
    }

    #[Test]
    public function it_can_do_the_avegare_using_the_profiling_data(): void
    {
        $profilingData = $this->createProfilingData('empty_label');

        self::assertEquals($profilingData->metrics, Metrics::average($profilingData));
    }

    #[Test]
    public function it_can_calculate_the_average_using_the_profiler(): void
    {
        $profilingData1 = $this->createProfilingData('profile1');
        $profilingData2 = $this->createProfilingData('profile2');

        $profiler = new Profiler(fn () => null);
        $reflection = new ReflectionClass($profiler);
        $reflection->getProperty('profilingDataList')->setValue($profiler, [$profilingData1, $profilingData2]);
        $reflection->getProperty('labels')->setValue($profiler, [$profilingData1->label => 1, $profilingData2->label => 1]);

        self::assertEquals(
            Metrics::average($profiler),
            Metrics::average($profilingData1, $profilingData2),
        );
    }

    #[Test]
    public function it_correctly_returns_the_cpu_time_in_nanoseconds(): void
    {
        $start = new Snapshot(new DateTimeImmutable(), hrtime(true), [
            'ru_utime.tv_sec'  => 1,
            'ru_utime.tv_usec' => 500_000, // 1.5s user
            'ru_stime.tv_sec'  => 0,
            'ru_stime.tv_usec' => 250_000, // 0.25s system
        ], 1000, 2000, 3000, 4000);
        $end = new Snapshot(new DateTimeImmutable(), hrtime(true) + 1, [
            'ru_utime.tv_sec'  => 2,
            'ru_utime.tv_usec' => 0,       // 2.0s user
            'ru_stime.tv_sec'  => 0,
            'ru_stime.tv_usec' => 750_000, // 0.75s system
        ], 1100, 2100, 3100, 4100);

        // Expected:
        // User time: 2.0 - 1.5 = 0.5s = 500_000_000 ns
        // System time: 0.75 - 0.25 = 0.5s = 500_000_000 ns
        // Total = 1_000_000_000 ns

        self::assertSame(1_000_000_000.0, Metrics::fromSnapshots($start, $end)->cpuTime);
    }
}
