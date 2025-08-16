<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Timeline;
use DateTimeInterface;
use OpenTelemetry\API\Trace\Span as OtlSpan;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

final class OpenTelemetryExporter implements Exporter
{
    private readonly TracerProviderInterface $tracerProvider;
    private readonly TracerInterface $tracer;

    public function __construct(TracerProviderInterface $tracerProvider)
    {
        $this->tracerProvider = $tracerProvider;
        $this->tracer = $this->tracerProvider->getTracer('profiler-exporter');
    }

    public function exportSummary(Result|Span $span, Profiler|Timeline|null $parent = null): void
    {
        if ($span instanceof Result) {
            $span = $span->span;
        }

        $start = $span->start;
        $end = $span->end;

        $otlspan = $this->tracer
            ->spanBuilder($span->label)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setStartTimestamp(DurationUnit::Millisecond->convertToNano((int) $start->timestamp->format('Uu')))
            ->startSpan();

        $this->exportSnapshot($start);
        $metrics = $span->metrics;
        if (null !== $parent) {
            $otlspan->setAttribute('profiler.identifier', $parent->identifier());
        }

        $otlspan->setAttribute('export.status', 'success');
        $otlspan->setAttribute('profiler.label', $span->label);
        $otlspan->setAttribute('profiler.status', 'ended');
        $otlspan->setAttribute('cpu.time', $metrics->cpuTime);
        $otlspan->setAttribute('execution.time', $metrics->executionTime);
        $otlspan->setAttribute('memory.usage', $metrics->memoryUsage);
        $otlspan->setAttribute('memory.usage.real', $metrics->realMemoryUsage);
        $otlspan->setAttribute('memory.peak', $metrics->peakMemoryUsage);
        $otlspan->setAttribute('memory.peak.real', $metrics->realPeakMemoryUsage);
        $this->exportSnapshot($end);
        $otlspan->end(DurationUnit::Millisecond->convertToNano((int) $end->timestamp->format('Uu')));
    }

    public function exportProfiler(Profiler $profiler, ?string $label = null): void
    {
        $parent = $this->tracer->spanBuilder('profiler-run')
            ->setAttribute('profiler.identifier', $profiler->identifier())
            ->startSpan();
        $scope = $parent->activate();
        $input = null === $label ? $profiler : $profiler->getAll($label);

        try {
            foreach ($input as $span) {
                $this->exportSummary($span, $profiler);
            }
        } finally {
            $parent->end();
            $scope->detach();
        }
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        $activeSpan = OtlSpan::fromContext(Context::getCurrent());
        if (!$activeSpan->isRecording()) {
            return;
        }

        $timestampNs = DurationUnit::Millisecond->convertToNano((int) $snapshot->timestamp->format('Uu'));

        $attributes = [
            'snapshot.execution.time,ns' => $snapshot->hrtime,
            'snapshot.memory.usage' => $snapshot->memoryUsage,
            'snapshot.memory.usage.real' => $snapshot->realMemoryUsage,
            'snapshot.memory.peak' => $snapshot->peakMemoryUsage,
            'snapshot.memory.peak.real' => $snapshot->realPeakMemoryUsage,
            'snapshot.cpu.user.time' => $snapshot->cpuUserTime,
            'snapshot.cpu.system.time.real' => $snapshot->cpuSystemTime,
            'snapshot.timestamp.iso' => $snapshot->timestamp->format(DateTimeInterface::ATOM),
            'snapshot.call.location.file' => $snapshot->originPath ?? '',
            'snapshot.call.location.line' => $snapshot->originLine ?? '',
        ];

        $activeSpan->addEvent('snapshot', $attributes, $timestampNs);
    }

    public function exportTimeline(Timeline $timeline): void
    {
        if (! $timeline->hasEnoughSnapshots()) {
            return;
        }

        $parent = $this->tracer->spanBuilder('timeline-run')
            ->setAttribute('profiler.identifier', $timeline->identifier())
            ->startSpan();
        $scope = $parent->activate();

        try {
            foreach ($timeline->deltas() as $span) {
                $this->exportSummary($span, $timeline);
            }
        } finally {
            $parent->end();
            $scope->detach();
        }
    }
}
