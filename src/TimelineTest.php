<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Timeline::class)]
#[CoversClass(Label::class)]
final class TimelineTest extends TestCase
{
    private Timeline $timeline;

    protected function setUp(): void
    {
        $this->timeline = new Timeline();
    }

    public function testTimelineIsInitiallyEmpty(): void
    {
        self::assertTrue($this->timeline->isEmpty());
        self::assertCount(0, $this->timeline);
    }

    public function testTakeAddsSnapshot(): void
    {
        $this->timeline->take('start');
        self::assertTrue($this->timeline->has('start'));
        self::assertCount(1, $this->timeline);
    }

    public function testTakeThrowsOnDuplicateLabel(): void
    {
        $this->timeline->take('foo');

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('The label "foo" already exists.');
        $this->timeline->take('foo');
    }

    public function testDeltaReturnsProfilingData(): void
    {
        $this->timeline->take('a');
        usleep(1000); // simulate work
        $this->timeline->take('b');

        $delta = $this->timeline->delta('a', 'b');
        self::assertInstanceOf(ProfilingData::class, $delta);
        self::assertGreaterThan(0, $delta->metrics->toArray()['cpu_time'] ?? 0);
    }

    public function testDeltaReturnsSingleMetric(): void
    {
        $this->timeline->take('x');
        usleep(1000);
        $this->timeline->take('y');

        $time = $this->timeline->delta('x', 'y', 'cpu_time');
        self::assertIsFloat($time);
        self::assertGreaterThan(0, $time);
    }

    public function testDeltaThrowsOnUnknownLabels(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->timeline->delta('foo', 'bar');
    }

    public function testDeltaThrowsOnUnknownMetric(): void
    {
        $this->timeline->take('start');
        usleep(500);
        $this->timeline->take('end');

        $this->expectException(InvalidArgument::class);
        $this->timeline->delta('start', 'end', 'unknown_metric');
    }

    public function testNthAndFirstAndLatest(): void
    {
        $this->timeline->take('first');
        $this->timeline->take('middle');
        $this->timeline->take('last');

        self::assertSame(
            $this->timeline->get('first'),
            $this->timeline->first()
        );

        self::assertSame(
            $this->timeline->get('last'),
            $this->timeline->latest()
        );

        self::assertSame(
            $this->timeline->get('middle'),
            $this->timeline->nth(1)
        );

        self::assertSame(
            $this->timeline->get('last'),
            $this->timeline->nth(-1)
        );
    }

    public function testSummaryReturnsNullIfNotEnoughSnapshots(): void
    {
        self::assertNull($this->timeline->summary());
        $this->timeline->take('only');
        self::assertNull($this->timeline->summary());
    }

    public function testSummaryReturnsProfilingData(): void
    {
        $this->timeline->take('start');
        usleep(1000);
        $this->timeline->take('end');

        $summary = $this->timeline->summary();
        self::assertInstanceOf(ProfilingData::class, $summary);
    }

    public function testResetClearsAllData(): void
    {
        $this->timeline->take('foo');
        $this->timeline->take('bar');

        $this->timeline->reset();

        self::assertTrue($this->timeline->isEmpty());
        self::assertCount(0, $this->timeline);
        self::assertFalse($this->timeline->has('foo'));
    }

    public function testReportsYieldAllIntermediateDiffs(): void
    {
        $this->timeline->take('a');
        usleep(1000);
        $this->timeline->take('b');
        usleep(1000);
        $this->timeline->take('c');

        $reports = iterator_to_array($this->timeline->reports());
        self::assertCount(2, $reports);

        foreach ($reports as $data) {
            self::assertInstanceOf(ProfilingData::class, $data);
        }

        foreach ($this->timeline as $label => $snapshot) {
            self::assertContains($label, $this->timeline->labels());
        }

        self::assertSame(json_encode($this->timeline->toArray()), json_encode($this->timeline));

        $this->timeline->reset();
        $reports = iterator_to_array($this->timeline->reports());
        self::assertCount(0, $reports);
        self::assertNull($this->timeline->nth(-2));
    }

    public function testFinishMethodReturnsValidProfilingData(): void
    {
        $timeline = Timeline::start('boot');

        // Simulate code block
        usleep(10_000); // 10ms

        $profile = $timeline->finish('shutdown', 'boot_shutdown');

        $metrics = $profile->metrics;

        self::assertGreaterThan(0, $metrics->executionTime);
    }

    public function testFinishThrowsIfNoSnapshotExists(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('The timeline can not be finished; no starting snapshot found.');

        $timeline = new Timeline();
        $timeline->finish('end');
    }
}
