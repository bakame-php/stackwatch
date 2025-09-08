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
use function random_int;

use const PHP_EOL;

/**
 * @phpstan-import-type MetricsHumanReadable from Metrics
 * @phpstan-import-type EnvironmentHumanReadable from Environment
 * @phpstan-import-type SnapshotHumanReadable from Snapshot
 */
final class ViewExporter implements Exporter
{
    public readonly Environment $environment;

    private static bool $styleRendered = false;

    public static function stylesheet(): string
    {
        return <<<CSS
.bkm-sw-container {border: 1px #c5cdd5 solid;background-color: #18171B;padding: .5em .5em;margin: 1em auto;font-family: "IBM Plex Mono", mono, source-code-pro, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;font-size:12px;}
.bkm-sw-container .bkm-sw-header {padding: .3em .7em; font-size: 12px;}
CSS;
    }

    public static function style(): string
    {
        return '<style>'.PHP_EOL.
            ViewExporter::stylesheet().PHP_EOL.
            Table::stylesheet().PHP_EOL.
            LeaderPrinter::stylesheet().PHP_EOL.
            AnsiStyle::stylesheet().PHP_EOL.
            '</style>'.PHP_EOL;
    }

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

    private function export(Renderer $renderer, ?string $type = null): void
    {
        if ($this->environment->isCli()) {
            $this->writeln($renderer->renderCli());

            return;
        }

        if (null === $type) {
            echo $renderer->renderHtml();

            return;
        }

        $this->containerStart($type);
        echo $renderer->renderHtml();
        $this->containerEnd();
    }

    public function renderStyle(): void
    {
        if ($this->environment->isCli()) {
            return;
        }

        if (!self::$styleRendered) {
            echo self::style();
            self::$styleRendered = true;
        }
    }

    private function containerStart(string $type): void
    {
        if ($this->environment->isCli()) {
            return;
        }

        echo '<div class="bkm-sw-container" id="bkm-sw-'.$type.'-'.random_int(0, 100_000).'">'.PHP_EOL;
    }

    private function containerEnd(): void
    {
        if ($this->environment->isCli()) {
            return;
        }

        echo '</div>'.PHP_EOL;
    }

    private function formatPath(CallLocation $location, AnsiStyle ...$styles): string
    {
        $ide = Ide::fromEnv();
        $path = $ide->path($location);
        if ($this->environment->isCli()) {
            return $path;
        }

        return '<a class="'.AnsiStyle::inlineClasses(...$styles).' hover:bkm-sw-ansi-bright-white" href="'.$ide->uri($location).'">'.$path.'</a>';
    }

    /**
     * @param array<string, string> $data
     */
    private function exportLeaderPrinter(array $data, ?string $type = null): void
    {
        $this->export(
            (new LeaderPrinter())
                ->setStylesKey(AnsiStyle::BrightGreen, AnsiStyle::Bold)
                ->setStylesValue(AnsiStyle::BrightCyan, AnsiStyle::Bold)
                ->setPairs($this->translator->translateArrayKeys($data)),
            $type
        );
    }

    public function exportSpan(Result|Span $span): void
    {
        $this->renderStyle();
        $this->containerStart('span');
        $source = match ($span::class) {
            Result::class => $span->span,
            Span::class => $span,
        };

        $this->exportLeaderPrinter([
            'label' => $source->label,
            'call_location_start' => $this->formatPath($source->range->start, AnsiStyle::BrightCyan, AnsiStyle::Bold),
            'call_location_end' => $this->formatPath($source->range->end, AnsiStyle::BrightCyan, AnsiStyle::Bold),
        ]);
        $this->buildMetrics($source->metrics);
        $this->containerEnd();
    }

    public function exportStatistics(Statistics $statistics, ?MetricType $type = null): void
    {
        $this->renderStyle();
        $this->exportLeaderPrinter(match ($type) {
            null => $statistics->toHuman(),
            default => [...['type' => $type->value], ...$statistics->toHuman()],
        }, 'statistics');
    }

    private function buildMetrics(Result|Span|Metrics $metrics, ?AggregationType $type = null): void
    {
        $source = match ($metrics::class) {
            Result::class => $metrics->span->metrics,
            Span::class => $metrics->metrics,
            Metrics::class => $metrics,
        };

        $this->exportLeaderPrinter(match ($type) {
            null => $source->toHuman(),
            default => [...['type' => $type->value], ...$source->toHuman()],
        });
    }

    private function buildReport(Report $report): void
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
    public function exportProfiler(Profiler $profiler, callable|string|null $label = null): void
    {
        $this->renderStyle();
        $input = match (true) {
            null === $label => iterator_to_array($profiler),
            is_callable($label) => $profiler->filter($label),
            default => $profiler->getAll($label),
        };

        $tableRenderer = Table::dashed()
            ->setHeader(array_map($this->translator->translate(...), [...['label'], ...array_keys(Metrics::none()->toArray())]))
            ->setHeaderStyle(AnsiStyle::BrightGreen)
            ->setTitle($profiler->identifier())
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

        $this->export($tableRenderer, 'profile');
    }

    /**
     * @param ?callable(Snapshot): bool $filter
     */
    public function exportTimeline(Timeline $timeline, ?callable $filter = null): void
    {
        $this->renderStyle();
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
            $this->export($tableRenderer, 'timeline');
            return;
        }

        foreach ($snapshots as $snapshot) {
            $data = $snapshot->toHuman();
            $tableRenderer->addRow([
                $data['label'],
                $this->formatPath(new CallLocation($data['origin_path'], (int) $data['origin_line']), AnsiStyle::Bold, AnsiStyle::BrightCyan),
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

    private function renderStackMetadata(?Profile $profile, ?CallLocation $callLocation): void
    {
        if (null === $callLocation && null !== $profile) {
            return;
        }

        $this->export(
            (new StackMetadata())
                ->setCollLocation($callLocation)
                ->setProfile($profile)
        );
    }

    public function exportEnvironment(Environment $environment): void
    {
        $this->renderStyle();
        $this->exportLeaderPrinter($environment->toHuman(), 'environment');
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        $this->renderStyle();
        $data = $snapshot->toHuman();
        $location = new CallLocation($snapshot->originPath, $snapshot->originLine);
        unset($data['origin_path'], $data['origin_line']);
        $data['call_location'] = $this->formatPath($location, AnsiStyle::BrightCyan, AnsiStyle::Bold);

        $this->exportLeaderPrinter($data, 'snapshot');
    }

    public function exportReport(Report $report): void
    {
        $this->renderStyle();
        $this->containerStart('report');
        $this->buildReport($report);
        $this->containerEnd();
    }

    public function exportMetrics(Metrics $metrics, ?AggregationType $type = null): void
    {
        $this->renderStyle();
        $this->containerStart('metrics');
        $this->buildMetrics($metrics, $type);
        $this->containerEnd();
    }

    public function exportStack(Report|Metrics $stats, ?Profile $profile = null, ?CallLocation $callLocation = null): void
    {
        $this->renderStyle();
        $this->containerStart($stats instanceof Report ? 'report' : 'metrics');
        $this->renderStackMetadata($profile, $callLocation);
        $stats instanceof Report ? $this->buildReport($stats) : $this->buildMetrics($stats);
        $this->containerEnd();
    }
}
