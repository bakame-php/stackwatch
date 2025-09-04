<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Stringable;

use function array_values;
use function exec;
use function implode;
use function is_numeric;
use function max;
use function preg_match;
use function str_repeat;
use function trim;

final class LeaderPrinter
{
    /** @var list<array{0:string, 1:string}> */
    private array $pairs = [];

    /**
     * @param list<AnsiStyle> $stylesKey
     * @param list<AnsiStyle> $stylesValue
     */
    public function __construct(
        private string $filler = '.',
        private int $padExtra = 1,
        private array $stylesKey = [],
        private array $stylesValue = [],
    ) {
    }

    public function setStylesKey(AnsiStyle ...$styles): self
    {
        $this->stylesKey = array_values($styles);

        return $this;
    }

    public function setStylesValue(AnsiStyle ...$styles): self
    {
        $this->stylesValue = array_values($styles);

        return $this;
    }

    public static function stylesheet(): string
    {
        return <<<CSS
.bkm-sw-dotted-list {width: 100%; list-style: none; padding:0;}
.bkm-sw-dotted-item {display: flex; padding: .3em .7em; align-items: center;}
.bkm-sw-dotted-item:hover {background-color: #333;}
.bkm-sw-dotted-item .bkm-sw-dots {flex: 1;border-bottom: 1px dotted #666;margin: 0 0.5em; color:transparent;}
CSS;
    }

    /**
     * @param iterable<array-key, Stringable|float|int|string|null> $pairs
     *
     * @return $this
     */
    public function setPairs(iterable $pairs): self
    {
        foreach ($pairs as $key => $value) {
            $this->addPair($key, $value);
        }

        return $this;
    }

    public function addPair(string|int $key, Stringable|float|int|string|null $value): self
    {
        $this->pairs[] = [(string) $key, (string) $value];

        return $this;
    }

    /**
     * Format a leader list for console output.
     */
    public function render(): string
    {
        $lines = [];
        $terminalWitdh = self::detectTerminalWidth();
        foreach ($this->pairs as [$key, $value]) {
            $dotsCount = $terminalWitdh - mb_strlen($value) - mb_strlen($key) - (2 * $this->padExtra);
            $lines[] = Ansi::write($key, ...$this->stylesKey).' '
                .str_repeat($this->filler, max(1, $dotsCount)).' '
                .Ansi::write($value, ...$this->stylesValue);
        }

        return implode("\n", $lines);
    }

    /**
     * Format a leader list for HTML output.
     */
    public function renderHtml(): string
    {
        $lines = ['<ul class="bkm-sw-dotted-list bkm-sw-ansi-bold">'];
        foreach ($this->pairs as [$key, $value]) {
            $lines[] = '<li class="bkm-sw-dotted-item">'
                .AnsiStyle::wrapHtml('class', $key, ...$this->stylesKey)
                .'<span class="bkm-sw-dots">:</span>'
                .AnsiStyle::wrapHtml('class', $value, ...$this->stylesValue)
                .'</li>';
        }
        $lines[] = '</ul>';

        return implode("\n", $lines);
    }

    /**
     * Detects terminal width across platforms.
     */
    private static function detectTerminalWidth(int $fallback = 80): int
    {
        /** @var string|false $cols */
        $cols = exec('tput cols 2>/dev/null');
        if (is_numeric($cols) && $cols > 0) {
            return (int) $cols;
        }

        /** @var string|false $stty */
        $stty = exec('stty size 2>/dev/null');
        if (false !== $stty && 1 === preg_match('/^\d+\s+(?<size>\d+)$/', trim($stty), $match)) {
            return (int) $match['size'];
        }

        /** @var string|false $mode */
        $mode = exec('mode con');
        if (false !== $mode && 1 === preg_match('/Columns:\s+(?<size>\d+)/i', $mode, $match)) {
            return (int) $match['size'];
        }

        return $fallback;
    }
}
