<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Stringable;

use function array_map;
use function function_exists;
use function getenv;
use function implode;
use function posix_isatty;
use function preg_replace;

use const DIRECTORY_SEPARATOR;
use const STDOUT;

final class Ansi
{
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (null !== self::$enabled) {
            return self::$enabled;
        }

        if (false !== getenv('NO_COLOR')) {
            return self::$enabled = false;
        }

        if (false !== getenv('FORCE_COLOR')) {
            return self::$enabled = true;
        }

        if (self::isOutputPiped()) {
            return self::$enabled = false;
        }

        return self::$enabled = self::detectTerminalSupport();
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Wrap text with one or multiple ANSI styles if supported.
     */
    public static function write(Stringable|string|float|int|null $text, AnsiStyle... $styles): string
    {
        if (!self::enabled()) {
            return (string) $text;
        }

        $prefix = implode('', array_map(fn (AnsiStyle $s) => $s->value, $styles));

        return $prefix.$text.AnsiStyle::Reset->value;
    }

    /**
     * Wrap text with one or multiple ANSI styles if supported and finish with a newline character.
     */
    public static function writeln(Stringable|string|float|int|null $text, AnsiStyle... $styles): string
    {
        return self::write($text."\n", ...$styles);
    }

    private static function isOutputPiped(): bool
    {
        if (function_exists('posix_isatty')) {
            return !posix_isatty(STDOUT);
        }

        return DIRECTORY_SEPARATOR === '\\' ? (false === getenv('TERM')) : false;
    }

    private static function detectTerminalSupport(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM')
                || (function_exists('sapi_windows_vt100_support') && Warning::cloak(sapi_windows_vt100_support(...), STDOUT)); /* @phpstan-ignore-line */
        }

        return false !== getenv('TERM')
            && 'dumb' !== getenv('TERM');
    }

    public static function error(Stringable|string|float|int|null $text): string
    {
        return self::write($text, AnsiStyle::Red, AnsiStyle::Bold);
    }

    public static function success(Stringable|string|float|int|null $text): string
    {
        return self::write($text, AnsiStyle::Green, AnsiStyle::Bold);
    }

    public static function warning(Stringable|string|float|int|null $text): string
    {
        return self::write($text, AnsiStyle::Yellow, AnsiStyle::Bold);
    }

    public static function info(Stringable|string|float|int|null $text): string
    {
        return self::write($text, AnsiStyle::Cyan);
    }

    public static function stripStyle(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
    }
}
