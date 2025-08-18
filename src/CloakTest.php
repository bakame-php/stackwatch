<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ValueError;

final class CloakTest extends TestCase
{
    public function testCallExecutesCallbackAndReturnsResult(): void
    {
        $result = Cloak::call(fn (int $a, int $b): int => $a + $b, 2, 3);
        self::assertSame(5, $result);
    }

    public function testWarningSuppressesWarning(): void
    {
        $result = Cloak::warning(fopen(...), '/no/resource.dot', 'r');

        self::assertFalse($result);
    }

    public function testNoticeSuppressesNotice(): void
    {
        $result = Cloak::notice(function (): bool {
            // undefined variable => notice
            /* @phpstan-ignore-next-line */
            return isset($undefinedVar);
        });

        self::assertFalse($result);
    }

    public function testDeprecatedSuppressesDeprecated(): void
    {
        $result = Cloak::deprecated(function (): string {
            @trigger_error('Deprecated feature', E_USER_DEPRECATED);
            return 'ok';
        });

        self::assertSame('ok', $result);
    }

    public function testAllSuppressesAllErrors(): void
    {
        $result = Cloak::all(function (): string {
            @trigger_error('Notice', E_USER_NOTICE);
            @trigger_error('Warning', E_USER_WARNING);
            @trigger_error('Deprecated', E_USER_DEPRECATED);

            return 'done';
        });
        self::assertSame('done', $result);
    }

    public function testExceptionFromCallbackIsNotSwallowed(): void
    {
        $this->expectException(RuntimeException::class);
        Cloak::call(fn () => throw new RuntimeException('boom'));
    }

    public function testThrowsOnMissingCallback(): void
    {
        $this->expectException(ValueError::class);
        Cloak::__callStatic('call', []);
    }

    public function testThrowsOnInvalidCallback(): void
    {
        $this->expectException(ValueError::class);
        Cloak::__callStatic('call', ['not_a_callable']);
    }

    public function testThrowsOnUnknownMethod(): void
    {
        $this->expectException(BadMethodCallException::class);
        Cloak::__callStatic('foo', [fn () => null]);
    }
}
