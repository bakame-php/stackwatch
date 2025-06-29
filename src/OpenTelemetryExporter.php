<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class OpenTelemetryExporter implements Exporter
{
    private readonly TracerProviderInterface $tracerProvider;
    private readonly TracerInterface $tracer;
    private readonly LoggerInterface $logger;

    public function __construct(TracerProviderInterface $tracerProvider, LoggerInterface $logger = new NullLogger())
    {
        $this->tracerProvider = $tracerProvider;
        $this->tracer = $this->tracerProvider->getTracer('profiler-exporter');
        $this->logger = $logger;
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
            ->setStartTimestamp((int) $start->timestamp->format('Uu')  * 1000)
            ->startSpan();

        $metrics = $profilingData->metrics;

        $span->setAttribute('export.status', 'success');
        $span->setAttribute('profiler.label', $profilingData->label);
        $span->setAttribute('profiler.status', 'ended');
        $span->setAttribute('cpu_time', $metrics->cpuTime * 1000);
        $span->setAttribute('exec_time', $metrics->executionTime);
        $span->setAttribute('memory_usage', $metrics->memoryUsage);
        $span->setAttribute('real_memory_usage', $metrics->realMemoryUsage);
        $span->setAttribute('peak_memory_usage', $metrics->peakMemoryUsage);
        $span->setAttribute('real_peak_memory_usage', $metrics->realPeakMemoryUsage);

        $span->end((int) $end->timestamp->format('Uu') * 1000);
    }

    public function exportProfiler(Profiler $profiler): void
    {
        $parent = $this->tracer->spanBuilder('profiler-run')->startSpan();
        $scope = $parent->activate();

        foreach ($profiler as $profilingData) {
            $this->exportProfilingData($profilingData);
        }

        $parent->end();
        $scope->detach();

        try {
            $this->tracerProvider->shutdown();
        } catch (Throwable $exception) {
            $this->logger->error('Exporting the Profiler aborted due to an error with the tracer provider.', ['exception' => $exception]);
        }
    }
}
