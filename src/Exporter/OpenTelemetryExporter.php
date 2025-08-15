<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Summary;
use Bakame\Stackwatch\Timeline;
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

    public function exportSummary(Result|Summary $summary, Profiler|Timeline|null $parent = null): void
    {
        if ($summary instanceof Result) {
            $summary = $summary->summary;
        }

        $start = $summary->start;
        $end = $summary->end;

        $span = $this->tracer
            ->spanBuilder($summary->label)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setStartTimestamp(DurationUnit::Millisecond->convertToNano((int) $start->timestamp->format('Uu')))
            ->startSpan();

        $this->exportSnapshot($start);
        $metrics = $summary->metrics;
        if (null !== $parent) {
            $span->setAttribute('profiler.identifier', $parent->identifier());
        }

        $span->setAttribute('export.status', 'success');
        $span->setAttribute('profiler.label', $summary->label);
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
        $parent = $this->tracer->spanBuilder('profiler-run')
            ->setAttribute('profiler.identifier', $profiler->identifier())
            ->startSpan();
        $scope = $parent->activate();
        $input = null === $label ? $profiler : $profiler->getAll($label);

        try {
            foreach ($input as $summary) {
                $this->exportSummary($summary, $profiler);
            }
        } finally {
            $parent->end();
            $scope->detach();
        }
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
            'snapshot.call.location.file' => $snapshot->callLocation->path ?? '',
            'snapshot.call.location.line' => $snapshot->callLocation->line ?? '',
        ];

        foreach ($snapshot->cpu as $key => $value) {
            $attributes["snapshot.cpu.$key"] = $value;
        }

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
            foreach ($timeline->deltas() as $summary) {
                $this->exportSummary($summary, $timeline);
            }
        } finally {
            $parent->end();
            $scope->detach();
        }
    }
}
