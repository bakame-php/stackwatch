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
        return strtr(self::template(), [
            '{name}' => self::NAME,
            '{version}' => self::VERSION_ID,
            '{codename}' => self::VERSION_NAME,
            '{author}' => self::AUTHOR,
        ]);
    }

    public static function toConsoleString(): string
    {
        return '<fg=green>'.strtr(self::template(), [
            '{name}' => self::NAME,
            '{version}' => self::VERSION_ID,
            '{codename}' => self::VERSION_NAME.'</>',
            '{author}' => '<fg=yellow>'.self::AUTHOR,
        ]).'</>'."\n";
    }

    private static function template(): string
    {
        return '{name} v{version} ({codename}) by {author} and contributors.';
    }

    public function __toString(): string
    {
        return self::toPlainString();
    }
}
