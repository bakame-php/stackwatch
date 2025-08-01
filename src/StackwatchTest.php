<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(StackwatchInput::class)]
#[CoversClass(Stackwatch::class)]
#[CoversClass(Target::class)]
final class StackwatchTest extends TestCase
{
    private BufferedOutput $stdout;
    private BufferedOutput $stderr;
    private Stackwatch $cli;

    protected function setUp(): void
    {
        $this->stdout = new BufferedOutput();
        $this->stderr = new BufferedOutput();
        $this->cli = new Stackwatch($this->stdout, $this->stderr, new ConsoleLogger($this->stderr), Environment::current());
    }

    #[Test]
    public function it_can_show_the_version_message(): void
    {
        self::assertSame(Stackwatch::SUCCESS, $this->cli->execute(StackwatchInput::fromInput(['version' => false])));
        self::assertStringContainsString(Version::full(), $this->stdout->fetch());
    }

    #[Test]
    public function it_can_show_the_help_message(): void
    {
        self::assertSame(Stackwatch::SUCCESS, $this->cli->execute(StackwatchInput::fromInput(['help' => false])));
        self::assertStringContainsString('Profiles functions and methods in a PHP codebase using #[Profile] attributes.', $this->stdout->fetch());
    }

    #[Test]
    public function it_can_show_the_info_message_even_without_the_path_provided(): void
    {
        self::assertSame(Stackwatch::SUCCESS, $this->cli->execute(StackwatchInput::fromInput(['i' => false])));
    }

    #[Test]
    public function it_fails_if_the_path_is_missing(): void
    {
        $this->expectExceptionObject(new InvalidArgument('Missing required option: --path'));

        $this->cli->execute(StackwatchInput::fromInput(['path' => false]));
    }

    #[Test]
    public function it_fails_if_the_path_is_invalid(): void
    {
        self::assertSame(Stackwatch::ERROR, $this->cli->execute(StackwatchInput::fromInput(['path' => 'foobar.php'])));
        self::assertStringContainsString('Execution Error', $this->stderr->fetch());
    }

    #[Test]
    public function it_handles_correctly_a_found_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'profiler_test').'.php';
        $content = <<<PHP
<?php
namespace Test;

use Bakame\Stackwatch\Profile;

#[Profile(iterations: 5, warmup: 1, type: Profile::DETAILED)]
function testCommandLineFunction(): void
{
    usleep(1000);
}

class TestCommandLineClass
{
    #[Profile(iterations: 3, warmup: 1, type: Profile::SUMMARY)]
    public function testMethod(): void
    {
        usleep(500);
    }
}
PHP;
        file_put_contents($tmpFile, $content);

        self::assertSame(Stackwatch::SUCCESS, $this->cli->execute(StackwatchInput::fromInput(['path' => $tmpFile])));
        unlink($tmpFile);

        $output = $this->stdout->fetch();

        self::assertStringContainsString('testCommandLineFunction', $output);
        self::assertStringContainsString('TestCommandLineClass::testMethod', $output);
    }
}
