<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\InvalidArgument;
use PHPUnit\Framework\TestCase;

final class AnsiStyleTest extends TestCase
{
    public function testToCssClass(): void
    {
        self::assertSame('bkm-sw-ansi-bold', AnsiStyle::Bold->toCssClass());
        self::assertSame('bkm-sw-ansi-red', AnsiStyle::Red->toCssClass());
        self::assertSame('bkm-sw-ansi-white-bg', AnsiStyle::WhiteBg->toCssClass());
    }

    public function testToCssRule(): void
    {
        self::assertSame('font-weight:bold;', AnsiStyle::Bold->toCssRule());
        self::assertSame('color:red;', AnsiStyle::Red->toCssRule());
        self::assertSame('background-color:white;', AnsiStyle::WhiteBg->toCssRule());
    }

    public function testInline(): void
    {
        self::assertSame("\033[1m\033[31m", AnsiStyle::inline(AnsiStyle::Bold, AnsiStyle::Red));
    }

    public function testInlineRules(): void
    {
        self::assertSame(
            'font-weight:bold;color:red;',
            AnsiStyle::inlineRules(AnsiStyle::Bold, AnsiStyle::Red)
        );
    }

    public function testInlineClasses(): void
    {
        self::assertSame(
            'bkm-sw-ansi-bold bkm-sw-ansi-red',
            AnsiStyle::inlineClasses(AnsiStyle::Bold, AnsiStyle::Red)
        );
    }

    public function testWrap(): void
    {
        self::assertSame(
            "\033[1m\033[31mHello\033[0m",
            AnsiStyle::wrap('Hello', AnsiStyle::Bold, AnsiStyle::Red)
        );
    }

    public function testUnwrap(): void
    {
        self::assertSame('Hello', AnsiStyle::unwrap("\033[1m\033[31mHello\033[0m"));
    }

    public function testWrapHtmlWithClass(): void
    {
        self::assertSame(
            '<span class="bkm-sw-ansi-bold bkm-sw-ansi-red">Hello</span>',
            AnsiStyle::wrapHtml('class', 'Hello', AnsiStyle::Bold, AnsiStyle::Red)
        );
    }

    public function testWrapHtmlWithStyle(): void
    {
        self::assertSame(
            '<span style="font-weight:bold;color:red;">Hello</span>',
            AnsiStyle::wrapHtml('style', 'Hello', AnsiStyle::Bold, AnsiStyle::Red)
        );
    }

    public function testWrapHtmlWithInvalidModeThrows(): void
    {
        $this->expectException(InvalidArgument::class);

        AnsiStyle::wrapHtml('invalid', 'Hello', AnsiStyle::Bold); /* @phpstan-ignore-line */
    }

    public function testStylesheetContainsCssForAllCases(): void
    {
        $css = AnsiStyle::stylesheet();

        foreach (AnsiStyle::cases() as $style) {
            self::assertStringContainsString($style->toCssClass(), $css);
            self::assertStringContainsString($style->toCssRule(), $css);
        }
    }
}
