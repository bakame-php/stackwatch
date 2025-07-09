<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

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
#[CoversClass(Marker::class)]
#[CoversClass(Profiler::class)]
class OpenTelemetryExporterTest extends TestCase
{
    private OpenTelemetryExporter $exporter;
    private InMemoryExporter $otlExporter;

    /**
     * @param non-empty-string $label
     */
    private static function createSummary(string $label): Summary
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
        return new Summary($start, $end, $label);
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
    public function it_can_export_profiling_data(Summary|ProfiledResult $profiling): void
    {
        $this->exporter->exportSummary($profiling);

        $summary = $profiling instanceof Summary ? $profiling : $profiling->summary;
        $spans = $this->otlExporter->getSpans();
        self::assertCount(1, $spans);
        /** @var ImmutableSpan $span */
        $span = $spans[0];

        self::assertSame($summary->label, $span->getName());

        $otlAttributes = $span->getAttributes()->toArray();

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
     * @return iterable<string, array{profiling: ProfiledResult|Summary}>
     */
    public static function providesSummaries(): iterable
    {
        yield 'the profiling data comes from a Summary instance' => [
            'profiling' => self::createSummary('test_export'),
        ];

        yield 'the profiling data comes from a ProfiledResult instance' => [
            'profiling' => new ProfiledResult('result', self::createSummary('test_export')),
        ];
    }

    #[Test]
    public function it_can_export_a_profiler(): void
    {
        $summary1 = self::createSummary('profile1');
        $summary2 = self::createSummary('profile2');

        $profiler = new Profiler(fn () => null);
        $reflection = new ReflectionClass($profiler);
        $reflection->getProperty('summaries')->setValue($profiler, [$summary1, $summary2]);
        $reflection->getProperty('labels')->setValue($profiler, [$summary1->label => 1, $summary2->label => 1]);

        $this->exporter->exportProfiler($profiler);
        $spans = $this->otlExporter->getSpans();
        self::assertCount(3, $spans);

        /** @var ImmutableSpan $span */
        $span = $spans[0];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], $summary1->label);
        self::assertSame($otlAttributes['profiler.identifier'], $profiler->identifier());

        /** @var ImmutableSpan $span */
        $span = $spans[1];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], $summary2->label);
        self::assertSame($otlAttributes['profiler.identifier'], $profiler->identifier());
    }

    #[Test]
    public function it_can_export_nothing_if_the_marker_cannot_be_sumaarize(): void
    {
        $marker = Marker::start();

        $this->exporter->exportMarker($marker);
        $spans = $this->otlExporter->getSpans();
        self::assertCount(0, $spans);
    }

    #[Test]
    public function it_can_export_a_marker(): void
    {
        $marker = new Marker('test-marker');
        $start = new Snapshot(new DateTimeImmutable(), hrtime(true), [
            'ru_utime.tv_sec' => 1,
            'ru_stime.tv_sec' => 1,
            'ru_utime.tv_usec' => 1,
            'ru_stime.tv_usec' => 1,
        ], 1000, 2000, 3000, 4000);
        usleep(100);
        $middle = new Snapshot(new DateTimeImmutable(), hrtime(true), [
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

        $reflection = new ReflectionClass($marker);
        $reflection->getProperty('snapshots')->setValue($marker, ['start' => $start, 'middle' => $middle, 'end' => $end]);

        $this->exporter->exportMarker($marker);
        $spans = $this->otlExporter->getSpans();
        self::assertCount(3, $spans);

        /** @var ImmutableSpan $span */
        $span = $spans[0];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], 'start_middle');
        self::assertSame($otlAttributes['profiler.identifier'], $marker->identifier());

        /** @var ImmutableSpan $span */
        $span = $spans[1];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], 'middle_end');
        self::assertSame($otlAttributes['profiler.identifier'], $marker->identifier());
    }
}
