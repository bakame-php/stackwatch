<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

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
    private function createSummary(string $label): Summary
    {
        $start = new Snapshot('start', new DateTimeImmutable(), hrtime(true), [
            'ru_utime.tv_sec' => 1,
            'ru_stime.tv_sec' => 1,
            'ru_utime.tv_usec' => 1,
            'ru_stime.tv_usec' => 1,
        ], 1000, 2000, 3000, 4000);
        $end = new Snapshot('end', new DateTimeImmutable(), hrtime(true) + 1, [
            'ru_utime.tv_sec' => 1,
            'ru_stime.tv_sec' => 1,
            'ru_utime.tv_usec' => 1,
            'ru_stime.tv_usec' => 1,
        ], 1100, 2100, 3100, 4100);
        return new Summary($label, $start, $end);
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
        $summary = $this->createSummary('empty_label');

        self::assertEquals($summary->metrics, Metrics::average($summary));
    }

    #[Test]
    public function it_can_calculate_the_average_using_the_profiler(): void
    {
        $summary1 = $this->createSummary('profile1');
        $summary2 = $this->createSummary('profile2');

        $profiler = new Profiler(fn () => null);
        $reflection = new ReflectionClass($profiler);
        $reflection->getProperty('summaries')->setValue($profiler, [$summary1, $summary2]);

        self::assertEquals(
            Metrics::average($profiler),
            Metrics::average($summary1, $summary2),
        );
    }

    #[Test]
    public function it_correctly_returns_the_cpu_time_in_nanoseconds(): void
    {
        $start = new Snapshot('start', new DateTimeImmutable(), hrtime(true), [
            'ru_utime.tv_sec'  => 1,
            'ru_utime.tv_usec' => 500_000, // 1.5s user
            'ru_stime.tv_sec'  => 0,
            'ru_stime.tv_usec' => 250_000, // 0.25s system
        ], 1000, 2000, 3000, 4000);
        $end = new Snapshot('end', new DateTimeImmutable(), hrtime(true) + 1, [
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

    #[Test]
    public function it_can_returns_the_metrics_in_a_human_readable_format(): void
    {
        $start = new Snapshot('start', new DateTimeImmutable(), hrtime(true), [
            'ru_utime.tv_sec'  => 1,
            'ru_utime.tv_usec' => 500_000, // 1.5s user
            'ru_stime.tv_sec'  => 0,
            'ru_stime.tv_usec' => 250_000, // 0.25s system
        ], 1000, 2000, 3000, 4000);
        $end = new Snapshot('end', new DateTimeImmutable(), hrtime(true) + 1, [
            'ru_utime.tv_sec'  => 2,
            'ru_utime.tv_usec' => 0,       // 2.0s user
            'ru_stime.tv_sec'  => 0,
            'ru_stime.tv_usec' => 750_000, // 0.75s system
        ], 1100, 2100, 3100, 4100);
        $metrics = Metrics::fromSnapshots($start, $end);

        $humans = $metrics->forHuman();
        self::assertIsArray($humans);
        self::assertArrayHasKey('cpu_time', $humans);
        self::assertArrayHasKey('memory_usage', $humans);
        self::assertIsString($metrics->forHuman('execution_time'));

        $this->expectException(InvalidArgument::class);
        $metrics->forHuman('foobar');
    }

    #[Test]
    public function it_can_be_recreated_from_an_array(): void
    {
        $metrics = Metrics::none();

        self::assertEquals($metrics, Metrics::fromArray($metrics->toArray()));
    }

    #[Test]
    public function it_fails_to_create_a_new_instance_from_an_invalid_array(): void
    {
        $this->expectException(InvalidArgument::class);

        Metrics::fromArray([]); /* @phpstan-ignore-line */
    }
}
