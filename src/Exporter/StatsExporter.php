<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\AnsiStyle;
use Bakame\Stackwatch\ConsoleTable;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\LeaderPrinter;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Translator;

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
    /** @var resource */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct(
        $stream,
        public readonly LeaderPrinter $leaderPrinter = new LeaderPrinter(),
        public readonly Translator $translator = new Translator(),
    ) {
        $this->stream = $stream;
    }

    public function write(string $content): int|false
    {
        return fwrite($this->stream, $content);
    }

    public function writeln(string $content): int|false
    {
        return fwrite($this->stream, $content."\n");
    }

    public function exportMetrics(Result|Span|Metrics $metrics): void
    {
        /** @var Metrics $source */
        $source = match ($metrics::class) {
            Result::class => $metrics->span->metrics,
            Span::class => $metrics->metrics,
            Metrics::class => $metrics,
        };

        $this->writeLeaderPrinter($source->toHuman());
    }

    /**
     * @param array<string, string> $data
     */
    private function writeLeaderPrinter(array $data): void
    {
        foreach ($this->leaderPrinter->setPairs($this->translator->translateArrayKeys($data))->format() as $line) {
            $this->writeln($line);
        }
    }

    public function exportSnapshots(Snapshot $snapshot): void
    {
        $this->writeLeaderPrinter($snapshot->toHuman());
    }

    public function exportEnvironement(Environment $environment): void
    {
        $this->writeLeaderPrinter($environment->toHuman());
    }

    public function exportReport(Report $report): void
    {
        $reportData = $report->toHuman();
        $headers = array_map($this->translator->translate(...), array_merge(['metrics'], array_keys($reportData['cpu_time'])));
        $rows = [];
        foreach ($reportData as $name => $statsForHuman) {
            $rows[] = array_values(array_merge([$this->translator->translate($name)], $statsForHuman));
        }

        $tableRenderer = ConsoleTable::dashed()
            ->setHeader($headers)
            ->setHeaderColor(AnsiStyle::Green)
            ->setRows($rows)
            ->setRowsColor([['column' => 0, 'color' => AnsiStyle::Green]]);

        foreach ($tableRenderer->format() as $line) {
            $this->writeln($line);
        }
    }
}
