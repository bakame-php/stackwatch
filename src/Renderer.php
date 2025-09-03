<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Exporter\StatsExporter;

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
.bkm-sw-header {padding: .3em .7em; font-size: 12px;}
CSS;

    }

    public function __construct(public readonly StatsExporter $exporter)
    {
    }

    public function render(Profile $profile, CallLocation $callLocation, Metrics|Report $stats): void
    {
        $this->exporter->environment->isCli()
            ? $this->terminal($profile, $callLocation, $stats)
            : $this->browser($profile, $callLocation, $stats);
    }

    public function terminal(Profile $profile, CallLocation $callLocation, Metrics|Report $stats): void
    {
        $this->exporter->writeln(
            Ansi::write('Path: ', AnsiStyle::Green)
            .Ansi::writeln($callLocation->path.':'.$callLocation->line, AnsiStyle::Yellow)
            .Ansi::write('Iterations: ', AnsiStyle::Green)
            .Ansi::write($profile->iterations, AnsiStyle::Yellow)
            .Ansi::write('; ')
            .Ansi::write('Warmup: ', AnsiStyle::Green)
            .Ansi::write($profile->warmup, AnsiStyle::Yellow)
            .Ansi::write('; ')
            .Ansi::write('Type: ', AnsiStyle::Green)
            .Ansi::write(null !== $profile->type ? ucfirst($profile->type->value).' only' : 'Detailed', AnsiStyle::Yellow)
            .Ansi::writeln('')
        );

        $stats instanceof Report ? $this->exporter->exportReport($stats) : $this->exporter->exportMetrics($stats);
    }

    public function browser(Profile $profile, CallLocation $callLocation, Metrics|Report $stats): void
    {
        $html = [];
        $html[] = '<p class="bkm-sw-header">';
        $html[] = AnsiStyle::wrapHtml('class', 'Path: ', AnsiStyle::Green);
        $html[] = AnsiStyle::wrapHtml('class', $callLocation->path.':'.$callLocation->line, AnsiStyle::Yellow);
        $html[] = '<br>';
        $html[] = AnsiStyle::wrapHtml('class', 'Iterations: ', AnsiStyle::Green);
        $html[] = AnsiStyle::wrapHtml('class', $profile->iterations, AnsiStyle::Yellow);
        $html[] = AnsiStyle::wrapHtml('class', '; ');
        $html[] = AnsiStyle::wrapHtml('class', 'Warmup: ', AnsiStyle::Green);
        $html[] = AnsiStyle::wrapHtml('class', $profile->warmup, AnsiStyle::Yellow);
        $html[] = AnsiStyle::wrapHtml('class', '; ');
        $html[] = AnsiStyle::wrapHtml('class', 'Type: ', AnsiStyle::Green);
        $html[] = AnsiStyle::wrapHtml('class', null !== $profile->type ? ucfirst($profile->type->value).' only' : 'Detailed', AnsiStyle::Yellow);
        $html[] = AnsiStyle::wrapHtml('class', '; ');
        $html[] = '</p>';

        self::loadCss();

        echo '<div class="bkm-sw-container" id="bkm-sw-profile-'.random_int(0, 100_000).'">';
        echo implode('', $html);
        $stats instanceof Report ? $this->exporter->exportReport($stats) : $this->exporter->exportMetrics($stats);
        echo '</div>', PHP_EOL;
    }

    public static function loadCss(): void
    {
        if (! self::$stylesLoaded) {
            echo '<style>', PHP_EOL;
            echo Renderer::stylesheet(), PHP_EOL;
            echo LeaderPrinter::stylesheet(), PHP_EOL;
            echo AnsiStyle::stylesheet(), PHP_EOL;
            echo Table::stylesheet(), PHP_EOL;
            echo '</style>', PHP_EOL;
            self::$stylesLoaded = true;
        }
    }
}
