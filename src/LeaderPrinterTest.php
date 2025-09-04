<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LeaderPrinter::class)]
#[CoversClass(AnsiStyle::class)]
#[CoversClass(Ansi::class)]
final class LeaderPrinterTest extends TestCase
{
    public function testAddPairAndRender(): void
    {
        $printer = new LeaderPrinter();
        $printer->addPair('Name', 'Alice')->addPair('Age', 30);

        $output = $printer->render();
        $lines = explode("\n", $output);

        self::assertCount(2, $lines);
        self::assertStringContainsString('Name', $lines[0]);
        self::assertStringContainsString('Alice', $lines[0]);
        self::assertStringContainsString('Age', $lines[1]);
        self::assertStringContainsString('30', $lines[1]);
    }

    public function testSetPairs(): void
    {
        $printer = new LeaderPrinter();
        $printer->setPairs(['A' => '1', 'B' => '2']);
        $output = $printer->render();

        self::assertStringContainsString('A', $output);
        self::assertStringContainsString('1', $output);
        self::assertStringContainsString('B', $output);
        self::assertStringContainsString('2', $output);
    }

    public function testSetStyles(): void
    {
        $printer = new LeaderPrinter();
        $printer
            ->setStylesKey(AnsiStyle::Bold)
            ->setStylesValue(AnsiStyle::Underline)
            ->addPair('Key', 'Value');

        $output = $printer->render();
        self::assertStringContainsString('Key', $output);
        self::assertStringContainsString('Value', $output);
        if (Ansi::enabled()) {
            self::assertStringContainsString(AnsiStyle::Bold->value, $output);
            self::assertStringContainsString(AnsiStyle::Underline->value, $output);
        }
    }

    public function testRenderHtml(): void
    {
        $printer = new LeaderPrinter();
        $printer
            ->setStylesKey(AnsiStyle::Bold)
            ->setStylesValue(AnsiStyle::Underline)
            ->addPair('Key', 'Value');

        $html = $printer->renderHtml();

        self::assertStringContainsString('<ul class="bkm-sw-dotted-list', $html);
        self::assertStringContainsString('<li class="bkm-sw-dotted-item">', $html);
        self::assertStringContainsString('bkm-sw-ansi-bold', $html);
        self::assertStringContainsString('bkm-sw-ansi-underline', $html);
        self::assertStringContainsString('Key', $html);
        self::assertStringContainsString('Value', $html);
        self::assertStringContainsString('</ul>', $html);
    }

    public function testStylesheet(): void
    {
        $css = LeaderPrinter::stylesheet();

        self::assertStringContainsString('.bkm-sw-dotted-list', $css);
        self::assertStringContainsString('.bkm-sw-dotted-item', $css);
        self::assertStringContainsString('.bkm-sw-dots', $css);
    }
}
