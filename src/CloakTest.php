<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CloakTest extends TestCase
{
    public function testCallExecutesCallbackAndReturnsResult(): void
    {
        $result = Cloak::warning(fn (int $a, int $b): int => $a + $b, 2, 3);
        self::assertSame(5, $result);
    }

    public function testWarningSuppressesWarning(): void
    {
        $result = Cloak::warning(fopen(...), '/no/resource.dot', 'r');

        self::assertFalse($result);
    }

    public function testAllSuppressesAllErrors(): void
    {
        $result = Cloak::warning(function (): string {
            trigger_error('Warning', E_USER_WARNING);

            return 'done';
        });
        self::assertSame('done', $result);
    }

    public function testExceptionFromCallbackIsNotSwallowed(): void
    {
        $this->expectException(RuntimeException::class);
        Cloak::warning(fn () => throw new RuntimeException('boom'));
    }
}
