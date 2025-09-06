<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use function implode;
use function random_int;
use function ucfirst;

use const PHP_EOL;

final class Renderer
{
    private static bool $stylesLoaded = false;

    public static function stylesheet(): string
    {
        return <<<CSS
.bkm-sw-container {border: 1px #c5cdd5 solid;background-color: #18171B;padding: .5em .5em;margin: 1em auto;font-family: "IBM Plex Mono", mono, source-code-pro, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;font-size:12px;}
.bkm-sw-container .bkm-sw-header {padding: .3em .7em; font-size: 12px;}
CSS;
    }

    public static function loadCss(): void
    {
        if (! self::$stylesLoaded) {
            echo '<style>', PHP_EOL;
            echo Renderer::stylesheet(), PHP_EOL;
            echo Table::stylesheet(), PHP_EOL;
            echo LeaderPrinter::stylesheet(), PHP_EOL;
            echo AnsiStyle::stylesheet(), PHP_EOL;
            echo '</style>', PHP_EOL;
            self::$stylesLoaded = true;
        }
    }

    public function __construct(public readonly ViewExporter $exporter = new ViewExporter())
    {
    }

    public function render(Report|Metrics $stats, ?Profile $profile = null, ?CallLocation $callLocation = null): void
    {
        match ($stats::class) {
            Report::class => $this->renderReport($stats, $profile, $callLocation),
            Metrics::class => $this->renderMetrics($stats, $profile, $callLocation),
        };
    }

    public function renderSnapshot(Snapshot $snapshot): void
    {
        if ($this->exporter->environment->isCli()) {
            $this->exporter->exportSnapshot($snapshot);

            return;
        }

        self::loadCss();

        echo '<div class="bkm-sw-container" id="bkm-sw-snapshot-'.random_int(0, 100_000).'">';
        $this->exporter->exportSnapshot($snapshot);
        echo '</div>', PHP_EOL;
    }

    public function renderEnvironment(?Environment $environment = null): void
    {
        $environment = $environment ?? $this->exporter->environment;
        if ($this->exporter->environment->isCli()) {
            $this->exporter->exportEnvironment($environment);

            return;
        }

        self::loadCss();

        echo '<div class="bkm-sw-container" id="bkm-sw-environment-'.random_int(0, 100_000).'">';
        $this->exporter->exportEnvironment($environment);
        echo '</div>', PHP_EOL;
    }

    /**
     * @param non-empty-string $name
     */
    public function renderStatistics(Statistics $statistics, string $name): void
    {
        if ($this->exporter->environment->isCli()) {
            $this->exporter->exportStatistics($statistics, $name);

            return;
        }

        self::loadCss();

        echo '<div class="bkm-sw-container" id="bkm-sw-span-'.random_int(0, 100_000).'">';
        $this->exporter->exportStatistics($statistics, $name);
        echo '</div>', PHP_EOL;
    }

    public function renderSpan(Result|Span $span): void
    {
        if ($this->exporter->environment->isCli()) {
            $this->exporter->exportSpan($span);

            return;
        }

        self::loadCss();

        echo '<div class="bkm-sw-container" id="bkm-sw-span-'.random_int(0, 100_000).'">';
        $this->exporter->exportSpan($span);
        echo '</div>', PHP_EOL;
    }

    /**
     * @param ?callable(Snapshot): bool $filter
     */
    public function renderTimeline(Timeline $timeline, ?callable $filter = null): void
    {
        if ($this->exporter->environment->isCli()) {
            $this->exporter->exportTimeline($timeline, $filter);

            return;
        }

        self::loadCss();

        echo '<div class="bkm-sw-container" id="bkm-sw-timeline-'.random_int(0, 100_000).'">';
        $this->exporter->exportTimeline($timeline, $filter);
        echo '</div>', PHP_EOL;
    }

    public function renderSpanAggregator(Profiler $spanAggregator, callable|string|null $label = null): void
    {
        if ($this->exporter->environment->isCli()) {
            $this->exporter->exportProfiler($spanAggregator, $label);

            return;
        }

        self::loadCss();

        echo '<div class="bkm-sw-container" id="bkm-sw-span-aggregator-'.random_int(0, 100_000).'">';
        $this->exporter->exportProfiler($spanAggregator, $label);
        echo '</div>', PHP_EOL;
    }

    public function renderReport(Report $stats, ?Profile $profile = null, ?CallLocation $callLocation = null): void
    {
        $header = $this->renderHeader($profile, $callLocation);
        if ($this->exporter->environment->isCli()) {
            $this->exporter->writeln($header);
            $this->exporter->exportReport($stats);

            return;
        }

        self::loadCss();
        echo '<div class="bkm-sw-container" id="bkm-sw-report-'.random_int(0, 100_000).'">';
        echo $header;
        $this->exporter->exportReport($stats);
        echo '</div>', PHP_EOL;
    }

    public function renderMetrics(Metrics $stats, ?Profile $profile = null, ?CallLocation $callLocation = null): void
    {
        $header = $this->renderHeader($profile, $callLocation);
        if ($this->exporter->environment->isCli()) {
            $this->exporter->writeln($header);
            $this->exporter->exportMetrics($stats);

            return;
        }

        self::loadCss();
        echo '<div class="bkm-sw-container" id="bkm-sw-metrics-'.random_int(0, 100_000).'">';
        echo $header;
        $this->exporter->exportMetrics($stats);
        echo '</div>', PHP_EOL;
    }

    private function renderHeader(?Profile $profile, ?CallLocation $callLocation): string
    {
        if (null === $callLocation && null !== $profile) {
            return '';
        }

        $header = [];
        if ($this->exporter->environment->isCli()) {
            if (null !== $callLocation) {
                $header[] = Ansi::write('Path: ', AnsiStyle::Green);
                $header[] = Ansi::writeln($callLocation->path.':'.$callLocation->line, AnsiStyle::Yellow);
            }

            if (null !== $profile) {
                $header[] = Ansi::write('Iterations: ', AnsiStyle::Green);
                $header[] = Ansi::write($profile->iterations, AnsiStyle::Yellow);
                $header[] = Ansi::write('; ');
                $header[] = Ansi::write('Warmup: ', AnsiStyle::Green);
                $header[] = Ansi::write($profile->warmup, AnsiStyle::Yellow);
                $header[] = Ansi::write('; ');
                $header[] = Ansi::write('Type: ', AnsiStyle::Green);
                $header[] = Ansi::write(null !== $profile->type ? ucfirst($profile->type->value).' only' : 'Detailed', AnsiStyle::Yellow);
                $header[] = Ansi::writeln('');
            }

            return implode('', $header);
        }

        $header[] = '<p class="bkm-sw-header">';
        if (null !== $callLocation) {
            $header[] = AnsiStyle::wrapHtml('class', 'Path: ', AnsiStyle::BrightGreen, AnsiStyle::Bold);
            $header[] = $this->exporter->formatPath($callLocation, AnsiStyle::BrightCyan, AnsiStyle::Bold);
            $header[] = '<br>';
        }

        if (null !== $profile) {
            $header[] = AnsiStyle::wrapHtml('class', 'Iterations: ', AnsiStyle::BrightGreen, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', $profile->iterations, AnsiStyle::Yellow, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', '; ');
            $header[] = AnsiStyle::wrapHtml('class', 'Warmup: ', AnsiStyle::BrightGreen, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', $profile->warmup, AnsiStyle::Yellow, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', '; ');
            $header[] = AnsiStyle::wrapHtml('class', 'Type: ', AnsiStyle::BrightGreen, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', null !== $profile->type ? ucfirst($profile->type->value).' only' : 'Detailed', AnsiStyle::Yellow, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', '; ');
        }

        $header[] = '</p>';

        return implode(PHP_EOL, $header);
    }
}
