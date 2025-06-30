<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function hrtime;
use function usleep;

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
        usleep(100);
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
            Metrics::avg(Metrics::none(), Metrics::none(), Metrics::none()),
        );
    }

    #[Test]
    public function it_can_do_the_avegare_using_the_profiling_data(): void
    {
        $profilingData = $this->createProfilingData('empty_label');

        self::assertEquals($profilingData->metrics, Metrics::avg($profilingData, Metrics::none(), Metrics::none()));
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
            Metrics::avg($profiler),
            Metrics::avg($profilingData1, $profilingData2),
        );
    }
}
