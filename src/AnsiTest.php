<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

#[CoversClass(Ansi::class)]
#[CoversClass(AnsiStyle::class)]
final class AnsiTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset static state before each test
        $ref = new ReflectionClass(Ansi::class);
        $prop = $ref->getProperty('enabled');
        $prop->setValue(null, null);

        // Clear environment variables we might manipulate
        putenv('NO_COLOR');
        putenv('FORCE_COLOR');
        putenv('TERM');
        putenv('ANSICON');
        putenv('ConEmuANSI');
    }

    public function testEnableAndDisable(): void
    {
        Ansi::disable();
        self::assertFalse(Ansi::enabled());

        Ansi::enable();
        self::assertTrue(Ansi::enabled());
    }

    public function testNoColorEnvironmentVariableDisables(): void
    {
        putenv('NO_COLOR=1');
        self::assertFalse(Ansi::enabled());
    }

    public function testForceColorEnvironmentVariableEnables(): void
    {
        putenv('FORCE_COLOR=1');
        self::assertTrue(Ansi::enabled());
    }

    public function testWriteWithoutAnsi(): void
    {
        Ansi::disable();
        self::assertSame('Hello', Ansi::write('Hello', AnsiStyle::Bold));
    }

    public function testWriteWithAnsi(): void
    {
        Ansi::enable();
        $expected = AnsiStyle::Bold->value.'Hello'.AnsiStyle::Reset->value;
        self::assertSame($expected, Ansi::write('Hello', AnsiStyle::Bold));
    }

    public function testWritelnAddsNewline(): void
    {
        Ansi::enable();
        $expected = AnsiStyle::Underline->value."Hello\n".AnsiStyle::Reset->value;
        self::assertSame($expected, Ansi::writeln('Hello', AnsiStyle::Underline));
    }

    public function testErrorMessageStyling(): void
    {
        Ansi::enable();
        $expected = AnsiStyle::Red->value.AnsiStyle::Bold->value.'Error'.AnsiStyle::Reset->value;
        self::assertSame($expected, Ansi::error('Error'));
    }

    public function testSuccessMessageStyling(): void
    {
        Ansi::enable();
        $expected = AnsiStyle::Green->value.AnsiStyle::Bold->value.'Ok'.AnsiStyle::Reset->value;
        self::assertSame($expected, Ansi::success('Ok'));
    }

    public function testWarningMessageStyling(): void
    {
        Ansi::enable();
        $expected = AnsiStyle::Yellow->value.AnsiStyle::Bold->value.'Warn'.AnsiStyle::Reset->value;
        self::assertSame($expected, Ansi::warning('Warn'));
    }

    public function testInfoMessageStyling(): void
    {
        Ansi::enable();
        $expected = AnsiStyle::Cyan->value.'Info'.AnsiStyle::Reset->value;
        self::assertSame($expected, Ansi::info('Info'));
    }

    public function testStripStyleRemovesAllAnsiCodes(): void
    {
        $styled = AnsiStyle::Red->value.AnsiStyle::Bold->value.'Hello'.AnsiStyle::Reset->value;
        self::assertSame('Hello', Ansi::stripStyle($styled));
    }

    public function testStripStyleWithoutAnsiIsUnchanged(): void
    {
        self::assertSame('PlainText', Ansi::stripStyle('PlainText'));
    }

    public function testDetectTerminalSupportOnUnix(): void
    {
        // Simulate TERM set
        putenv('TERM=xterm');
        self::assertTrue($this->invokePrivateMethod(Ansi::class, 'detectTerminalSupport'));

        // Simulate TERM=dumb
        putenv('TERM=dumb');
        self::assertFalse($this->invokePrivateMethod(Ansi::class, 'detectTerminalSupport'));

        // TERM unset
        putenv('TERM');
        self::assertFalse($this->invokePrivateMethod(Ansi::class, 'detectTerminalSupport'));
    }

    /**
     * @param class-string $class
     * @param array<mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivateMethod(string $class, string $method, array $args = []): mixed
    {
        $ref = new ReflectionClass($class);
        $m = $ref->getMethod($method);

        return $m->invokeArgs(null, $args);
    }
}
