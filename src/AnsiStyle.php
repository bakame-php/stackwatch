<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Stringable;

use function array_keys;
use function array_map;
use function array_reduce;
use function implode;
use function preg_replace;
use function sort;

enum AnsiStyle: string
{
    // Text effects
    case Reset = "\033[0m";
    case Bold = "\033[1m";
    case Dim = "\033[2m";
    case Underline = "\033[4m";
    case Blink = "\033[5m";
    case Reverse = "\033[7m";
    // Foreground colors
    case Black = "\033[30m";
    case Red = "\033[31m";
    case Green = "\033[32m";
    case Yellow = "\033[33m";
    case Blue = "\033[34m";
    case Magenta = "\033[35m";
    case Cyan = "\033[36m";
    case White = "\033[37m";
    // Foreground (bright)
    case BrightBlack = "\033[90m";
    case BrightRed = "\033[91m";
    case BrightGreen = "\033[92m";
    case BrightYellow = "\033[93m";
    case BrightBlue = "\033[94m";
    case BrightMagenta = "\033[95m";
    case BrightCyan = "\033[96m";
    case BrightWhite = "\033[97m";
    // Background colors
    case BlackBg = "\033[40m";
    case RedBg = "\033[41m";
    case GreenBg = "\033[42m";
    case YellowBg = "\033[43m";
    case BlueBg = "\033[44m";
    case MagentaBg = "\033[45m";
    case CyanBg = "\033[46m";
    case WhiteBg = "\033[47m";
    // Background (bright)
    case BrightBlackBg = "\033[100m";
    case BrightRedBg = "\033[101m";
    case BrightGreenBg = "\033[102m";
    case BrightYellowBg = "\033[103m";
    case BrightBlueBg = "\033[104m";
    case BrightMagentaBg = "\033[105m";
    case BrightCyanBg = "\033[106m";
    case BrightWhiteBg = "\033[107m";

    public static function stylesheet(): string
    {
        $lines = array_map(fn (self $style): string => '.'.$style->toCssClass().' {'.$style->toCssRule().'}'.'.hover\\:'.$style->toCssClass().':hover {'.$style->toCssRule().'}', self::cases());
        sort($lines);

        return implode("\n", $lines);
    }

    public function toCssClass(): string
    {
        return match ($this) {
            // Effects
            self::Reset => 'bkm-sw-ansi-reset',
            self::Bold => 'bkm-sw-ansi-bold',
            self::Dim => 'bkm-sw-ansi-dim',
            self::Underline => 'bkm-sw-ansi-underline',
            self::Blink => 'bkm-sw-ansi-blink',
            self::Reverse => 'bkm-sw-ansi-reverse',

            // Normal foreground
            self::Black => 'bkm-sw-ansi-black',
            self::Red => 'bkm-sw-ansi-red',
            self::Green => 'bkm-sw-ansi-green',
            self::Yellow => 'bkm-sw-ansi-yellow',
            self::Blue => 'bkm-sw-ansi-blue',
            self::Magenta => 'bkm-sw-ansi-magenta',
            self::Cyan => 'bkm-sw-ansi-cyan',
            self::White => 'bkm-sw-ansi-white',

            // Bright foreground
            self::BrightBlack => 'bkm-sw-ansi-bright-black',
            self::BrightRed => 'bkm-sw-ansi-bright-red',
            self::BrightGreen => 'bkm-sw-ansi-bright-green',
            self::BrightYellow => 'bkm-sw-ansi-bright-yellow',
            self::BrightBlue => 'bkm-sw-ansi-bright-blue',
            self::BrightMagenta => 'bkm-sw-ansi-bright-magenta',
            self::BrightCyan => 'bkm-sw-ansi-bright-cyan',
            self::BrightWhite => 'bkm-sw-ansi-bright-white',

            // Normal background
            self::BlackBg => 'bkm-sw-ansi-black-bg',
            self::RedBg => 'bkm-sw-ansi-red-bg',
            self::GreenBg => 'bkm-sw-ansi-green-bg',
            self::YellowBg => 'bkm-sw-ansi-yellow-bg',
            self::BlueBg => 'bkm-sw-ansi-blue-bg',
            self::MagentaBg => 'bkm-sw-ansi-magenta-bg',
            self::CyanBg => 'bkm-sw-ansi-cyan-bg',
            self::WhiteBg => 'bkm-sw-ansi-white-bg',

            // Bright background
            self::BrightBlackBg => 'bkm-sw-ansi-bright-black-bg',
            self::BrightRedBg => 'bkm-sw-ansi-bright-red-bg',
            self::BrightGreenBg => 'bkm-sw-ansi-bright-green-bg',
            self::BrightYellowBg => 'bkm-sw-ansi-bright-yellow-bg',
            self::BrightBlueBg => 'bkm-sw-ansi-bright-blue-bg',
            self::BrightMagentaBg => 'bkm-sw-ansi-bright-magenta-bg',
            self::BrightCyanBg => 'bkm-sw-ansi-bright-cyan-bg',
            self::BrightWhiteBg => 'bkm-sw-ansi-bright-white-bg',
        };
    }

