<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\Span as OtlSpan;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

use function is_callable;

final class OtlExporter implements Exporter
{
    private readonly TracerInterface $tracer;
    private readonly MeterInterface $meter;
    private readonly HistogramInterface $execTime;
    private readonly HistogramInterface $memoryUsage;
    private readonly HistogramInterface $memoryRss;
    private readonly CounterInterface $cpuUser;
    private readonly CounterInterface $cpuSystem;

    public function __construct(
        TracerProviderInterface $tracerProvider,
        MeterProviderInterface $meterProvider,
    ) {
        $this->tracer = $tracerProvider->getTracer('profiler-exporter');

        $this->meter = $meterProvider->getMeter('profiler-exporter');

        $this->execTime = $this->meter->createHistogram('code.execution_time', 'ns');
        $this->memoryUsage = $this->meter->createHistogram('process.memory.usage', 'bytes');
        $this->memoryRss = $this->meter->createHistogram('process.memory.rss', 'bytes');
        $this->cpuUser = $this->meter->createCounter('process.cpu.time.user', 'ns');
        $this->cpuSystem = $this->meter->createCounter('process.cpu.time.system', 'ns');
    }

    public function exportTimeline(Timeline $timeline, ?callable $filter = null): void
    {
        $parent = $this->tracer->spanBuilder('timeline-run')
            ->setAttribute('profiler.identifier', $timeline->identifier())
            ->startSpan();
        $scope = $parent->activate();
        $input = null === $filter ? $timeline : $timeline->filter($filter);

        try {
            foreach ($input as $snapshot) {
                $this->exportSnapshot($snapshot);
            }
        } finally {
            $scope->detach();
            $parent->end();
        }
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        $activeSpan = OtlSpan::fromContext(Context::getCurrent());
        if (!$activeSpan->isRecording()) {
            return;
        }

        $seconds = (int) $snapshot->timestamp->format('U');
        $micros = (int) $snapshot->timestamp->format('u');
        $nanos = $seconds * 1_000_000_000 + $micros * 1000;

        $activeSpan->addEvent('snapshot', [
            'label' => $snapshot->label,
            'code.execution_time_ns' => $snapshot->hrtime,
            'process.memory.usage' => $snapshot->memoryUsage,
            'process.memory.rss' => $snapshot->realMemoryUsage,
            'process.memory.peak_usage' => $snapshot->peakMemoryUsage,
            'process.memory.peak_rss' => $snapshot->realPeakMemoryUsage,
            'process.cpu.time.user' => $snapshot->cpuUserTime,
            'process.cpu.time.system' => $snapshot->cpuSystemTime,
        ], $nanos);

        $labels = ['label' => $snapshot->label];
        $this->execTime->record($snapshot->hrtime, $labels);
        $this->memoryUsage->record($snapshot->memoryUsage, $labels);
        $this->memoryRss->record($snapshot->realMemoryUsage, $labels);
        $this->cpuUser->add($snapshot->cpuUserTime, $labels);
        $this->cpuSystem->add($snapshot->cpuSystemTime, $labels);
    }

    public function exportProfiler(Profiler $profiler, callable|string|null $label = null): void
    {
        $parent = $this->tracer->spanBuilder('profiler-run')
            ->setAttribute('profiler.identifier', $profiler->identifier())
            ->startSpan();
        $scope = $parent->activate();

        $input = match (true) {
            null === $label => $profiler,
            is_callable($label) => $profiler->filter($label),
            default => $profiler->getAll($label),
        };

        try {
            foreach ($input as $span) {
                $this->exportSpan($span);
            }
        } finally {
            $scope->detach();
            $parent->end();
        }
    }

    public function exportSpan(Result|Span $span): void
    {
        if ($span instanceof Result) {
            $span = $span->span;
        }

        $start = $span->start;
        $end = $span->end;

        $otlSpan = $this->tracer
            ->spanBuilder($span->label)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setStartTimestamp(DurationUnit::Millisecond->convertToNano((int) $start->timestamp->format('Uu')))
            ->startSpan();

        $scope = $otlSpan->activate();
        try {
            $this->exportSnapshot($start);
            $this->exportMetrics($span->metrics);
            $this->exportSnapshot($end);
        } finally {
            $scope->detach();
            $otlSpan->end(DurationUnit::Millisecond->convertToNano((int) $end->timestamp->format('Uu')));
        }
    }

