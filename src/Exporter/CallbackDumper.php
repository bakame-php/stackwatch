<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\Environment;

final class CallbackDumper
{
    private const EXIT_CODE = 1;

    public static function dd(callable $callback): never
    {
        ob_start();
        $callback();
        $content = ob_get_clean();

        if (Environment::current()->isCli()) {
            echo $content;
            exit(self::EXIT_CODE);
        }

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $content;
        exit(self::EXIT_CODE);
    }
}
