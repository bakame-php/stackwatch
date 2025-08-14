<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

#[CoversClass(Input::class)]
#[CoversClass(State::class)]
#[CoversClass(Visibility::class)]
final class InputTest extends TestCase
{
    /**
     * @param array<string, int|string|false> $options
     */
    private function mockInputInterface(array $options): InputInterface
    {
        $mock = $this->createMock(InputInterface::class);
        $mock->method('getOption')->willReturnCallback(
            fn (string $name) => $options[$name] ?? null
        );

        return $mock;
    }

    public function testFromInputWithMinimumRequiredOptions(): void
    {
        $input = [
            'path' => '/tmp',
        ];

        $obj = Input::fromInput($input);

        self::assertSame('/tmp', $obj->path);
        self::assertSame(Input::TABLE_FORMAT, $obj->format);
        self::assertFalse($obj->helpSection->isVisible());
        self::assertSame(-1, $obj->depth);
    }

    public function testFromInputInterface(): void
    {
        $mockInput = $this->mockInputInterface([
            'path' => '/tmp',
            'format' => 'table',
            'depth' => '1',
        ]);

        $obj = Input::fromInput($mockInput);

        self::assertSame('/tmp', $obj->path);
        self::assertSame(Input::TABLE_FORMAT, $obj->format);
        self::assertSame(1, $obj->depth);
    }

    public function testFromInputWithJsonFormatAndPretty(): void
    {
        $input = [
            'path' => '/tmp',
            'format' => 'json',
            'pretty' => false, // flag is present, so hasFlag should return true
        ];

        $obj = Input::fromInput($input);

        self::assertSame(Input::JSON_FORMAT, $obj->format);
        self::assertTrue($obj->jsonPrettyPrint->isEnabled());
    }

    public function testInvalidFormatThrowsException(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Output format is not supported');

        new Input(path: '/tmp', format: 'xml');
    }

    public function testBlankPathThrowsException(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('path format is not valid');

        new Input('');
    }

    public function testMissingRequiredOptionThrowsException(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Missing required option');

        new Input(null);
    }

    public function testDepthDefaultsToUnlimited(): void
    {
        $input = [
            'path' => '/tmp',
        ];

        $obj = Input::fromInput($input);

        self::assertSame(-1, $obj->depth);
    }

    public function testDepthOptionIsRespected(): void
    {
        $input = [
            'path' => '/tmp',
            'depth' => '3',
        ];

        $obj = Input::fromInput($input);

        self::assertSame(3, $obj->depth);
    }

    public function testNoRecursionReturnsDepthZero(): void
    {
        $input = [
            'path' => '/tmp',
            'no-recursion' => false,
        ];

        $obj = Input::fromInput($input);

        self::assertSame(0, $obj->depth);
    }

    public function testNoRecursionAndDepthConflictThrows(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('--no-recursion and --depth cannot be used together.');

        $input = [
            'path' => '/tmp',
            'no-recursion' => false,
            'depth' => '2',
        ];

        Input::fromInput($input);
    }

    public function testNormalizeFormatTrimsAndLowercases(): void
    {
        $input = [
            'path' => '/tmp',
            'no-recursion' => false,
            'format' => ' JsOn ',
        ];

        self::assertSame('json', Input::fromInput($input)->format);
    }

    public function testUsageAndConsoleDescriptionContainExpectedStrings(): void
    {
        self::assertStringContainsString('--path=PATH', Input::usage());
        self::assertStringContainsString('--path=PATH', Input::consoleDescription());
    }
}
