<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use DateTimeImmutable;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

use function usleep;

class OpenTelemetryExporterTest extends TestCase
{
    private OpenTelemetryExporter $exporter;
    private InMemoryExporter $otlExporter;

    /**
     * @throws ReflectionException
     */
    private function createProfile(string $label): ProfilingData
    {
        $start = new Snapshot(new DateTimeImmutable(), microtime(true), [], 1000, 2000, 3000, 4000);
        usleep(100);
        $end = new Snapshot(new DateTimeImmutable(), microtime(true) + 1, [], 1100, 2100, 3100, 4100);
        return new ProfilingData($label, $start, $end);
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
    public function it_can_export_a_profile(): void
    {
        $profile = $this->createProfile('test_export');

        $this->exporter->exportProfilingData($profile);

        $spans = $this->otlExporter->getSpans();
        self::assertCount(1, $spans);
        /** @var ImmutableSpan $span */
        $span = $spans[0];

        self::assertSame($profile->label, $span->getName());

        $otlAttributes = $span->getAttributes()->toArray();

        self::assertArrayHasKey('profiler.label', $otlAttributes);
        self::assertArrayHasKey('profiler.status', $otlAttributes);
        self::assertArrayHasKey('cpu_time', $otlAttributes);
        self::assertArrayHasKey('exec_time', $otlAttributes);
        self::assertArrayHasKey('memory_usage', $otlAttributes);
        self::assertArrayHasKey('real_memory_usage', $otlAttributes);
        self::assertArrayHasKey('peak_memory_usage', $otlAttributes);
        self::assertArrayHasKey('real_peak_memory_usage', $otlAttributes);
    }

    #[Test]
    public function it_can_export_a_profiler(): void
    {
        $profile1 = $this->createProfile('profile1');
        $profile2 = $this->createProfile('profile2');

        $profiler = new Profiler(fn () => null);
        $reflection = new ReflectionClass($profiler);
        $reflection->getProperty('profilingDataList')->setValue($profiler, [$profile1, $profile2]);
        $reflection->getProperty('labels')->setValue($profiler, [$profile1->label => 1, $profile2->label => 1]);

        $this->exporter->exportProfiler($profiler);
        $spans = $this->otlExporter->getSpans();
        self::assertCount(3, $spans);

        /** @var ImmutableSpan $span */
        $span = $spans[0];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], $profile1->label);

        /** @var ImmutableSpan $span */
        $span = $spans[1];
        $otlAttributes = $span->getAttributes()->toArray();
        self::assertSame($otlAttributes['profiler.label'], $profile2->label);
    }
}
