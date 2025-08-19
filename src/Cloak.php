<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Throwable;

use function in_array;
use function restore_error_handler;
use function set_error_handler;

use const E_USER_WARNING;
use const E_WARNING;

/**
 * Utility to cloak PHP errors (suppress certain error levels) during the execution of a callback.
 *
 * @template TReturn
 */
final class Cloak
{
    /**
     * Dynamically suppress PHP WARNING.
     *
     * @param mixed ...$arguments the callback arguments if needed
     *
     * @throws Throwable on callback execution if the callback throws
     *
     * @return TReturn The result returned by the callback.
     */
    public static function warning(callable $callback, mixed ...$arguments): mixed
    {
        set_error_handler(
            fn (int $errno, string $errstr, string $errfile, int $errline): bool =>
            in_array($errno, [E_WARNING, E_USER_WARNING], true)
        );

        try {
            return $callback(...$arguments);
        } finally {
            restore_error_handler();
        }
    }
}
