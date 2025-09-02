<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\AnsiStyle;
use Bakame\Stackwatch\ConsoleTable;
use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\LeaderPrinter;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Statistics;
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

    /**
     * @param resource|OutputInterface $output
     */
    public function __construct(mixed $output, public readonly Translator $translator = new Translator())
    {
        $this->output = $output;
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
        if ($this->output instanceof OutputInterface) {
            $this->output->writeln($content);

            return 0;
        }

        return fwrite($this->output, $content."\n");
    }

    /**
     * @param array<string, string> $data
     */
    private function writeLeaderPrinter(array $data): void
    {
        $this->writeln(
            (new LeaderPrinter())
                ->setPairs($this->translator->translateArrayKeys($data))
                ->render()
        );
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

        $this->writeln(
            ConsoleTable::dashed()
                ->setHeader($headers)
                ->setHeaderStyle(AnsiStyle::Green)
                ->setRows($rows)
                ->setRowStyle([['column' => 0, 'style' => [AnsiStyle::Green, AnsiStyle::Bold]]])
                ->render("\n")
        );
    }

    public function exportTimeline(Timeline $timeline): void
    {
        $tableRenderer = ConsoleTable::dashed()
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
            ->setHeaderStyle(AnsiStyle::Green)
            ->setTitle($timeline->identifier())
            ->setTitleStyle(AnsiStyle::Green, AnsiStyle::Bold)
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
                    'style' => [AnsiStyle::Red, AnsiStyle::Bold],
                    'colspan' => 8,
                ],
            ]);
            $this->writeln($tableRenderer->render("\n"));

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
            ['value' => 'Summary ', 'style' => [AnsiStyle::Green], 'align' => 'right', 'colspan' => 2],
            ['value' => $summary['execution_time'], 'style' => [AnsiStyle::Green]],
            ['value' => $summary['cpu_time'], 'style' => [AnsiStyle::Green]],
            ['value' => $summary['memory_usage'], 'style' => [AnsiStyle::Green]],
            ['value' => $summary['real_memory_usage'], 'style' => [AnsiStyle::Green]],
            ['value' => $summary['peak_memory_usage'], 'style' => [AnsiStyle::Green]],
            ['value' => $summary['real_peak_memory_usage'], 'style' => [AnsiStyle::Green]],
        ]);

        $this->writeln($tableRenderer->render("\n"));
    }

    public function exportProfiler(Profiler $profiler, ?string $label = null): void
    {
    }
}
