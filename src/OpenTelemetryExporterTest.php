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
class OpenTelemetryExporterTest extends TestCase
{
    private OpenTelemetryExporter $exporter;
    private InMemoryExporter $otlExporter;

    /**
     * @param non-empty-string $label
     */
    private static function createProfilingData(string $label): ProfilingData
    {
        $start = new Snapshot(new DateTimeImmutable(), hrtime(true), [
            'ru_utime.tv_sec' => 1,
            'ru_stime.tv_sec' => 1,
            'ru_utime.tv_usec' => 1,
            'ru_stime.tv_usec' => 1,
            'ru_inblock' => 0,
            'ru_oublock' => 0,
        ], 1000, 2000, 3000, 4000);
        usleep(100);
        $end = new Snapshot(new DateTimeImmutable(), hrtime(true) + 1, [
            'ru_utime.tv_sec' => 1,
            'ru_stime.tv_sec' => 1,
            'ru_utime.tv_usec' => 1,
            'ru_stime.tv_usec' => 1,
            'ru_inblock' => 0,
            'ru_oublock' => 0,
        ], 1100, 2100, 3100, 4100);
        return new ProfilingData($start, $end, $label);
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
    #[DataProvider('providesProfilingData')]
    public function it_can_export_profiling_data(ProfilingData|ProfilingResult $profiling): void
    {
        $this->exporter->exportProfilingData($profiling);

        $profilingData = $profiling instanceof ProfilingData ? $profiling : $profiling->profilingData;

        $spans = $this->otlExporter->getSpans();
        self::assertCount(1, $spans);
        /** @var ImmutableSpan $span */
        $span = $spans[0];

        self::assertSame($profilingData->label, $span->getName());

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
    }

    /**
     * @return iterable<string, array{profiling: ProfilingResult|ProfilingData}>
     */
    public static function providesProfilingData(): iterable
    {
        yield 'the profiling data comes from a ProfilingData instance' => [
            'profiling' => self::createProfilingData('test_export'),
        ];

        yield 'the profiling data comes from a ProfilingResult instance' => [
            'profiling' => new ProfilingResult('result', self::createProfilingData('test_export')),
        ];
    }


    #[Test]
    public function it_can_export_a_profiler(): void
    {
        $profilingData1 = self::createProfilingData('profile1');
        $profilingData2 = self::createProfilingData('profile2');

        $profiler = new Profiler(fn () => null);
        $reflection = new ReflectionClass($profiler);
        $reflection->getProperty('profilingDataList')->setValue($profiler, [$profilingData1, $profilingData2]);
        $reflection->getProperty('labels')->setValue($profiler, [$profilingData1->label => 1, $profilingData2->label => 1]);

        $this->exporter->exportProfiler($profiler);
        $spans = $this->otlExporter->getSpans();
        self::assertCount(3, $spans);

        /** @var ImmutableSpan $span */
        $span = $spans[0];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], $profilingData1->label);

        /** @var ImmutableSpan $span */
        $span = $spans[1];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], $profilingData2->label);
    }
}
