<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param list<non-empty-string> $expected
     */
    #[DataProvider('provideValidArguments')]
    public function testToArguments(Input $input, array $expected): void
    {
        self::assertSame($expected, $input->toArguments());
    }

    /**
     * @return iterable<string, array{0: Input, 1: list<non-empty-string>}>
     */
    public static function provideValidArguments(): iterable
    {
        yield 'path only, table format (default)' => [
            new Input(path: __DIR__.'/src'),
            [
                '--path', __DIR__.'/src',
                '--format', Input::TABLE_FORMAT,
            ],
        ];

        yield 'json format with pretty print and depth' => [
            new Input(
                path: __DIR__.'/src',
                format: Input::JSON_FORMAT,
                jsonPrettyPrint: State::Enabled,
                depth: 2,
            ),
            [
                '--path', __DIR__.'/src',
                '--format', Input::JSON_FORMAT,
                '--pretty',
                '--depth', '2',
            ],
        ];

        yield 'help visible with tags and suffixes' => [
            new Input(
                path: __DIR__.'/src',
                helpSection: Visibility::Show,
                tags: ['fast', 'db'],
                fileSuffixes: ['Prof.php', 'Bench.php'],
            ),
            [
                '--path', __DIR__.'/src',
                '--help',
                '--format', Input::TABLE_FORMAT,
                '--tags', 'fast,db',
                '--file-suffix', 'Prof.php,Bench.php',
            ],
        ];

        yield 'all options enabled' => [
            new Input(
                path: '/tmp',
                helpSection: Visibility::Show,
                infoSection: Visibility::Show,
                versionSection: Visibility::Show,
                format: Input::JSON_FORMAT,
                output: 'out.txt',
                jsonPrettyPrint: State::Enabled,
                inIsolation: State::Enabled,
                dryRun: State::Enabled,
                depth: 5,
                tags: ['t1', 't2'],
                memoryLimit: '512M',
                logFile: 'log.txt',
                fileSuffixes: ['Prof.php'],
                methodVisibilityList: ['public', 'protected']
            ),
            [
                '--path', '/tmp',
                '--info',
                '--help',
                '--version',
                '--format', Input::JSON_FORMAT,
                '--output', 'out.txt',
                '--log', 'log.txt',
                '--pretty',
                '--depth', '5',
                '--tags', 't1,t2',
                '--method-visibility', 'public,protected',
                '--file-suffix', 'Prof.php',
                '--dry-run',
                '--isolation',
                '--memory-limit', '512M',
            ],
        ];
    }

    public function testWithPath(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withPath('/var/www');

        self::assertSame('/tmp', $original->path);
        self::assertSame('/var/www', $modified->path);

        self::assertSame(
            ['--path', '/var/www', '--format', Input::TABLE_FORMAT],
            $modified->toArguments()
        );
    }

    public function testWithFormat(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withFormat(Input::JSON_FORMAT);

        self::assertSame(Input::TABLE_FORMAT, $original->format);
        self::assertSame(Input::JSON_FORMAT, $modified->format);

        self::assertSame(
            ['--path', '/tmp', '--format', Input::JSON_FORMAT],
            $modified->toArguments()
        );
    }

    public function testWithHelpSection(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withHelpSection(Visibility::Show);

        self::assertSame(Visibility::Hide, $original->helpSection);
        self::assertSame(Visibility::Show, $modified->helpSection);

        self::assertContains('--help', $modified->toArguments());
        self::assertNotContains('--help', $original->toArguments());
    }

    public function testWithInfoSection(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withInfoSection(Visibility::Show);

        self::assertSame(Visibility::Hide, $original->infoSection);
        self::assertSame(Visibility::Show, $modified->infoSection);

        self::assertContains('--info', $modified->toArguments());
    }

    public function testWithVersionSection(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withVersionSection(Visibility::Show);

        self::assertSame(Visibility::Hide, $original->versionSection);
        self::assertSame(Visibility::Show, $modified->versionSection);

        self::assertContains('--version', $modified->toArguments());
    }

    public function testWithOutput(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withOutput('out.txt');

        self::assertNull($original->output);
        self::assertSame('out.txt', $modified->output);

        self::assertSame(
            ['--path', '/tmp', '--format', Input::TABLE_FORMAT, '--output', 'out.txt'],
            $modified->toArguments()
        );
    }

    public function testWithLogFile(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withLogFile('log.txt');

        self::assertNull($original->logFile);
        self::assertSame('log.txt', $modified->logFile);

        self::assertContains('--log', $modified->toArguments());
        self::assertContains('log.txt', $modified->toArguments());
    }

    public function testWithJsonPrettyPrint(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withJsnPrettyPrint(State::Enabled);

        self::assertSame(State::Disabled, $original->jsonPrettyPrint);
        self::assertSame(State::Enabled, $modified->jsonPrettyPrint);

        self::assertContains('--pretty', $modified->toArguments());
    }

    public function testWithDryRun(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withDryRun(State::Enabled);

        self::assertSame(State::Disabled, $original->dryRun);
        self::assertSame(State::Enabled, $modified->dryRun);

        self::assertContains('--dry-run', $modified->toArguments());
    }

    public function testWithInIsolation(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withInIsolation(State::Enabled);

        self::assertSame(State::Disabled, $original->inIsolation);
        self::assertSame(State::Enabled, $modified->inIsolation);

        self::assertContains('--isolation', $modified->toArguments());
    }

    public function testWithDepth(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withDepth(3);

        self::assertSame(-1, $original->depth);
        self::assertSame(3, $modified->depth);

        self::assertContains('--depth', $modified->toArguments());
        self::assertContains('3', $modified->toArguments());
    }

    public function testWithTags(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withTags(['db', 'fast']);

        self::assertSame([], $original->tags);
        self::assertSame(['db', 'fast'], $modified->tags);

        self::assertContains('--tags', $modified->toArguments());
        self::assertContains('db,fast', $modified->toArguments());
    }

    public function testWithMemoryLimit(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withMemoryLimit('512M');

        self::assertNull($original->memoryLimit);
        self::assertSame('512M', $modified->memoryLimit);

        self::assertContains('--memory-limit', $modified->toArguments());
        self::assertContains('512M', $modified->toArguments());
    }

    public function testWithFileSuffixes(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withFileSuffixes(['Prof.php', 'Bench.php']);

        self::assertSame([], $original->fileSuffixes);
        self::assertSame(['Prof.php', 'Bench.php'], $modified->fileSuffixes);

        $args = $modified->toArguments();
        self::assertContains('--file-suffix', $args);
        self::assertContains('Prof.php,Bench.php', $args);
    }

    public function testWithMethodVisibilityList(): void
    {
        $original = new Input(path: '/tmp');
        $modified = $original->withMethodVisbility(['public', 'protected']);

        self::assertSame([], $original->methodVisibilityList);
        self::assertSame(['public', 'protected'], $modified->methodVisibilityList);

        $args = $modified->toArguments();
        self::assertContains('--method-visibility', $args);
        self::assertContains('public,protected', $args);
    }
}
