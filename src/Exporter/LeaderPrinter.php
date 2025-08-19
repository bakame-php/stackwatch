<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

final class LeaderPrinter
{
    public function __construct(private string $filler = '.', private int $padExtra = 1)
    {
    }

    /**
     * Format a leader list with left-aligned keys, right-aligned values,
     * and dots filling the space in between.
     *
     * @param array<string,string> $pairs Key => Value
     *
     * @return list<string> Formatted lines
     */
    public function format(array $pairs, ?int $terminalWitdh = null): array
    {
        $lines = [];
        $terminalWitdh ??= self::detectTerminalWidth();
        foreach ($pairs as $key => $value) {
            $dotsCount = $terminalWitdh - mb_strlen((string) $value) - mb_strlen($key) - (2 * $this->padExtra);
            $lines[] = sprintf(
                '%s %s %s',
                $key,
                str_repeat($this->filler, max(1, $dotsCount)),
                $value
            );
        }

        return $lines;
    }

    /**
     * Render formatted lines as a single string.
     *
     * @param array<string,string> $pairs
     */
    public function render(array $pairs, string $separator = PHP_EOL, ?int $terminalWitdh = null): string
    {
        return implode($separator, $this->format($pairs, $terminalWitdh));
    }

    /**
     * Detects terminal width across platforms.
     */
    public static function detectTerminalWidth(int $fallback = 80): int
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
