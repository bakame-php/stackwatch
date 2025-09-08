<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\CallLocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ide::class)]
final class IdeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
        $_ENV = [];
    }

    public function testFromEnvWithGet(): void
    {
        $_GET['ide'] = 'vscode';
        $ide = Ide::fromEnv();
        self::assertSame(Ide::VSCode, $ide);
    }

    public function testFromEnvWithEnv(): void
    {
        $_ENV['IDE'] = 'jetbrains';
        $ide = Ide::fromEnv();
        self::assertSame(Ide::JetBrains, $ide);
    }

    public function testFromEnvWithDefault(): void
    {
        $ide = Ide::fromEnv('jetbrains');
        self::assertSame(Ide::JetBrains, $ide);
    }

    public function testFromEnvWithInvalidFallsBackToPhpStorm(): void
    {
        $ide = Ide::fromEnv('unknown');
        self::assertSame(Ide::PhpStorm, $ide);
    }

    public function testUriForJetBrains(): void
    {
        $loc = new CallLocation('/tmp/file.php', 42);
        $uri = Ide::JetBrains->uri($loc);

        self::assertStringContainsString('jetbrains://php-storm/navigate/reference?', $uri);
        self::assertStringContainsString('project=UNKNOWN_PROJECT', $uri);
        self::assertStringContainsString('path=%2Ftmp%2Ffile.php', $uri);
        self::assertStringContainsString('line=42', $uri);
    }

    public function testUriForVSCode(): void
    {
        $loc = new CallLocation('/tmp/file.php', 42);
        $uri = Ide::VSCode->uri($loc);

        self::assertSame('vscode://file/tmp/file.php:42', $uri);
    }

    public function testUriForPhpStorm(): void
    {
        $loc = new CallLocation('/tmp/file.php', 42);
        $uri = Ide::PhpStorm->uri($loc);

        self::assertStringStartsWith('phpstorm://open?', $uri);
        self::assertStringContainsString('file=%2Ftmp%2Ffile.php', $uri);
        self::assertStringContainsString('line=42', $uri);
    }

    public function testPathNormalizesSlashes(): void
    {
        $loc = new CallLocation('C:\\path\\to\\file.php', 99);
        self::assertSame('C:/path/to/file.php:99', Ide::PhpStorm->path($loc));
    }
}