    public function toCssHoverClass(): string
    {
        return 'hover:'.$this->toCssClass();
    }

    /**
     * Return CSS rules as a string.
     */
    public function toCssRule(): string
    {
        return self::inlineRules($this);
    }

    /**
     * Wrap text with styles for terminals.
     */
    public static function wrap(Stringable|string|float|int|null $text, self ...$styles): string
    {
        return self::inline(...$styles).$text.self::Reset->value;
    }

    /**
     * Unwrap text from styles for terminals.
     */
    public static function unwrap(Stringable|string $text): string
    {
        $text = (string) $text;

        return preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
    }

    /**
     * Wrap text in an HTML span tag using CSS class or rule.
     *
     * @param 'class'|'style' $mode
     */
    public static function wrapHtml(string $mode, Stringable|string|float|int|null $text, self ...$styles): string
    {
        in_array($mode, ['class', 'style'], true) || throw new InvalidArgument('the HTML css mode can only be `class` or `style`');

        return match ($mode) {
            'class' => '<span class="'.self::inlineClasses(...$styles).'">'.$text.'</span>',
            'style' => '<span style="'.self::inlineRules(...$styles).'">'.$text.'</span>',
        };
    }

    /**
     * Merge multiple styles into a single console ansi pattern.
     */
    public static function inline(self ...$styles): string
    {
        return implode('', array_map(fn (self $style) => $style->value, $styles));
    }

    /**
     * Merge multiple styles into a single CSS string.
     */
    public static function inlineRules(self ...$styles): string
    {
        /** @var array<string, string> $rules */
        $rules = array_reduce($styles, fn (array $carry, self $style) => [...$carry, ...$style->cssRule()], []);

        return implode(';', array_map(fn (string $k, string $v): string => "$k:$v", array_keys($rules), $rules)).';';
    }

    /**
     * Merge multiple styles into a single CSS class string.
     */
    public static function inlineClasses(self ...$styles): string
    {
        return implode(' ', array_map(fn (self $style) => $style->toCssClass(), $styles));
    }

    /**
     * Merge multiple styles into a single CSS class string.
     */
    public static function inlineHoverClasses(self ...$styles): string
    {
        return implode(' ', array_map(fn (self $style) => $style->toCssHoverClass(), $styles));
    }

    /**
     * Return CSS rules as an associative array.
     *
     * @return array<string,string>
     */
    private function cssRule(): array
    {
        return match ($this) {
            // Effects
            self::Reset => ['all' => 'initial'],
            self::Bold => ['font-weight' => 'bold'],
            self::Dim => ['opacity' => '0.7'],
            self::Underline => ['text-decoration' => 'underline'],
            self::Blink => ['text-decoration' => 'blink'], // not widely supported
            self::Reverse => ['filter' => 'invert(100%)'],

            // Normal foreground
            self::Black => ['color' => 'black'],
            self::Red => ['color' => 'red'],
            self::Green => ['color' => 'green'],
            self::Yellow => ['color' => 'yellow'],
            self::Blue => ['color' => 'blue'],
            self::Magenta => ['color' => 'magenta'],
            self::Cyan => ['color' => 'cyan'],
            self::White => ['color' => 'white'],

            // Bright foreground
            self::BrightBlack   => ['color' => '#555555'],
            self::BrightRed     => ['color' => '#ff5555'],
            self::BrightGreen   => ['color' => '#55ff55'],
            self::BrightYellow  => ['color' => '#ffff55'],
            self::BrightBlue    => ['color' => '#5555ff'],
            self::BrightMagenta => ['color' => '#ff55ff'],
            self::BrightCyan    => ['color' => '#55ffff'],
            self::BrightWhite   => ['color' => '#ffffff'],

            // Normal background
            self::BlackBg => ['background-color' => 'black'],
            self::RedBg => ['background-color' => 'red'],
            self::GreenBg => ['background-color' => 'green'],
            self::YellowBg => ['background-color' => 'yellow'],
            self::BlueBg => ['background-color' => 'blue'],
            self::MagentaBg => ['background-color' => 'magenta'],
            self::CyanBg => ['background-color' => 'cyan'],
            self::WhiteBg => ['background-color' => 'white'],

            // Bright background
            self::BrightBlackBg   => ['background-color' => '#555555'],
            self::BrightRedBg     => ['background-color' => '#ff5555'],
            self::BrightGreenBg   => ['background-color' => '#55ff55'],
            self::BrightYellowBg  => ['background-color' => '#ffff55'],
            self::BrightBlueBg    => ['background-color' => '#5555ff'],
            self::BrightMagentaBg => ['background-color' => '#ff55ff'],
            self::BrightCyanBg    => ['background-color' => '#55ffff'],
            self::BrightWhiteBg   => ['background-color' => '#ffffff'],
        };
    }
}
