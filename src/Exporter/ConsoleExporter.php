<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\Console\Exporter;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Statistics;
use Bakame\Stackwatch\Timeline;
use Bakame\Stackwatch\Translator;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function array_map;

/**
 * @phpstan-import-type MetricsHumanReadable from Metrics
 * @phpstan-import-type SnapshotHumanReadable from Snapshot
 * @phpstan-import-type StatsHumanReadable from Statistics
 * @phpstan-import-type EnvironmentHumanReadable from Environment
 */
final class ConsoleExporter implements Exporter
{
    public function __construct(
        public readonly OutputInterface $output = new ConsoleOutput(),
        public readonly Translator $translator = new Translator(),
    ) {
    }

    public function exportSummary(Result|Span $span, Profiler|Timeline|null $parent = null): void
    {
        if ($span instanceof Result) {
            $span = $span->span;
        }

        $this->createSpanTable([$span], $parent)->render();
    }

    public function exportProfiler(Profiler $profiler, ?string $label = null): void
    {
        $input = null === $label ? $profiler : $profiler->getAll($label);

        $this
            ->createSpanTable($input)
            ->setHeaderTitle(' '.$profiler->identifier().' ')
            ->addRow(new TableSeparator())
            ->addRow($this->metricsToRow('<fg=green>Average</>', $profiler->average($label)))
            ->render();
    }

    public function exportTimeline(Timeline $timeline): void
    {
        if (! $timeline->hasEnoughSnapshots()) {
            $this
                ->createTable()
                ->setHeaderTitle($timeline->identifier())
                ->addRow([new TableCell('<fg=yellow>Not enough snapshot to generate an export</>', ['colspan' => 7])])
                ->render();

            return;
        }

        $span = $timeline->summarize();

        $this
            ->createSpanTable($timeline->deltas())
            ->setHeaderTitle(' '.$timeline->identifier().' ')
            ->addRow(new TableSeparator())
            ->addRow($this->metricsToRow('<fg=green>Summary</>', $span->metrics))
            ->render();
    }

    private function createTable(): Table
    {
        return (new Table($this->output))
            ->setHeaders([
            'Label',
            $this->translator->translate('execution_time'),
            $this->translator->translate('cpu_time'),
            $this->translator->translate('memory_usage'),
            $this->translator->translate('real_memory_usage'),
            $this->translator->translate('peak_memory_usage'),
            $this->translator->translate('real_peak_memory_usage'),
        ]);
    }

    /**
     * @param iterable<Span> $spans
     */
    private function createSpanTable(iterable $spans, Profiler|Timeline|null $parent = null): Table
    {
        $table = $this->createTable();
        if (null !== $parent) {
            $table->setHeaderTitle(' '.$parent->identifier().' ');
        }

        foreach ($spans as $span) {
            $table->addRow($this->metricsToRow($span->label, $span->metrics));
        }

        return $table;
    }

    /**
     * @return list<string>
     */
    private function metricsToRow(string $formattedLabel, Metrics $metrics): array
    {
        /** @var MetricsHumanReadable $formattedMetrics */
        $formattedMetrics = $metrics->forHuman();

        return [...[$formattedLabel], ...array_values($formattedMetrics)];
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        /** @var SnapshotHumanReadable $stats */
        $stats = $snapshot->forHuman();

        $this->renderTable($stats);
    }

    public function exportMetrics(Result|Span|Metrics $metrics): void
    {
        /** @var MetricsHumanReadable $stats */
        $stats = (match (true) {
            $metrics instanceof Result => $metrics->span->metrics,
            $metrics instanceof Span => $metrics->metrics,
            default => $metrics,
        })->forHuman();

        $this->renderTable($stats);
    }

    public function exportStatistics(Statistics $statistics): void
    {
        /** @var StatsHumanReadable $stats */
        $stats = $statistics->forHuman();

        $this->renderTable($stats);
    }

    public function exportReport(Report $report): void
    {
        static $reportPropertyNames = [
            'cpuTime' => 'CPU Time',
            'executionTime' => 'Execution Time',
            'memoryUsage' => 'Memory Usage',
            'realMemoryUsage' => 'Real Memory Usage',
            'peakMemoryUsage' => 'Peak Memory Usage',
            'realPeakMemoryUsage' =>  'Real Peak Memory Usage',
        ];

        $reportData = [
            'cpuTime' => $report->cpuTime->forHuman(),
            'executionTime' => $report->executionTime->forHuman(),
            'memoryUsage' => $report->memoryUsage->forHuman(),
            'realMemoryUsage' => $report->realMemoryUsage->forHuman(),
            'peakMemoryUsage' => $report->peakMemoryUsage->forHuman(),
            'realPeakMemoryUsage' => $report->realPeakMemoryUsage->forHuman(),
        ];

        $table = (new Table($this->output))
            ->setHeaders([
                $this->translator->translate('metrics'),
                $this->translator->translate('iterations'),
                $this->translator->translate('minimum'),
                $this->translator->translate('maximum'),
                $this->translator->translate('median'),
                $this->translator->translate('sum'),
                $this->translator->translate('range'),
                $this->translator->translate('average'),
                $this->translator->translate('variance'),
                $this->translator->translate('std_dev'),
                $this->translator->translate('coef_var'),
            ]);

        foreach ($reportData as $name => $stats) {
            $table->addRow([
                $reportPropertyNames[$name],
                $stats['iterations'],
                $stats['minimum'],
                $stats['maximum'],
                $stats['median'],
                $stats['sum'],
                $stats['range'],
                $stats['average'],
                $stats['variance'],
                $stats['std_dev'],
                $stats['coef_var'],
            ]);
        }

        $table->render();
    }

    public function exportEnvironment(Environment $environment): void
    {
        /** @var EnvironmentHumanReadable $stats */
        $stats = $environment->forHuman();

        $this->renderTable($stats);
    }

    /**
     * @param array<string, scalar> $stats
     */
    private function renderTable(array $stats): void
    {
        $fields = array_keys($stats);

        (new Table($this->output))
            ->setHeaders(array_map($this->translator->translate(...), $fields))
            ->addRow($stats)
            ->setVertical()
            ->render();
    }
}
