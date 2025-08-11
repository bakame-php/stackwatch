<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function usleep;

#[CoversClass(Timeline::class)]
#[CoversClass(LabelGenerator::class)]
final class TimelineTest extends TestCase
{
    private Timeline $timeline;

    protected function setUp(): void
    {
        $this->timeline = new Timeline();
    }

    #[Test]
    public function it_is_initially_empty(): void
    {
        self::assertTrue($this->timeline->hasNoSnapshots());
        self::assertCount(0, $this->timeline);
        self::assertFalse($this->timeline->hasSnapshots());
    }

    #[Test]
    public function it_adds_snapshot_everytime_mark_is_called(): void
    {
        $this->timeline->capture('start');

        self::assertTrue($this->timeline->hasLabel('start'));
        self::assertCount(1, $this->timeline);
    }

    #[Test]
    public function it_throws_on_duplicate_label(): void
    {
        $this->timeline->capture('foo');

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('The label "foo" already exists.');
        $this->timeline->capture('foo');
    }

    #[Test]
    public function it_returns_a_profiling_data_on_delta(): void
    {
        $this->timeline->capture('a');
        usleep(1000); // simulate work
        $this->timeline->capture('b');

        $delta = $this->timeline->delta('a', 'b');
        $metrics = $delta->metrics;

        self::assertEquals($delta, $this->timeline->delta('a'));
        self::assertGreaterThan(0, $delta->metrics->toArray()['cpu_time'] ?? 0);
        self::assertSame($metrics->executionTime, $this->timeline->metrics('a', 'b')->executionTime);
        self::assertSame($metrics->cpuTime, $this->timeline->metrics('a')->cpuTime);
        self::assertSame($metrics->realPeakMemoryUsage, $this->timeline->metrics('a')->realPeakMemoryUsage);
        self::assertSame($metrics->realMemoryUsage, $this->timeline->metrics('a')->realMemoryUsage);
    }

    #[Test]
    public function it_will_throw_on_unknown_label(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->timeline->delta('foo', 'bar');
    }

    #[Test]
    public function it_can_use_offset_for_navigating_snapshots(): void
    {
        $this->timeline->capture('first');
        $this->timeline->capture('middle');
        $this->timeline->capture('last');

        self::assertSame($this->timeline->get('first'), $this->timeline->first());
        self::assertSame($this->timeline->get('last'), $this->timeline->latest());
        self::assertSame($this->timeline->get('middle'), $this->timeline->nth(1));
        self::assertSame($this->timeline->get('last'), $this->timeline->nth(-1));
    }

    #[Test]
    public function it_will_return_null_on_summary_call_if_there_are_not_enough_snapshots(): void
    {
        $this->timeline->capture('only');


        self::assertEquals(Metrics::none(), $this->timeline->summarize()->metrics);
    }

    #[Test]
    public function it_returns_a_profiling_data_on_summary(): void
    {
        $this->timeline->capture('start');
        usleep(1000);
        $this->timeline->capture('end');

        self::assertCount(2, $this->timeline);
    }

    #[Test]
    public function it_can_reset_the_timeline_instance(): void
    {
        $this->timeline->capture('foo');
        $this->timeline->capture('bar');

        $this->timeline->reset();

        self::assertTrue($this->timeline->hasNoSnapshots());
        self::assertCount(0, $this->timeline);
        self::assertFalse($this->timeline->hasLabel('foo'));
    }

    #[Test]
    public function it_yield_all_intermediate_diff_on_reports_call(): void
    {
        $this->timeline->capture('a');
        usleep(1000);
        $this->timeline->capture('b');
        usleep(1000);
        $this->timeline->capture('c');

        $reports = iterator_to_array($this->timeline->deltas());
        self::assertCount(2, $reports);

        foreach ($reports as $data) {
            self::assertInstanceOf(Summary::class, $data);
        }

        foreach ($this->timeline as $snapshot) {
            self::assertContains($snapshot->label, $this->timeline->labels());
        }

        self::assertSame(json_encode($this->timeline->toArray()), json_encode($this->timeline));

        $this->timeline->reset();
        $reports = iterator_to_array($this->timeline->deltas());

        self::assertCount(0, $reports);
        self::assertNull($this->timeline->nth(-2));
    }

    #[Test]
    public function it_returns_a_valid_profiling_data_on_finish_call(): void
    {
        $timeline = Timeline::start('boot');
        usleep(10_000); // 10ms
        $profile = $timeline->take('shutdown', 'boot_shutdown');

        $metrics = $profile->metrics;

        self::assertGreaterThan(0, $metrics->executionTime);
    }

    #[Test]
    public function it_throws_if_no_enough_snapshot_present_on_finish(): void
    {
        self::assertEquals(
            Metrics::none(),
            (new Timeline())->take('end')->metrics
        );
    }

    #[Test]
    public function it_can_be_complete(): void
    {
        $timeline = Timeline::start('boot');
        usleep(100);
        $timeline->capture('shutdown');
        self::assertFalse($timeline->isComplete());
        $summary = $timeline->summarize();
        $timeline->complete();
        self::assertTrue($timeline->isComplete());
        self::assertEquals($summary, $timeline->summarize());

        $this->expectException(UnableToProfile::class);
        $timeline->take('boot', 'boot_shutdown');
    }

    #[Test]
    public function it_can_be_complete_and_reverted_on_reset(): void
    {
        $timeline = Timeline::start('boot');
        usleep(100);
        $timeline->capture('shutdown');
        self::assertFalse($timeline->isComplete());
        $summary = $timeline->summarize();
        $timeline->complete();
        self::assertTrue($timeline->isComplete());
        self::assertEquals($summary, $timeline->summarize());

        $timeline->reset();
        self::assertFalse($timeline->isComplete());
    }

    public function testDeltaWithExplicitTo(): void
    {
        $timeline = $this->makeTimeline(['start', 'middle', 'end']);
        $summary = $timeline->delta('start', 'end');

        self::assertSame('start_end', $summary->label);
    }

    public function testDeltaWithoutToUsesNextLabel(): void
    {
        $timeline = $this->makeTimeline(['start', 'middle', 'end']);
        $summary = $timeline->delta('start');

        self::assertSame('start_middle', $summary->label);
    }

    public function testDeltaWithoutToAndFromIsLastLabel(): void
    {
        $timeline = $this->makeTimeline(['start', 'middle', 'end']);
        $summary = $timeline->delta('end');

        self::assertSame('end_end', $summary->label);
    }

    public function testDeltaThrowsWhenFromDoesNotExist(): void
    {
        $timeline = $this->makeTimeline(['start']);

        $this->expectException(InvalidArgument::class);
        $timeline->delta('missing');
    }

    public function testDeltaThrowsWhenToDoesNotExist(): void
    {
        $timeline = $this->makeTimeline(['start', 'end']);

        $this->expectException(InvalidArgument::class);
        $timeline->delta('start', 'missing');
    }

    /**
     * @param array<non-empty-string> $labels
     * @param int<0, max> $microDelay
     */
    private function makeTimeline(array $labels, int $microDelay = 100): Timeline
    {
        $timeline = new Timeline('test');
        foreach ($labels as $label) {
            $timeline->capture($label);
            usleep($microDelay);
        }

        return $timeline;
    }
}
