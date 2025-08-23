<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Stringable;

use function exec;
use function implode;
use function is_numeric;
use function max;
use function preg_match;
use function sprintf;
use function str_repeat;
use function trim;

use const PHP_EOL;

final class LeaderPrinter
{
    /** @var list<array{0:string, 1:string}> */
    private array $pairs = [];

    public function __construct(
        private string $filler = '.',
        private int $padExtra = 1,
        private ?AnsiStyle $colorKey = AnsiStyle::Green,
        private ?AnsiStyle $colorValue = AnsiStyle::Cyan,
    ) {
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
     * Format a leader list with left-aligned keys, right-aligned values,
     * and dots filling the space in between.
     *
     * @return list<string> Formatted lines
     */
    public function format(?int $terminalWitdh = null): array
    {
        $lines = [];
        $terminalWitdh ??= self::detectTerminalWidth();
        foreach ($this->pairs as [$key, $value]) {
            $dotsCount = $terminalWitdh - mb_strlen($value) - mb_strlen($key) - (2 * $this->padExtra);
            $lines[] = sprintf(
                '%s %s %s',
                null !== $this->colorKey ? Ansi::write($key, $this->colorKey) : $key,
                str_repeat($this->filler, max(1, $dotsCount)),
                null !== $this->colorValue ? Ansi::write($value, $this->colorValue) : $value,
            );
        }

        return $lines;
    }

    /**
     * Render formatted lines as a single string.
     */
    public function render(?int $terminalWitdh = null, string $separator = PHP_EOL): string
    {
        return implode($separator, $this->format($terminalWitdh));
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
