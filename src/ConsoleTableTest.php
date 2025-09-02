<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleTable::class)]
final class ConsoleTableTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure ANSI is always enabled for predictable output
        Ansi::enable();
    }

    public function testClassicTableWithHeaderAndRow(): void
    {
        $table = ConsoleTable::classic()
            ->setHeader(['Name', 'Age'])
            ->addRow(['Alice', 30])
            ->addRow(['Bob', 25]);

        $lines = $table->format();

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
        $table = ConsoleTable::doubleLine()
            ->setHeader(['Item', 'Price'])
            ->addRow(['Book', 12]);

        $lines = $table->format();

        self::assertStringStartsWith('╔', (string) $lines[0]);
        self::assertStringStartsWith('╚', (string) end($lines));
    }

    public function testDashedBorders(): void
    {
        $table = ConsoleTable::dashed()
            ->setHeader(['Col1', 'Col2'])
            ->addRow(['A', 'B']);

        $lines = $table->format();

        self::assertStringStartsWith('+', $lines[0]);
        self::assertStringStartsWith('+', (string) end($lines));
        self::assertStringContainsString('|', $lines[1]);
    }

    public function testRenderWithCustomSeparator(): void
    {
        $table = ConsoleTable::classic()
            ->setHeader(['Key', 'Value'])
            ->addRow(['One', 1])
            ->addRow(['Two', 2]);

        $output = $table->render(';');

        self::assertIsString($output);
        self::assertStringContainsString(';', $output);
    }

    public function testTitleIsCentered(): void
    {
        $table = ConsoleTable::classic()
            ->setHeader(['A', 'B'])
            ->addRow(['x', 'y'])
            ->setTitle('My Table');

        $lines = $table->format();

        // First line should be the title
        self::assertStringContainsString('My Table', $lines[0]);
    }

    public function testNumericColumnsAreRightAligned(): void
    {
        $table = ConsoleTable::classic()
            ->setHeader(['Name', 'Score'])
            ->addRow(['Alice', 100])
            ->addRow(['Bob', 5]);

        $lines = $table->format();

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

        $table = ConsoleTable::dashed()
            ->setHeader(['Name', 'Score'])
            ->setHeaderStyle(AnsiStyle::Magenta, AnsiStyle::Bold)
            ->setRowStyle($rules)
            ->addRow(['Alice', 30])                                 // Alice should be Cyan, 30 should be red
            ->addRow([
                ['value' => 'Bob', 'style' => [AnsiStyle::Yellow]], // 'Bob' should be Yellow
                80,                                                  // should be green
            ])
            ->addRow(['Dave', 50]);

        $lines = $table->format();

        self::assertStringContainsString(AnsiStyle::Red->value, $lines[3]);
        self::assertStringContainsString(AnsiStyle::Green->value, $lines[4]);
    }
}
