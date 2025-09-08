<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use DateTimeImmutable;
use OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function hrtime;

#[CoversClass(OtlExporter::class)]
final class OtlExporterTest extends TestCase
{
    private OtlExporter $exporter;
    private InMemoryExporter $otlExporter;

    protected function setUp(): void
    {
        $this->otlExporter = new InMemoryExporter();
        $this->exporter = new OtlExporter(
            new TracerProvider(new SimpleSpanProcessor($this->otlExporter)),
            new NoopMeterProvider()
        );
    }

    protected function tearDown(): void
    {
        unset($this->exporter, $this->otlExporter);
    }

    #[Test]
    #[DataProvider('providesSpan')]
    public function it_can_export_profiling_data(Result|Span $result): void
    {
        $this->exporter->exportSpan($result);

        $span = $result instanceof Span ? $result : $result->span;
        $otlSpans = $this->otlExporter->getSpans();
        self::assertCount(1, $otlSpans);
        /** @var ImmutableSpan $otlSpan */
        $otlSpan = $otlSpans[0];

        self::assertSame($span->label, $otlSpan->getName());

        $events = $otlSpan->getEvents();
        self::assertCount(3, $events);
        self::assertSame('snapshot', $events[0]->getName());
        self::assertSame('metric', $events[1]->getName());
        self::assertSame('snapshot', $events[2]->getName());

        $otlAttributes = $events[1]->getAttributes()->toArray();
        foreach ([
            'cpu.time',
            'execution.time',
            'memory.usage',
            'memory.usage.real',
            'memory.peak',
            'memory.peak.real',
        ] as $expectedKey) {
            self::assertArrayHasKey($expectedKey, $otlAttributes);
        }

        self::assertArrayNotHasKey('profiler.identifier', $otlAttributes);
    }

    /**
     * @return iterable<string, array{result: Result|Span}>
     */
    public static function providesSpan(): iterable
    {
        $span = self::createSpan('test_export');

        yield 'the profiling data comes from a Summary instance' => ['result' => $span];
        yield 'the profiling data comes from a ProfiledResult instance' => ['result' => new Result('result', $span)];
    }

    /**
     * @param non-empty-string $label
     */
    private static function createSpan(string $label): Span
    {
        $start = new Snapshot('start', new DateTimeImmutable(), hrtime(true), 10001, 1001, 1000, 2000, 3000, 4000);
        $end = new Snapshot('end', new DateTimeImmutable(), hrtime(true) + 1, 1001, 1001, 1100, 2100, 3100, 4100);
        return new Span($label, $start, $end);
    }
}
