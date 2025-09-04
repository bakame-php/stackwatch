<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Symfony\Component\Console\Output\OutputInterface;

use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function is_callable;
use function iterator_to_array;

/**
 * @phpstan-import-type MetricsHumanReadable from Metrics
 * @phpstan-import-type EnvironmentHumanReadable from Environment
 * @phpstan-import-type SnapshotHumanReadable from Snapshot
 */
final class ViewExporter implements Exporter
{
    public readonly Environment $environment;

    public function __construct(
        public readonly StreamWriter|OutputInterface $output = new StreamWriter(),
        public readonly Translator $translator = new Translator(),
        ?Environment $environment = null,
    ) {
        $this->environment = $environment ?? Environment::current();
    }

    public function write(string $content): void
    {
        $this->output->write($content);
    }

    public function writeln(string $content): void
    {
        $this->write($content."\n");
    }

    private function export(Table|LeaderPrinter $tableRenderer): void
    {
        if ($this->environment->isCli()) {
            $this->writeln($tableRenderer->render());

            return;
        }

        echo $tableRenderer->renderHtml();
    }

    /**
     * @param array<string, string> $data
     */
    private function exportLeaderPrinter(array $data): void
    {
        $this->export(
            (new LeaderPrinter())->setPairs($this->translator->translateArrayKeys($data))
        );
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        $this->exportLeaderPrinter($snapshot->toHuman());
    }

    public function exportMetrics(Result|Span|Metrics $metrics): void
    {
        $source = match ($metrics::class) {
            Result::class => $metrics->span->metrics,
            Span::class => $metrics->metrics,
            Metrics::class => $metrics,
        };

        $this->exportLeaderPrinter($source->toHuman());
    }

    public function exportSpan(Result|Span $span): void
    {
        $source = match ($span::class) {
            Result::class => $span->span,
            Span::class => $span,
        };

        $this->exportLeaderPrinter([
            'label' => $source->label,
            'call_location_start' => Ide::fromEnv()->link($source->range->start, null, AnsiStyle::White),
            'call_location_end' => Ide::fromEnv()->link($source->range->end, null, AnsiStyle::White),
        ]);
        $this->exportMetrics($source->metrics);
    }

    public function exportEnvironment(Environment $environment): void
    {
        $this->exportLeaderPrinter($environment->toHuman());
    }

    public function exportStatistics(Statistics $statistics): void
    {
        $this->exportLeaderPrinter($statistics->toHuman());
    }

    public function exportReport(Report $report): void
    {
        $data = $report->toHuman();
        $headers = array_map($this->translator->translate(...), array_merge(['metrics'], array_keys($data['cpu_time'])));
        $rows = [];
        foreach ($data as $name => $statsForHuman) {
            $rows[] = array_values(array_merge([$this->translator->translate($name)], $statsForHuman));
        }

        $tableRenderer = Table::dashed()
            ->setHeader($headers)
            ->setHeaderStyle(AnsiStyle::BrightGreen)
            ->setRows($rows)
            ->setRowStyle([['column' => 0, 'style' => [AnsiStyle::BrightGreen, AnsiStyle::Bold], 'align' => 'left']]);

        $this->export($tableRenderer);
    }

    /**
     * @param (callable(Span): bool)|string|null $label
     */
    public function exportSpanAggregator(SpanAggregator $spanAggregator, callable|string|null $label = null): void
    {
        $input = match (true) {
            null === $label => iterator_to_array($spanAggregator),
            is_callable($label) => $spanAggregator->filter($label),
            default => $spanAggregator->getAll($label),
        };

        $tableRenderer = Table::dashed()
            ->setHeader(array_map($this->translator->translate(...), [...['label'], ...array_keys(Metrics::none()->toArray())]))
            ->setHeaderStyle(AnsiStyle::BrightGreen)
            ->setTitle($spanAggregator->identifier())
            ->setTitleStyle(AnsiStyle::BrightGreen, AnsiStyle::Bold)
            ->setRowStyle([
                ['column' => 0, 'style' => [AnsiStyle::BrightGreen, AnsiStyle::Bold]],
            ])
        ;

        if ([] === $input) {
            $tableRenderer->addRow([
                [
                    'value' => 'Not enough span to generate an export',
                    'align' => 'center',
                    'style' => [AnsiStyle::BrightRed, AnsiStyle::Bold],
                    'colspan' => 10,
                ],
            ]);
            $this->export($tableRenderer);

            return;
        }

        /** @var Span $span */
        foreach ($input as $span) {
            $data = [...['label' => $span->label], ...$span->metrics->toHuman()];
            $tableRenderer->addRow($data);
        }

        $this->export($tableRenderer);
    }

    /**
     * @param ?callable(Snapshot): bool $filter
     */
    public function exportTimeline(Timeline $timeline, ?callable $filter = null): void
    {
        $tableRenderer = Table::dashed()
            ->setHeader([
                $this->translator->translate('label'),
                $this->translator->translate('call_location'),
                $this->translator->translate('timestamp'),
                $this->translator->translate('cpu_time'),
                $this->translator->translate('memory_usage'),
                $this->translator->translate('real_memory_usage'),
                $this->translator->translate('peak_memory_usage'),
                $this->translator->translate('real_peak_memory_usage'),
            ])
            ->setHeaderStyle(AnsiStyle::BrightGreen)
            ->setTitle($timeline->identifier())
            ->setTitleStyle(AnsiStyle::BrightGreen, AnsiStyle::Bold)
            ->setRowStyle([
                ['column' => 3, 'align' => 'right'],
                ['column' => 4, 'align' => 'right'],
                ['column' => 5, 'align' => 'right'],
                ['column' => 6, 'align' => 'right'],
                ['column' => 7, 'align' => 'right'],
            ]);

        $snapshots = null !== $filter ? $timeline->filter($filter) : iterator_to_array($timeline);
        if ([] === $snapshots) {
            $tableRenderer->addRow([
                [
                    'value' => 'Not enough snapshot to generate an export',
                    'align' => 'center',
                    'style' => [AnsiStyle::BrightRed, AnsiStyle::Bold],
                    'colspan' => 8,
                ],
            ]);
            $this->export($tableRenderer);
            return;
        }

        foreach ($snapshots as $snapshot) {
            $data = $snapshot->toHuman();
            $tableRenderer->addRow([
                $data['label'],
                $data['origin_path'].':'.$data['origin_line'],
                $snapshot->timestamp->format('U.u'),
                DurationUnit::format($snapshot->cpuUserTime + $snapshot->cpuSystemTime, 3),
                $data['memory_usage'],
                $data['real_memory_usage'],
                $data['peak_memory_usage'],
                $data['real_peak_memory_usage'],
            ]);
        }

        if (2 < count($snapshots)) {
            $this->export($tableRenderer);

            return;
        }

        $summary = $timeline->summarize('summary')->metrics->toHuman();
        if (null !== $filter) {
            $from = array_key_first($snapshots);
            $to = array_key_last($snapshots);
            $summary = (new Span('summary', $snapshots[$from], $snapshots[$to]))->metrics->toHuman();
        }

        $tableRenderer->addRowSeparator();
        $tableRenderer->addRow([
            ['value' => 'Summary ', 'style' => [AnsiStyle::BrightGreen], 'align' => 'right', 'colspan' => 2],
            ['value' => $summary['execution_time'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['cpu_time'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['memory_usage'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['real_memory_usage'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['peak_memory_usage'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['real_peak_memory_usage'], 'style' => [AnsiStyle::BrightGreen]],
        ]);

        $this->export($tableRenderer);
    }
}
