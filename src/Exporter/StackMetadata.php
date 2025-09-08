<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\CallLocation;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Profile;

use function implode;
use function ucfirst;

use const PHP_EOL;

final class StackMetadata implements Renderer
{
    private ?CallLocation $callLocation = null;
    private ?Profile $profile = null;

    public function setCollLocation(?CallLocation $location): self
    {
        $this->callLocation = $location;

        return $this;
    }

    public function setProfile(?Profile $profile): self
    {
        $this->profile = $profile;

        return $this;
    }

    public function renderCli(): string
    {
        if (null === $this->callLocation && null === $this->profile) {
            return '';
        }

        $header = [];
        if (null !== $this->callLocation) {
            $header[] = Ansi::write('Path: ', AnsiStyle::Green);
            $header[] = Ansi::writeln($this->callLocation->path.':'.$this->callLocation->line, AnsiStyle::Yellow);
        }

        if (null !== $this->profile) {
            $header[] = Ansi::write('Iterations: ', AnsiStyle::Green);
            $header[] = Ansi::write($this->profile->iterations, AnsiStyle::Yellow);
            $header[] = Ansi::write('; ');
            $header[] = Ansi::write('Warmup: ', AnsiStyle::Green);
            $header[] = Ansi::write($this->profile->warmup, AnsiStyle::Yellow);
            $header[] = Ansi::write('; ');
            $header[] = Ansi::write('Type: ', AnsiStyle::Green);
            $header[] = Ansi::write(null !== $this->profile->type ? ucfirst($this->profile->type->value).' only' : 'Detailed', AnsiStyle::Yellow);
            $header[] = Ansi::writeln('');
        }

        return implode('', $header);
    }

    public function renderHtml(): string
    {
        $header = ['<p class="bkm-sw-header">'];
        if (null !== $this->callLocation) {
            $header[] = AnsiStyle::wrapHtml('class', 'Path: ', AnsiStyle::BrightGreen, AnsiStyle::Bold);
            $header[] = $this->formatPath($this->callLocation, AnsiStyle::BrightCyan, AnsiStyle::Bold);
            $header[] = '<br>';
        }

        if (null !== $this->profile) {
            $header[] = AnsiStyle::wrapHtml('class', 'Iterations: ', AnsiStyle::BrightGreen, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', $this->profile->iterations, AnsiStyle::Yellow, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', '; ');
            $header[] = AnsiStyle::wrapHtml('class', 'Warmup: ', AnsiStyle::BrightGreen, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', $this->profile->warmup, AnsiStyle::Yellow, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', '; ');
            $header[] = AnsiStyle::wrapHtml('class', 'Type: ', AnsiStyle::BrightGreen, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', null !== $this->profile->type ? ucfirst($this->profile->type->value).' only' : 'Detailed', AnsiStyle::Yellow, AnsiStyle::Bold);
            $header[] = AnsiStyle::wrapHtml('class', '; ');
        }

        $header[] = '</p>';

        return implode(PHP_EOL, $header);
    }

    private function formatPath(CallLocation $location, AnsiStyle ...$styles): string
    {
        $ide = Ide::fromEnv();
        $path = $ide->path($location);
        if (Environment::current()->isCli()) {
            return $path;
        }

        return '<a class="'.AnsiStyle::inlineClasses(...$styles).' hover:bkm-sw-ansi-bright-white" href="'.$ide->uri($location).'">'.$path.'</a>';
    }
}
