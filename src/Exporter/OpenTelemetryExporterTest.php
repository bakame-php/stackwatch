<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Timeline;
use DateTimeImmutable;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function hrtime;
use function usleep;

#[CoversClass(OpenTelemetryExporter::class)]
#[CoversClass(DurationUnit::class)]
#[CoversClass(MemoryUnit::class)]
#[CoversClass(Timeline::class)]
#[CoversClass(Profiler::class)]
class OpenTelemetryExporterTest extends TestCase
{
    private OpenTelemetryExporter $exporter;
    private InMemoryExporter $otlExporter;

    /**
     * @param non-empty-string $label
     */
    private static function createSummary(string $label): Span
    {
        $start = new Snapshot(
            'start',
            new DateTimeImmutable(),
            hrtime(true),
            1001,
            1001,
            1000,
            2000,
            3000,
            4000,
            '/test/this/code.php',
            42
        );
        usleep(100);
        $end = new Snapshot(
            'end',
            new DateTimeImmutable(),
            hrtime(true) + 1,
            1001,
            1001,
            1100,
            2100,
            3100,
            4100,
            '/test/this/code.php',
            56
        );
        return new Span($label, $start, $end);
    }

    protected function setUp(): void
    {
        $this->otlExporter = new InMemoryExporter();
        $this->exporter = new OpenTelemetryExporter(
            new TracerProvider(
                new SimpleSpanProcessor($this->otlExporter)
            )
        );
    }

    protected function tearDown(): void
    {
        unset($this->exporter);
        $this->otlExporter = new InMemoryExporter();
    }

    #[Test]
    #[DataProvider('providesSummaries')]
    public function it_can_export_profiling_data(Span|Result $profiling): void
    {
        $this->exporter->exportSummary($profiling);

        $span = $profiling instanceof Span ? $profiling : $profiling->span;
        $spans = $this->otlExporter->getSpans();
        self::assertCount(1, $spans);
        /** @var ImmutableSpan $otlSpan */
        $otlSpan = $spans[0];

        self::assertSame($span->label, $otlSpan->getName());

        $otlAttributes = $otlSpan->getAttributes()->toArray();

        self::assertSame('success', $otlAttributes['export.status']);
        self::assertSame('test_export', $otlAttributes['profiler.label']);
        self::assertSame('ended', $otlAttributes['profiler.status']);

        self::assertArrayHasKey('cpu.time', $otlAttributes);
        self::assertArrayHasKey('execution.time', $otlAttributes);
        self::assertArrayHasKey('memory.usage', $otlAttributes);
        self::assertArrayHasKey('memory.usage.real', $otlAttributes);
        self::assertArrayHasKey('memory.peak', $otlAttributes);
        self::assertArrayHasKey('memory.peak.real', $otlAttributes);
        self::assertArrayNotHasKey('profiler.identifier', $otlAttributes);
    }

    /**
     * @return iterable<string, array{profiling: Result|Span}>
     */
    public static function providesSummaries(): iterable
    {
        yield 'the profiling data comes from a Summary instance' => [
            'profiling' => self::createSummary('test_export'),
        ];

        yield 'the profiling data comes from a ProfiledResult instance' => [
            'profiling' => new Result('result', self::createSummary('test_export')),
        ];
    }

    #[Test]
    public function it_can_export_a_profiler(): void
    {
        $span1 = self::createSummary('profile1');
        $span2 = self::createSummary('profile2');

        $profiler = new Profiler(fn () => null);
        $reflection = new ReflectionClass($profiler);
        $reflection->getProperty('spans')->setValue($profiler, [$span1, $span2]);

        $this->exporter->exportProfiler($profiler);
        $spans = $this->otlExporter->getSpans();
        self::assertCount(3, $spans);

        /** @var ImmutableSpan $span */
        $span = $spans[0];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], $span1->label);
        self::assertSame($otlAttributes['profiler.identifier'], $profiler->identifier());

        /** @var ImmutableSpan $span */
        $span = $spans[1];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], $span2->label);
        self::assertSame($otlAttributes['profiler.identifier'], $profiler->identifier());
    }

    #[Test]
    public function it_can_export_nothing_if_the_timeline_cannot_be_sumaarize(): void
    {
        $this->exporter->exportTimeline(Timeline::start());
        $spans = $this->otlExporter->getSpans();
        self::assertCount(0, $spans);
    }

    #[Test]
    public function it_can_export_a_timeline(): void
    {
        $timeline = new Timeline('test-timeline');
        $start = new Snapshot(
            'start',
            new DateTimeImmutable(),
            hrtime(true),
            1001,
            1001,
            1000,
            2000,
            3000,
            4000,
            '/test/this/code.php',
            42
        );
        usleep(100);
        $middle = new Snapshot(
            'middle',
            new DateTimeImmutable(),
            hrtime(true),
            1001,
            1001,
            1000,
            2000,
            3000,
            4000,
            '/test/this/code.php',
            56
        );
        usleep(100);
        $end = new Snapshot(
            'end',
            new DateTimeImmutable(),
            hrtime(true) + 1,
            1001,
            1001,
            1100,
            2100,
            3100,
            4100,
            '/test/this/code.php',
            64
        );

        $reflection = new ReflectionClass($timeline);
        $reflection->getProperty('snapshots')->setValue($timeline, ['start' => $start, 'middle' => $middle, 'end' => $end]);

        $this->exporter->exportTimeline($timeline);
        $spans = $this->otlExporter->getSpans();
        self::assertCount(3, $spans);

        /** @var ImmutableSpan $span */
        $span = $spans[0];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], 'start_middle');
        self::assertSame($otlAttributes['profiler.identifier'], $timeline->identifier());

        /** @var ImmutableSpan $span */
        $span = $spans[1];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], 'middle_end');
        self::assertSame($otlAttributes['profiler.identifier'], $timeline->identifier());
    }
}
