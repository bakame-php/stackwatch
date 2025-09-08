<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function explode;

#[CoversClass(Table::class)]
final class TableTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure ANSI is always enabled for predictable output
        Ansi::enable();
    }

    public function testClassicTableWithHeaderAndRow(): void
    {
        $table = Table::classic()
            ->setHeader(['Name', 'Age'])
            ->addRow(['Alice', 30])
            ->addRow(['Bob', 25]);

        $lines = explode("\n", $table->renderCli());

        self::assertIsArray($lines);
        self::assertNotEmpty($lines);

        // Check top border
        self::assertStringStartsWith('┌', $lines[0]);
        // Check header row contains column names
        self::assertStringContainsString('Name', $lines[1]);
        self::assertStringContainsString('Age', $lines[1]);
        // Check bottom border
        self::assertStringStartsWith('└', end($lines));
    }

    public function testDoubleLineBorders(): void
    {
        $table = Table::doubleLine()
            ->setHeader(['Item', 'Price'])
            ->addRow(['Book', 12]);

        $lines = explode("\n", $table->renderCli());

        self::assertStringStartsWith('╔', $lines[0]);
        self::assertStringStartsWith('╚', (string) end($lines));
    }

    public function testDashedBorders(): void
    {
        $table = Table::dashed()
            ->setHeader(['Col1', 'Col2'])
            ->addRow(['A', 'B']);

        $lines = explode("\n", $table->renderCli());

        self::assertStringStartsWith('+', $lines[0]);
        self::assertStringStartsWith('+', (string) end($lines));
        self::assertStringContainsString('|', $lines[1]);
    }

    public function testTitleIsCentered(): void
    {
        $table = Table::classic()
            ->setHeader(['A', 'B'])
            ->addRow(['x', 'y'])
            ->setTitle('My Table');

        $lines = explode("\n", $table->renderCli());

        self::assertStringContainsString('My Table', $lines[0]);
    }

    public function testNumericColumnsAreRightAligned(): void
    {
        $table = Table::classic()
            ->setHeader(['Name', 'Score'])
            ->addRow(['Alice', 100])
            ->addRow(['Bob', 5]);

        $lines = explode("\n", $table->renderCli());

        // Row lines should have numbers aligned to the right
        $row = $lines[3]; // after top, header, header_sep
        self::assertMatchesRegularExpression('/Alice.*100/', $row);
        self::assertMatchesRegularExpression('/Bob.*  5/', $lines[4]);
    }

    public function testColorRulesApply(): void
    {
        $rules = [
            ['column' => 1, 'style' => [AnsiStyle::Red], 'below' => 50],
            ['column' => 1, 'style' => [AnsiStyle::Green], 'above' => 50],
            ['column' => 1, 'style' => [AnsiStyle::Blue], 'equal' => 50],
            ['column' => 0, 'style' => [AnsiStyle::Cyan]],
        ];

        $table = Table::dashed()
            ->setHeader(['Name', 'Score'])
            ->setHeaderStyle(AnsiStyle::Magenta, AnsiStyle::Bold)
            ->setRowStyle($rules)
            ->addRow(['Alice', 30])                                 // Alice should be Cyan, 30 should be red
            ->addRow([
                ['value' => 'Bob', 'style' => [AnsiStyle::Yellow]], // 'Bob' should be Yellow
                80,                                                  // should be green
            ])
            ->addRow(['Dave', 50]);

        $lines = explode("\n", $table->renderCli());

        self::assertStringContainsString(AnsiStyle::Red->value, $lines[3]);
        self::assertStringContainsString(AnsiStyle::Green->value, $lines[4]);
    }
}
