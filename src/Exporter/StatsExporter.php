<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\AnsiStyle;
use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\LeaderPrinter;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Statistics;
use Bakame\Stackwatch\Table;
use Bakame\Stackwatch\Timeline;
use Bakame\Stackwatch\Translator;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function fwrite;

/**
 * @phpstan-import-type MetricsHumanReadable from Metrics
 * @phpstan-import-type EnvironmentHumanReadable from Environment
 * @phpstan-import-type SnapshotHumanReadable from Snapshot
 */
final class StatsExporter
{
    /** @var resource|OutputInterface */
    private $output;
    public readonly Environment $environment;

    /**
     * @param resource|OutputInterface $output
     */
    public function __construct(
        mixed $output,
        public readonly Translator $translator = new Translator(),
        ?Environment $environment = null,
    ) {
        $this->output = $output;
        $this->environment = $environment ?? Environment::current();
    }

    public function write(string $content): int|false
    {
        if ($this->output instanceof OutputInterface) {
            $this->output->write($content);

            return 0;
        }

        return fwrite($this->output, $content);
    }

    public function writeln(string $content): int|false
    {
        return $this->write($content."\n");
    }

    /**
     * @param array<string, string> $data
     */
    private function writeLeaderPrinter(array $data): void
    {
        $renderer = (new LeaderPrinter())->setPairs($this->translator->translateArrayKeys($data));

        if ($this->environment->isCli()) {
            $this->writeln($renderer->render());

            return;
        }

        echo $renderer->renderHtml();
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        $this->writeLeaderPrinter($snapshot->toHuman());
    }

    public function exportMetrics(Result|Span|Metrics $metrics): void
    {
        $source = match ($metrics::class) {
            Result::class => $metrics->span->metrics,
            Span::class => $metrics->metrics,
            Metrics::class => $metrics,
        };

        $this->writeLeaderPrinter($source->toHuman());
    }

    public function exportEnvironment(Environment $environment): void
    {
        $this->writeLeaderPrinter($environment->toHuman());
    }

    public function exportStatistics(Statistics $statistics): void
    {
        $this->writeLeaderPrinter($statistics->toHuman());
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

        if ($this->environment->isCli()) {
            $this->writeln($tableRenderer->render());

            return;
        }

        echo $tableRenderer->renderHtml();
    }

    public function exportTimeline(Timeline $timeline): void
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
        if ($timeline->hasNoSnapshots()) {
            $tableRenderer->addRow([
                [
                    'value' => 'Not enough snapshot to generate an export',
                    'align' => 'center',
                    'style' => [AnsiStyle::BrightRed, AnsiStyle::Bold],
                    'colspan' => 8,
                ],
            ]);
            if ($this->environment->isCli()) {
                $this->writeln($tableRenderer->render());

                return;
            }

            echo $tableRenderer->renderHtml();
            return;
        }

        foreach ($timeline as $snapshot) {
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
        $tableRenderer->addRowSeparator();
        $summary = $timeline->summarize('summary')->metrics->toHuman();
        $tableRenderer->addRow([
            ['value' => 'Summary ', 'style' => [AnsiStyle::BrightGreen], 'align' => 'right', 'colspan' => 2],
            ['value' => $summary['execution_time'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['cpu_time'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['memory_usage'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['real_memory_usage'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['peak_memory_usage'], 'style' => [AnsiStyle::BrightGreen]],
            ['value' => $summary['real_peak_memory_usage'], 'style' => [AnsiStyle::BrightGreen]],
        ]);

        if ($this->environment->isCli()) {
            $this->writeln($tableRenderer->render());

            return;
        }

        echo $tableRenderer->renderHtml();
    }
}
