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
    private function createSummary(string $label): Span
    {
        $start = new Snapshot('start', new DateTimeImmutable(), hrtime(true), 10001, 1001, 1000, 2000, 3000, 4000);
        $end = new Snapshot('end', new DateTimeImmutable(), hrtime(true) + 1, 1001, 1001, 1100, 2100, 3100, 4100);
        return new Span($label, $start, $end);
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
        $span = $this->createSummary('empty_label');

        self::assertEquals($span->metrics, Metrics::average($span));
    }

    #[Test]
    public function it_can_calculate_the_average_using_the_profiler(): void
    {
        $span1 = $this->createSummary('profile1');
        $span2 = $this->createSummary('profile2');

        $profiler = new SpanAggregator(fn () => null);
        $reflection = new ReflectionClass($profiler);
        $reflection->getProperty('spans')->setValue($profiler, [$span1, $span2]);

        self::assertEquals(
            Metrics::average($profiler),
            Metrics::average($span1, $span2),
        );
    }

    #[Test]
    public function it_correctly_returns_the_cpu_time_in_nanoseconds(): void
    {
        $start = new Snapshot(
            'start',
            new DateTimeImmutable(),
            hrtime(true),
            1_500_000,
            250_000,
            1000,
            2000,
            3000,
            4000,
        );
        $end = new Snapshot(
            'end',
            new DateTimeImmutable(),
            hrtime(true) + 1,
            2_000_000,
            750_000,
            1100,
            2100,
            3100,
            4100,
        );

        // Expected:
        // User time: 2.0 - 1.5 = 0.5s = 500_000_000 ns
        // System time: 0.75 - 0.25 = 0.5s = 500_000_000 ns
        // Total = 1_000_000_000 ns

        self::assertSame(1_000_000.0, Metrics::fromSnapshots($start, $end)->cpuTime);
    }

    #[Test]
    public function it_can_returns_the_metrics_in_a_human_readable_format(): void
    {
        $start = new Snapshot(
            'start',
            new DateTimeImmutable(),
            hrtime(true),
            1_500_000,
            250_000,
            1000,
            2000,
            3000,
            4000,
        );
        $end = new Snapshot(
            'end',
            new DateTimeImmutable(),
            hrtime(true) + 1,
            2_000_000,
            750_000,
            1100,
            2100,
            3100,
            4100,
        );
        $metrics = Metrics::fromSnapshots($start, $end);

        $humans = $metrics->toHuman();
        self::assertIsArray($humans);
        self::assertArrayHasKey('cpu_time', $humans);
        self::assertArrayHasKey('memory_usage', $humans);
        self::assertIsString($metrics->human('execution_time'));

        $this->expectException(InvalidArgument::class);
        $metrics->human('foobar');
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
