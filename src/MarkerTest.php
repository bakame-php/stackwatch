<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Marker::class)]
#[CoversClass(Label::class)]
final class MarkerTest extends TestCase
{
    private Marker $marker;

    protected function setUp(): void
    {
        $this->marker = new Marker();
    }

    #[Test]
    public function it_is_initially_empty(): void
    {
        self::assertTrue($this->marker->isEmpty());
        self::assertCount(0, $this->marker);
    }

    #[Test]
    public function it_adds_snapshot_everytime_mark_is_called(): void
    {
        $this->marker->mark('start');

        self::assertTrue($this->marker->has('start'));
        self::assertCount(1, $this->marker);
    }

    #[Test]
    public function it_throws_on_duplicate_label(): void
    {
        $this->marker->mark('foo');

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('The label "foo" already exists.');
        $this->marker->mark('foo');
    }

    #[Test]
    public function it_returns_a_profiling_data_on_delta(): void
    {
        $this->marker->mark('a');
        usleep(1000); // simulate work
        $this->marker->mark('b');

        $delta = $this->marker->delta('a', 'b');
        self::assertInstanceOf(ProfilingData::class, $delta);
        self::assertGreaterThan(0, $delta->metrics->toArray()['cpu_time'] ?? 0);
    }

    #[Test]
    public function it_can_return_a_single_metrics_on_delta_usage(): void
    {
        $this->marker->mark('x');
        usleep(1000);
        $this->marker->mark('y');
        $time = $this->marker->delta('x', 'y', 'cpu_time');

        self::assertIsFloat($time);
        self::assertGreaterThan(0, $time);
    }

    #[Test]
    public function it_will_throw_on_unknown_label(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->marker->delta('foo', 'bar');
    }

    #[Test]
    public function it_will_throw_on_unknown_metric(): void
    {
        $this->marker->mark('start');
        usleep(500);
        $this->marker->mark('end');

        $this->expectException(InvalidArgument::class);

        $this->marker->delta('start', 'end', 'unknown_metric');
    }

    #[Test]
    public function it_can_use_offset_for_navigating_snapshots(): void
    {
        $this->marker->mark('first');
        $this->marker->mark('middle');
        $this->marker->mark('last');

        self::assertSame($this->marker->get('first'), $this->marker->first());
        self::assertSame($this->marker->get('last'), $this->marker->latest());
        self::assertSame($this->marker->get('middle'), $this->marker->nth(1));
        self::assertSame($this->marker->get('last'), $this->marker->nth(-1));
    }

    #[Test]
    public function it_will_return_null_on_summary_call_if_there_are_not_enough_snapshots(): void
    {
        self::assertNull($this->marker->summary());
        $this->marker->mark('only');
        self::assertNull($this->marker->summary());
    }

    #[Test]
    public function it_returns_a_profiling_data_on_summary(): void
    {
        $this->marker->mark('start');
        usleep(1000);
        $this->marker->mark('end');

        $summary = $this->marker->summary();
        self::assertInstanceOf(ProfilingData::class, $summary);
    }

    #[Test]
    public function it_can_reset_the_marker_instance(): void
    {
        $this->marker->mark('foo');
        $this->marker->mark('bar');

        $this->marker->reset();

        self::assertTrue($this->marker->isEmpty());
        self::assertCount(0, $this->marker);
        self::assertFalse($this->marker->has('foo'));
    }

    #[Test]
    public function it_yield_all_intermediate_diff_on_reports_call(): void
    {
        $this->marker->mark('a');
        usleep(1000);
        $this->marker->mark('b');
        usleep(1000);
        $this->marker->mark('c');

        $reports = iterator_to_array($this->marker->deltas());
        self::assertCount(2, $reports);

        foreach ($reports as $data) {
            self::assertInstanceOf(ProfilingData::class, $data);
        }

        foreach ($this->marker as $label => $snapshot) {
            self::assertContains($label, $this->marker->labels());
        }

        self::assertSame(json_encode($this->marker->toArray()), json_encode($this->marker));

        $this->marker->reset();
        $reports = iterator_to_array($this->marker->deltas());

        self::assertCount(0, $reports);
        self::assertNull($this->marker->nth(-2));
    }

    #[Test]
    public function it_returns_a_valid_profiling_data_on_finish_call(): void
    {
        $marker = Marker::start('boot');
        usleep(10_000); // 10ms
        $profile = $marker->finish('shutdown', 'boot_shutdown');

        $metrics = $profile->metrics;

        self::assertGreaterThan(0, $metrics->executionTime);
    }

    #[Test]
    public function it_throws_if_no_enough_snapshot_present_on_finish(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Marking can not be finished; no starting snapshot found.');

        $marker = new Marker();
        $marker->finish();
    }
}
