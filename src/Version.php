<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

final class Version
{
    private const VERSION = '0.11.0';
    private const NAME = 'Kampala';

    public static function identifier(): string
    {
        return self::VERSION;
    }

    public static function name(): string
    {
        return self::NAME;
    }

    public static function full(): string
    {
        return 'v'.self::VERSION.' ('.self::NAME.')';
    }
}
