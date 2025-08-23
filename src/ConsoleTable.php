<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Closure;
use Stringable;

use function array_map;
use function floor;
use function implode;
use function is_numeric;
use function max;
use function mb_strwidth;
use function str_repeat;

use const PHP_EOL;
use const STR_PAD_BOTH;
use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

/**
 * @phpstan-type borderConfig array{
 *     top: array{left: string, mid: string, right: string},
 *     header_sep: array{left: string, mid: string, right: string},
 *     bottom: array{left: string, mid: string, right: string},
 *     horizontal?: string,
 *     vertical?: string,
 *  }
 * @phpstan-type colorRule array{column:int, color:AnsiStyle, below?:int, above?:int, equal?: int}
 * @phpstan-type colorConfig List<colorRule>
 */
final class ConsoleTable
{
    /** @var list<string>*/
    private array $headers = [];
    /** @var list<array<string>> */
    private array $rows = [];
    private ?string $title = null;

    private function __construct(
        /** @var borderConfig */
        private array $borders,
        /** @var ?AnsiStyle ANSI code for the full header */
        private ?AnsiStyle $headerColor = null,
        /** @var ?AnsiStyle ANSI code for the table title */
        private ?AnsiStyle $titleColor = null,
        /** @var colorConfig */
        private array $colorRules = []
    ) {
    }

    /**
     * @param colorConfig $colorRules
     */
    public static function classic(
        ?AnsiStyle $headerColor = null,
        ?AnsiStyle $titleColor = null,
        array $colorRules = []
    ): self {
        return new self(
            borders: [
                'top' => ['left' => '┌', 'mid' => '┬', 'right' => '┐'],
                'header_sep' => ['left' => '├', 'mid' => '┼', 'right' => '┤'],
                'bottom' => ['left' => '└', 'mid' => '┴', 'right' => '┘'],
            ],
            headerColor: $headerColor,
            titleColor: $titleColor,
            colorRules: $colorRules,
        );
    }

    /**
     * @param colorConfig $colorRules
     */
    public static function doubleLine(
        ?AnsiStyle $headerColor = null,
        ?AnsiStyle $titleColor = null,
        array $colorRules = []
    ): self {
        return new self(
            borders: [
                'top' => ['left' => '╔', 'mid' => '╦', 'right' => '╗'],
                'header_sep' => ['left' => '╠', 'mid' => '╬', 'right' => '╣'],
                'bottom' => ['left' => '╚', 'mid' => '╩', 'right' => '╝'],
            ],
            headerColor: $headerColor,
            titleColor: $titleColor,
            colorRules: $colorRules,
        );
    }