    public function exportEnvironment(Environment $environment): void
    {
        $activeSpan = OtlSpan::fromContext(Context::getCurrent());
        if (!$activeSpan->isRecording()) {
            return;
        }

        $activeSpan->addEvent('environment', [
            'type' => 'php_environment',
            'os' => $environment->os,
            'os_family' => $environment->osFamily,
            'hostname' => $environment->hostname,
            'machine' => $environment->machine,
            'php_int_size' => $environment->phpIntSize,
            'php_architecture' => $environment->phpArchitecture,
            'php_version' => $environment->phpVersion,
            'sapi' => $environment->sapi,
            'memory_limit' => $environment->memoryLimit,
            'raw_memory_limit' => $environment->rawMemoryLimit,
            'cpu_cores' => $environment->cpuCores,
            'total_disk' => $environment->totalDisk,
            'free_disk' => $environment->freeDisk,
        ]);
    }

    public function exportMetrics(Metrics $metrics, ?AggregationType $type = null): void
    {
        $labels = ['type' => $type?->value];
        $this->cpuUser->add($metrics->cpuTime, $labels);
        $this->execTime->record($metrics->executionTime, $labels);
        $this->memoryUsage->record($metrics->memoryUsage, $labels);
        $this->memoryRss->record($metrics->realMemoryUsage, $labels);

        // Keep growth metrics as trace events for visualization
        $activeSpan = OtlSpan::fromContext(Context::getCurrent());
        if (!$activeSpan->isRecording()) {
            return;
        }

        $activeSpan->addEvent('metric', [
            'type' => $type?->value,
            'cpu.time' => $metrics->cpuTime,
            'execution.time' => $metrics->executionTime,
            'memory.usage' => $metrics->memoryUsage,
            'memory.usage.real' => $metrics->realMemoryUsage,
            'memory.peak' => $metrics->peakMemoryUsage,
            'memory.peak.real' => $metrics->realPeakMemoryUsage,
            'memory.usage.growth' => $metrics->memoryUsageGrowth,
            'memory.usage.real.growth' => $metrics->realMemoryUsageGrowth,
            'memory.peak.growth' => $metrics->peakMemoryUsageGrowth,
            'memory.peak.real.growth' => $metrics->realPeakMemoryUsageGrowth,
        ]);
    }

    public function exportStatistics(Statistics $statistics, ?MetricType $type = null): void
    {
        $activeSpan = OtlSpan::fromContext(Context::getCurrent());
        if (!$activeSpan->isRecording()) {
            return;
        }

        $activeSpan->addEvent('statistics', [
            'type' => $type?->value,
            'unit' => $statistics->unit->name,
            'iterations' => $statistics->iterations,
            'min' => $statistics->minimum,
            'max' => $statistics->maximum,
            'range' => $statistics->range,
            'sum' => $statistics->sum,
            'average' => $statistics->average,
            'median' => $statistics->median,
            'variance' => $statistics->variance,
            'stdDev' => $statistics->stdDev,
            'coefVar' => $statistics->coefVar,
        ]);

        if (null !== $type && !$type->isGrowth()) {
            $labels = ['type' => $type->value];
            $this->execTime->record($statistics->average, $labels);
            $this->memoryUsage->record($statistics->sum, $labels);
        }
    }

    public function exportReport(Report $report): void
    {
        $parent = $this->tracer->spanBuilder('report-run')
            ->startSpan();
        $scope = $parent->activate();

        try {
            $statsMap = [
                'cpu_time' => $report->cpuTime,
                'execution_time' => $report->executionTime,
                'memory_usage' => $report->memoryUsage,
                'memory_usage_growth' => $report->memoryUsageGrowth,
                'peak_memory_usage' => $report->peakMemoryUsage,
                'peak_memory_usage_growth' => $report->peakMemoryUsageGrowth,
                'real_memory_usage' => $report->realMemoryUsage,
                'real_memory_usage_growth' => $report->realMemoryUsageGrowth,
                'real_peak_memory_usage' => $report->realPeakMemoryUsage,
                'real_peak_memory_usage_growth' => $report->realPeakMemoryUsageGrowth,
            ];

            foreach ($statsMap as $name => $stats) {
                $this->exportStatistics($stats, MetricType::from($name));
            }
        } finally {
            $scope->detach();
            $parent->end();
        }
    }
}
