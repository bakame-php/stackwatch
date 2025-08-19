<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use ErrorException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function trigger_error;

use const E_USER_WARNING;

final class WarningTest extends TestCase
{
    public function testCallExecutesCallbackAndReturnsResult(): void
    {
        $result = Warning::trap(fn (int $a, int $b): int => $a + $b, 2, 3);

        self::assertSame(5, $result);
    }

    public function testWarningThrowsWarning(): void
    {
        $this->expectException(ErrorException::class);

        Warning::trap(fopen(...), '/no/resource.dot', 'r');
    }

    public function testWarningSuppressesWarning(): void
    {
        $result = Warning::cloak(fopen(...), '/no/resource.dot', 'r');

        self::assertFalse($result);
    }

    public function testThrowsOnUserWarningErrors(): void
    {
        $this->expectException(ErrorException::class);

        Warning::trap(function (): string {
            trigger_error('Warning', E_USER_WARNING);

            return 'done';
        });
    }

    public function testSilentlyFailsOnUserWarningErrors(): void
    {
        $result = Warning::cloak(function (): string {
            trigger_error('Warning', E_USER_WARNING);

            return 'done';
        });

        self::assertSame('done', $result);
    }

    public function testExceptionFromCallbackIsNotSwallowedByTrap(): void
    {
        $this->expectException(RuntimeException::class);

        Warning::trap(fn () => throw new RuntimeException('boom'));
    }

    public function testExceptionFromCallbackIsNotSwallowedByCloak(): void
    {
        $this->expectException(RuntimeException::class);

        Warning::cloak(fn () => throw new RuntimeException('boom'));
    }
}
