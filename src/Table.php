<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Closure;
use Generator;
use Stringable;

use function array_map;
use function array_merge;
use function array_slice;
use function array_sum;
use function array_unshift;
use function array_values;
use function count;
use function floor;
use function implode;
use function intdiv;
use function is_array;
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
 * @phpstan-type cell array{value: string, colspan?: int, align?: 'left'|'right'|'center', style?: list<AnsiStyle>}
 * @phpstan-type cellStyle array{column:int, style?:list<AnsiStyle>, below?:int, above?:int, equal?:int, align?: 'left'|'right'|'center'}
 * @phpstan-type rowStyle List<cellStyle>
 */
final class Table
{
    private ?string $title = null;
    /** @var list<AnsiStyle> ANSI code for the table title */
    private array $titleStyle = [];
    /** @var list<string>*/
    private array $headers = [];
    /** @var list<AnsiStyle> ANSI code for the full header */
    private array $headerStyle = [];
    /** @var list<list<cell>> */
    private array $rows = [];
    /** @var rowStyle */
    private array $rowStyle = [];
    /** @var array<int, true> */
    private array $rowSeparators = [];

    public static function stylesheet(): string
    {
        return <<<'STYLE'
.bkm-sw-table {margin: 0 auto;border-collapse: collapse;width: 100%; font-size:12px}
.bkm-sw-table td,
.bkm-sw-table th {padding: .7em;border: 1px solid #5c636a;text-align: right; color: yellow;}
.bkm-sw-table tbody tr {background-color: #111;}
.bkm-sw-table tbody tr:nth-child(odd) {background-color: #222;}
.bkm-sw-table tbody tr:hover {background-color: #333;}
.bkm-sw-table tbody td {transition:border .2s}
.bkm-sw-table tbody td:hover {border-left: 2px solid #ffa;border-right: 1px solid #ffa;}
STYLE;
    }

    public function addRowSeparator(): self
    {
        $this->rowSeparators[count($this->rows)] = true;

        return $this;
    }

    /**
     * @param borderConfig $borders
     */
    private function __construct(private array $borders)
    {
    }

    public static function classic(): self
    {
        return new self([
            'top' => ['left' => '┌', 'mid' => '┬', 'right' => '┐'],
            'header_sep' => ['left' => '├', 'mid' => '┼', 'right' => '┤'],
            'bottom' => ['left' => '└', 'mid' => '┴', 'right' => '┘'],
        ]);
    }

    public static function doubleLine(): self
    {
        return new self([
            'top' => ['left' => '╔', 'mid' => '╦', 'right' => '╗'],
            'header_sep' => ['left' => '╠', 'mid' => '╬', 'right' => '╣'],
            'bottom' => ['left' => '╚', 'mid' => '╩', 'right' => '╝'],
        ]);
    }

    public static function dashed(): self
    {
        return new self([
            'top' => ['left' => '+', 'mid' => '+', 'right' => '+'],
            'header_sep' => ['left' => '+', 'mid' => '+', 'right' => '+'],
            'bottom' => ['left' => '+', 'mid' => '+', 'right' => '+'],
            'horizontal' => '-',
            'vertical' => '|',
        ]);
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setTitleStyle(AnsiStyle ...$titleStyle): self
    {
        $this->titleStyle = array_values($titleStyle);

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

    public function setHeaderStyle(AnsiStyle ...$color): self
    {
        $this->headerStyle = array_values($color);

        return $this;
    }

    /**
     * @param iterable<array<string|Stringable|float|int|null|cell>> $rows
     */
    public function setRows(iterable $rows): self
    {
        foreach ($rows as $row) {
            $this->addRow($row);
        }

        return $this;
    }

    /**
     * Add a row of cells. Supports colspan with array syntax:
     * ['value' => 'My text', 'colspan' => 2]
     *
     * @param array<string|Stringable|float|int|null|cell> $row
     */
    public function addRow(array $row): self
    {
        $normalized = [];
        foreach ($row as $cell) {
            if (!is_array($cell)) {
                $normalized[] = ['value' => (string) $cell, 'colspan' => 1];
                continue;
            }

            if (isset($cell['value'])) {
                $data = ['value' => (string) $cell['value'], 'colspan' => $cell['colspan'] ?? 1];
                if (isset($cell['align'])) {
                    $data['align'] = $cell['align'];
                }

                if (isset($cell['style'])) {
                    $data['style'] = $cell['style'];
                }
                $normalized[] = $data;
            }
        }
        $this->rows[] = $normalized;

        return $this;
    }

    /**
     * @param rowStyle $rowStyles
     */
    public function setRowStyle(array $rowStyles): self
    {
        $this->rowStyle = $rowStyles;

        return $this;
    }

    public function renderHtml(): string
    {
        $html = ['<table class="bkm-sw-table">'];
        if (null !== $this->title) {
            $html[] = '<caption style="caption-side:top; text-align:center; padding:1em;'.AnsiStyle::inlineRules(...$this->titleStyle).'">'.$this->title.'</caption>';
        }

        if ([] !== $this->headers) {
            $html[] = '<thead>';
            $html[] = '<tr>';
            foreach ($this->headers as $header) {
                $html[] = '<th style="text-align:center;'.AnsiStyle::inlineRules(...$this->headerStyle).'">'.$header.'</th>';
            }
            $html[] = '</tr>';
            $html[] = '</thead>';
        }

        if ([] !== $this->rows) {
            $cellStyleFn = self::createCellStyleFn($this->rowStyle);
            foreach ($this->buildTbody($cellStyleFn) as $row) {
                $html [] = $row;
            }
        }
        $html [] = '</table>';

        return implode(PHP_EOL, $html);
    }

    /**
     * @param ?Closure(int, string): array{style: list<AnsiStyle>, align: 'left'|'center'|'right'|null} $cellStyleFn
     *
     * @return Generator<string>
     */
    private function buildTbody(?Closure $cellStyleFn): Generator
    {
        yield '<tbody>';
        foreach ($this->rows as $row) {
            yield $this->buildTr($row, $cellStyleFn);
        }
        yield '</tbody>';
    }

    /**
     * @param array<cell> $row
     * @param ?Closure(int, string): array{style: list<AnsiStyle>, align: 'left'|'center'|'right'|null} $cellStyleFn
     */
    private function buildTr(array $row, ?Closure $cellStyleFn): string
    {
        $html = ['<tr>'];
        $colIndex = 0;
        foreach ($row as $cell) {
            $colspan = $cell['colspan'] ?? 1;
            $value = $cell['value'];
            $result = null !== $cellStyleFn ? $cellStyleFn($colIndex, $value) : ['style' => [], 'align' => null];
            $align = $cell['align'] ?? $result['align'] ?? 'right';
            $styles = $cell['style'] ?? $result['style'];
            $html[] = '<td style="'.AnsiStyle::inlineRules(...$styles).';text-align:'.$align.'" colspan="'.$colspan.'">'.$value.'</td>';
            $colIndex += $colspan;
        }

        $html[] = '</tr>';

        return implode('', $html);
    }

    public function render(): string
    {
        $cellStyleFn = self::createCellStyleFn($this->rowStyle);
        $lines = [];
        foreach ($this->build($cellStyleFn) as $line) {
            $lines[] = $line;
        }

        if (null !== $this->title) {
            /** @var non-empty-list<int<0, max>> $visibleWidths */
            $visibleWidths = array_map(
                fn (string $line): int => mb_strwidth(AnsiStyle::unwrap($line)),
                $lines
            );
            $tableWidth = max($visibleWidths);
            $rawTitle = $this->title;
            if ([] !== $this->titleStyle) {
                $rawTitle = Ansi::write($rawTitle, ...$this->titleStyle);
            }

            $visibleTitleLen = mb_strwidth(AnsiStyle::unwrap($rawTitle));
            $padding = max(0, intdiv($tableWidth - $visibleTitleLen, 2));

            array_unshift($lines, str_repeat(' ', $padding).$rawTitle);
        }

        return implode("\n", $lines);
    }

    /**
     * @param ?Closure(int, string): array{style: list<AnsiStyle>, align: 'left'|'center'|'right'|null} $cellStyleFn
     *
     * @return list<string>
     */
    private function build(?Closure $cellStyleFn): array
    {
        $widths = $this->computeColumnWidths();
        $numericColumns = $this->detectNumericColumns();
        $lines = [
            $this->buildBorder($widths, 'top'),
            $this->buildHeaderRow($widths),
            $this->buildBorder($widths, 'header_sep'),
        ];

        foreach ($this->rows as $i => $row) {
            if (isset($this->rowSeparators[$i])) {
                $lines[] = $this->buildBorder($widths, 'header_sep');
            }
            $lines[] = $this->buildRow($row, $widths, $numericColumns, $cellStyleFn);
        }

        $lines[] = $this->buildBorder($widths, 'bottom');

        return $lines;
    }

    /**
     * @return array<int, int>
     */
    private function computeColumnWidths(): array
    {
        $columns = count($this->headers);
        $widths  = [];

        // Initialize widths with header lengths
        for ($i = 0; $i < $columns; $i++) {
            $widths[$i] = mb_strwidth($this->headers[$i]);
        }

        // Check each row
        foreach ($this->rows as $row) {
            $colIndex = 0;
            foreach ($row as $cell) {
                $colspan = $cell['colspan'];
                $len = mb_strwidth($cell['value']);

                if (1 === $colspan) {
                    $widths[$colIndex] = max($widths[$colIndex], $len);
                    $colIndex += $colspan;
                    continue;
                }

                $currentSpanWidth = array_sum(array_slice($widths, $colIndex, $colspan));
                if ($len > $currentSpanWidth) {
                    $extra = $len - $currentSpanWidth;
                    $perCol = intdiv($extra, $colspan);
                    $remainder = $extra % $colspan;

                    for ($k = 0; $k < $colspan; $k++) {
                        $widths[$colIndex + $k] += $perCol + ($k < $remainder ? 1 : 0);
                    }
                }
                $colIndex += $colspan;
            }
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
            $colIndex = 0;
            foreach ($row as $cell) {
                if (is_numeric($cell['value'])) {
                    $numericColumns[$colIndex] = true;
                }
                $colIndex += $cell['colspan'] ?? 1;
            }
        }
        return $numericColumns;
    }

    /**
     * @param array<int, int> $widths
     */
    private function buildBorder(array $widths, string $type): string
    {
        $b = $this->borders[$type];
        $h = $this->borders['horizontal'] ?? '─';
        $parts = array_map(fn (int $w): string => str_repeat($h, $w + 2), $widths);

        return $b['left'].implode($b['mid'], $parts).$b['right'];
    }

    /**
     * @param array<int, int> $widths
     */
    private function buildHeaderRow(array $widths): string
    {
        $vertical = $this->borders['vertical'] ?? '│';
        $cells = [];
        foreach ($this->headers as $i => $header) {
            $cells[] = ' '.self::padDisplayWidth($header, $widths[$i], STR_PAD_RIGHT, ...$this->headerStyle).' ';
        }
        return $vertical.implode($vertical, $cells).$vertical;
    }

    /**
     * @param array<cell> $row
     * @param array<int, int> $widths
     * @param array<true> $numericColumns
     * @param ?Closure(int, string): array{style: list<AnsiStyle>, align: 'left'|'center'|'right'|null} $cellStyleFn
     */
    private function buildRow(array $row, array $widths, array $numericColumns, ?Closure $cellStyleFn): string
    {
        $vertical = $this->borders['vertical'] ?? '│';
        $cells = [];
        $colIndex = 0;

        foreach ($row as $cell) {
            $colspan = $cell['colspan'];
            $value = $cell['value'];

            // Base content width (sum of all spanned columns)
            $spanWidths = array_slice($widths, $colIndex, $colspan);
            $contentWidth = array_sum($spanWidths);

            // Each column normally has 2 spaces padding. Keep that for every column spanned.
            $paddingWidth = $colspan * 2;

            // Between merged columns, we lose (colspan - 1) vertical separators.
            // Each missing separator would normally add 1 char.
            $lostSeparators = $colspan - 1;

            // Final total width available for text
            $totalWidth = $contentWidth + $paddingWidth + $lostSeparators;

            $isNumeric = (1 === $colspan && isset($numericColumns[$colIndex]));
            $result = null !== $cellStyleFn ? $cellStyleFn($colIndex, $value) : ['style' => [], 'align' => null];
            $align = $cell['align'] ?? $result['align'] ?? ($isNumeric ? 'right' : 'left');
            $styles = $cell['style'] ?? $result['style'];

            $padType = match ($align) {
                'left' => STR_PAD_RIGHT,
                'right' => STR_PAD_LEFT,
                'center' => STR_PAD_BOTH,
                default => STR_PAD_RIGHT,
            };

            $cells[] = self::padDisplayWidth(
                $value,
                $totalWidth,
                $padType,
                ...$styles
            );

            $colIndex += $colspan;
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
     * @param array<cellStyle> $rules
     *
     * @return Closure(int, string): array{style: list<AnsiStyle>, align: 'left'|'center'|'right'|null}
     */
    private static function createCellStyleFn(array $rules): Closure
    {
        /** @var array<int, array<cellStyle>> $grouped */
        $grouped = [];
        foreach ($rules as $rule) {
            $grouped[$rule['column']][] = $rule;
        }

        return function (int $col, string $value) use ($grouped): array {
            $style = [];
            $align = null;
            if (!isset($grouped[$col])) {
                return ['style' => $style, 'align' => $align];
            }

            $numericValue = is_numeric($value) ? $value + 0 : null;

            /** @var cellStyle $rule */
            foreach ($grouped[$col] as $rule) {
                $rule['style'] = $rule['style'] ?? [];

                $hasBelow = array_key_exists('below', $rule);
                $hasAbove = array_key_exists('above', $rule);
                $hasEqual = array_key_exists('equal', $rule);
                $hasAnyCondition = $hasBelow || $hasAbove || $hasEqual;

                $matches = !$hasAnyCondition;

                if (!$matches) {
                    $matches = true;
                    if ($hasBelow) {
                        $matches = (null !== $numericValue && $numericValue < $rule['below']);
                    }

                    if ($hasAbove) {
                        $matches = (null !== $numericValue && $numericValue > $rule['above']);
                    }

                    if ($hasEqual) {
                        $expected = $rule['equal'];
                        if (null !== $numericValue && is_numeric($expected)) {
                            $matches = $numericValue === $expected;
                        } else {
                            $matches = $value === (string) $expected;
                        }
                    }
                }

                if ($matches) {
                    $style = array_merge($style, $rule['style']);
                    $align = $rule['align'] ?? $align;
                }
            }

            return ['style' => $style, 'align' => $align];
        };
    }
}
