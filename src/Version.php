<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Stringable;

final class Version implements Stringable
{
    private const NAME = 'stackwatch';
    private const VERSION_ID = '0.11.0';
    private const VERSION_NAME = 'Kampala';
    private const AUTHOR = 'Ignace Nyamagana Butera';

    public static function name(): string
    {
        return self::NAME;
    }

    public static function identifier(): string
    {
        return self::VERSION_ID;
    }

    public static function codename(): string
    {
        return self::VERSION_NAME;
    }

    public static function full(): string
    {
        return 'v'.self::VERSION_ID.' ('.self::VERSION_NAME.')';
    }

    public static function author(): string
    {
        return self::AUTHOR;
    }

    public static function toPlainString(): string
    {
        return self::NAME.' '.self::full().' by '.self::AUTHOR.' and contributors.';
    }

    public static function toConsoleString(): string
    {
        $text = self::toPlainString();
        $before = strstr($text, ' by', true);
        $after = strstr($text, 'by ');

        return '<fg=green>'.$before.'</> <fg=yellow>'.$after.'</>'."\n";
    }

    public function __toString(): string
    {
        return self::toPlainString();
    }
}
