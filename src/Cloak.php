<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use BadMethodCallException;
use Throwable;
use ValueError;

use function array_shift;
use function in_array;
use function is_callable;
use function restore_error_handler;
use function set_error_handler;
use function strtolower;

use const E_DEPRECATED;
use const E_NOTICE;
use const E_USER_DEPRECATED;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;

/**
 * Utility to cloak PHP errors (suppress certain error levels) during the execution of a callback.
 *
 * @template TReturn
 *
 * @method static TReturn call(callable $callback, mixed...$args) Suppresses Warnings and Notices
 * @method static TReturn warningAndNorice(callable $callback, mixed...$args) Suppresses Warnings and Notices
 * @method static TReturn all(callable $callback, mixed...$args) Suppresses All errors
 * @method static TReturn warning(callable  $callback, mixed...$args) Suppresses Warnings
 * @method static TReturn notice(callable  $callback, mixed...$args) Suppresses Notices
 */
final class Cloak
{
    /**
     * Dynamically suppress specific PHP errors based on static method called.
     *
     * @param string $name The static method name
     * @param array<mixed> $arguments Arguments: the first element must be callable, rest are its args
     *
     * @throws ValueError If the callback is missing or if the first argument is not callable
     * @throws BadMethodCallException If the method name could not be used.
     * @throws Throwable on callback execution if the callback throws
     *
     * @return mixed The result returned by the callback.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        [] !== $arguments || throw new ValueError('The callback is missing.');
        $callback = array_shift($arguments);
        is_callable($callback) || throw new ValueError('The callback is invalid.');

        $levels = match (strtolower($name)) {
            'call', 'warningandnotice' => [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE],
            'warning' => [E_WARNING, E_USER_WARNING],
            'notice' => [E_NOTICE, E_USER_NOTICE],
            'all' => [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED],
            default => throw new BadMethodCallException("Undefined Cloak method '$name'"),
        };

        set_error_handler(
            fn (int $errno, string $errstr, string $errfile, int $errline): bool =>
            in_array($errno, $levels, true)
        );

        try {
            return $callback(...$arguments);
        } finally {
            restore_error_handler();
        }
    }
}