    /**
     * @param colorConfig $colorRules
     */
    public static function dashed(
        ?AnsiStyle $headerColor = null,
        ?AnsiStyle $titleColor = null,
        array $colorRules = []
    ): self {
        return new self(
            borders: [
                'top' => ['left' => '+', 'mid' => '+', 'right' => '+'],
                'header_sep' => ['left' => '+', 'mid' => '+', 'right' => '+'],
                'bottom' => ['left' => '+', 'mid' => '+', 'right' => '+'],
                'horizontal' => '-',
                'vertical' => '|',
            ],
            headerColor: $headerColor,
            titleColor: $titleColor,
            colorRules: $colorRules,
        );
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @param list<string> $headers
     */
    public function setHeader(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param iterable<array<Stringable|string|float|int|null>> $rows
     *
     * @return $this
     */
    public function setRows(iterable $rows): self
    {
        foreach ($rows as $row) {
            $this->addRow($row);
        }

        return $this;
    }

    /**
     * @param array<Stringable|string|float|int|null> $row
     *
     * @return $this
     */
    public function addRow(array $row): self
    {
        $this->rows[] = array_map(fn (Stringable|string|float|int|null $value): string => (string) $value, $row);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function format(): array
    {
        $cellColorFn = self::createCellColorFn($this->colorRules);
        $lines = [];
        foreach ($this->build($this->headerColor, $cellColorFn) as $line) {
            $lines[] = $line;
        }

        if (null !== $this->title) {
            /** @var non-empty-list<int<0, max>> $visibleWidths */
            $visibleWidths = array_map(
                fn (string $line): int => mb_strwidth(Ansi::stripStyle($line)),
                $lines
            );
            $tableWidth = max($visibleWidths);
            $rawTitle = $this->title;
            if (null !== $this->titleColor) {
                $rawTitle = Ansi::write($rawTitle, $this->titleColor);
            }

            $visibleTitleLen = mb_strwidth(Ansi::stripStyle($rawTitle));
            $padding = max(0, intdiv($tableWidth - $visibleTitleLen, 2));

            array_unshift($lines, str_repeat(' ', $padding).$rawTitle);
        }

        return $lines;
    }

    /**
     * @param non-empty-string $separator
     */
    public function render(string $separator = PHP_EOL): string
    {
        return implode($separator, $this->format());
    }

    /**
     * @param Closure(int, string): list<AnsiStyle> $cellColorFn
     *
     * @return list<string>
     */
    public function build(?AnsiStyle $headerColor = null, ?Closure $cellColorFn = null): array
    {
        $widths = $this->computeColumnWidths();
        $numericColumns = $this->detectNumericColumns();
        $lines = [
            $this->buildBorder($widths, 'top'),
            $this->buildHeaderRow($this->headers, $widths, $headerColor),
            $this->buildBorder($widths, 'header_sep'),
        ];

        foreach ($this->rows as $row) {
            $lines[] = $this->buildRow($row, $widths, $numericColumns, $cellColorFn);
        }

        $lines[] = $this->buildBorder($widths, 'bottom');

        return $lines;
    }

    /**
     * @return list<int>
     */
    private function computeColumnWidths(): array
    {
        $columns = count($this->headers);
        $widths = [];

        for ($i = 0; $i < $columns; $i++) {
            $max = mb_strwidth($this->headers[$i]);
            foreach ($this->rows as $row) {
                $max = max($max, mb_strwidth((string)($row[$i] ?? '')));
            }
            $widths[] = $max;
        }

        return $widths;
    }

    /**
     * @return array<true>
     */
    private function detectNumericColumns(): array
    {
        $numericColumns = [];
        foreach ($this->rows as $row) {
            foreach ($row as $i => $cell) {
                if (is_numeric($cell)) {
                    $numericColumns[$i] = true;
                }
            }
        }

        return $numericColumns;
    }

    /**
     * @param list<int> $widths
     */
    private function buildBorder(array $widths, string $type): string
    {
        $b = $this->borders[$type];
        $h = $this->borders['horizontal'] ?? '─'; // fallback if missing
        $parts = array_map(fn ($w) => str_repeat($h, $w + 2), $widths);

        return $b['left'].implode($b['mid'], $parts).$b['right'];
    }

    /**
     * @param list<string> $headers
     * @param list<int> $widths
     */
    private function buildHeaderRow(array $headers, array $widths, ?AnsiStyle $headerColor): string
    {
        $color = null !== $headerColor ? [$headerColor] : [];
        $vertical = $this->borders['vertical'] ?? '│';
        $cells = [];
        foreach ($headers as $i => $header) {
            $cells[] = ' '.self::padDisplayWidth($header, $widths[$i], STR_PAD_RIGHT, ...$color).' ';
        }
        return $vertical.implode($vertical, $cells).$vertical;
    }

    /**
     * @param array<string> $row
     * @param list<int> $widths
     * @param array<true> $numericColumns
     * @param Closure(int, string): list<AnsiStyle> $cellColorFn
     */
    private function buildRow(array $row, array $widths, array $numericColumns, ?Closure $cellColorFn): string
    {
        $vertical = $this->borders['vertical'] ?? '│';
        $cells = [];
        foreach ($row as $i => $cell) {
            $isNumeric = isset($numericColumns[$i]);
            $colors = null !== $cellColorFn ? $cellColorFn($i, $cell) : [];
            $cells[] = ' '.self::padDisplayWidth(
                (string)$cell,
                $widths[$i],
                $isNumeric ? STR_PAD_LEFT : STR_PAD_RIGHT,
                ...$colors
            ).' ';
        }

        return $vertical.implode($vertical, $cells).$vertical;
    }

    private static function padDisplayWidth(string $text, int $width, int $padType = STR_PAD_RIGHT, AnsiStyle ...$color): string
    {
        $len = mb_strwidth($text);
        $padLen = max(0, $width - $len);
        $text = match ($padType) {
            STR_PAD_RIGHT => $text.str_repeat(' ', $padLen),
            STR_PAD_LEFT => str_repeat(' ', $padLen).$text,
            STR_PAD_BOTH => str_repeat(' ', (int) floor($padLen / 2)).$text.str_repeat(' ', $padLen - (int) floor($padLen / 2)),
            default => $text,
        };

        if ([] === $color) {
            return $text;
        }

        return Ansi::write($text, ...$color);
    }

    /**
     * @param array<array{column:int, color:AnsiStyle, below?:int, above?:int}> $rules
     *
     * @return Closure(int, string): list<AnsiStyle>
     */
    private static function createCellColorFn(array $rules): Closure
    {
        /**
         * @var array<int, array<colorRule>> $grouped
         */
        $grouped = [];
        foreach ($rules as $rule) {
            $grouped[$rule['column']][] = $rule;
        }

        return function (int $col, string $value) use ($grouped): array {
            if (!isset($grouped[$col])) {
                return [];
            }

            $colors = [];
            $numericValue = is_numeric($value) ? $value + 0 : $value;
            /** @var colorRule $rule */
            foreach ($grouped[$col] as $rule) {
                if (isset($rule['below']) && $numericValue < $rule['below']) {
                    $colors[] = $rule['color'];
                }

                if (isset($rule['above']) && $numericValue > $rule['above']) {
                    $colors[] = $rule['color'];
                }

                if (isset($rule['equal']) && $numericValue === $rule['equal']) {
                    $colors[] = $rule['color'];
                }
            }

            return $colors;
        };
    }
}
