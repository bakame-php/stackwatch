<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use DateTimeInterface;
use OpenTelemetry\API\Trace\Span;
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

    public function exportProfilingData(ProfilingResult|ProfilingData $profilingData): void
    {
        if ($profilingData instanceof ProfilingResult) {
            $profilingData = $profilingData->profilingData;
        }

        $start = $profilingData->start;
        $end = $profilingData->end;

        $span = $this->tracer
            ->spanBuilder($profilingData->label)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setStartTimestamp(DurationUnit::Millisecond->convertToNano((int) $start->timestamp->format('Uu')))
            ->startSpan();

        $this->exportSnapshot($start);
        $metrics = $profilingData->metrics;
        $span->setAttribute('export.status', 'success');
        $span->setAttribute('profiler.label', $profilingData->label);
        $span->setAttribute('profiler.status', 'ended');
        $span->setAttribute('cpu.time', $metrics->cpuTime);
        $span->setAttribute('execution.time', $metrics->executionTime);
        $span->setAttribute('memory.usage', $metrics->memoryUsage);
        $span->setAttribute('memory.usage.real', $metrics->realMemoryUsage);
        $span->setAttribute('memory.peak', $metrics->peakMemoryUsage);
        $span->setAttribute('memory.peak.real', $metrics->realPeakMemoryUsage);
        $this->exportSnapshot($end);
        $span->end(DurationUnit::Millisecond->convertToNano((int) $end->timestamp->format('Uu')));
    }

    public function exportProfiler(Profiler $profiler, ?string $label = null): void
    {
        $parent = $this->tracer->spanBuilder('profiler-run')->startSpan();
        $scope = $parent->activate();
        $input = null === $label ? $profiler : $profiler->getAll($label);
        foreach ($input as $profilingData) {
            $this->exportProfilingData($profilingData);
        }

        $parent->end();
        $scope->detach();
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        $activeSpan = Span::fromContext(Context::getCurrent());
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
            'snapshot.timestamp.iso' => $snapshot->timestamp->format(DateTimeInterface::ATOM),
        ];

        foreach ($snapshot->cpu as $key => $value) {
            $attributes["snapshot.cpu.$key"] = $value;
        }

        $activeSpan->addEvent('snapshot', $attributes, $timestampNs);
    }
}
