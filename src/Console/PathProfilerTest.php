<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

#[CoversClass(PathProfiler::class)]
#[CoversClass(Profile::class)]
#[CoversClass(PathInspector::class)]
#[CoversClass(JsonProcessor::class)]
#[CoversClass(ConsoleProcessor::class)]
#[CoversClass(UnitOfWorkGenerator::class)]
#[CoversClass(State::class)]
#[CoversClass(Visibility::class)]
final class PathProfilerTest extends TestCase
{
    private string $tmpFile;
    private BufferedOutput $stdout;
    private BufferedOutput $stderr;
    private PathProfiler $command;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'profiler_test').'.php';
        $this->stdout = new BufferedOutput();
        $this->stderr = new BufferedOutput();
        $logger = new Logger($this->stderr);
        $this->command = new PathProfiler(
            new UnitOfWorkGenerator(new PathInspector(Profile::class), $logger),
            new ConsoleProcessor(new ConsoleExporter($this->stdout)),
            $logger
        );
    }

    protected function tearDown(): void
    {
        unlink($this->tmpFile);
    }

    private function loadTempFile(string $content): void
    {
        file_put_contents($this->tmpFile, $content);
    }

    #[Test]
    public function it_handle_profiles_functions_and_methods(): void
    {

        $content = <<<PHP
<?php
namespace Test;

use Bakame\Stackwatch\Profile;

#[Profile(iterations: 5, warmup: 1, type: Profile::DETAILED)]
function testFunction(): void
{
    usleep(1000);
}

class TestClass
{
    #[Profile(iterations: 3, warmup: 1, type: Profile::SUMMARY)]
    public function testMethod(): void
    {
        usleep(500);
    }
}
PHP;

        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertStringContainsString('Detailed metrics for ', $output);
        self::assertStringContainsString('Average metrics for ', $output);

        self::assertEmpty($errorOutput, 'No errors expected');
    }

    #[Test]
    public function it_handle_profiles_enums(): void
    {
        $content = <<<PHP
<?php
namespace Test;

use Bakame\Stackwatch\Profile;

enum TestEnum
{
    case Foo;

    #[Profile(iterations: 3, warmup: 1, type: Profile::DETAILED)]
    public function testMethod(): void
    {
        usleep(500);
    }
}
PHP;

        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertStringContainsString('Detailed metrics for ', $output);
        self::assertEmpty($errorOutput, 'No errors expected');
    }

    #[Test]
    public function it_will_skip_methods_with_arguments(): void
    {
        $content = <<<'PHP'
<?php
namespace Test;

use Bakame\Stackwatch\Profile;

enum TestMethodWithArguments
{
    case Foo;

    #[Profile(iterations: 3, warmup: 1, type: Profile::SUMMARY)]
    public function testMethod(string $foo): string
    {
        return $foo;
    }
}
PHP;

        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertStringNotContainsString('Report for the method', $output);
        self::assertStringContainsString('The method Test\TestMethodWithArguments::testMethod', $errorOutput);
        self::assertStringContainsString('can not be profiled because it has arguments.', $errorOutput);

    }

    #[Test]
    public function it_will_skip_methods_on_interface(): void
    {
        $content = <<<'PHP'
<?php
namespace Test;

use Bakame\Stackwatch\Profile;

interface TestInterface
{
    #[Profile(iterations: 3, warmup: 1, type: Profile::SUMMARY)]
    public function testMethod(string $foo): string;
}
PHP;
        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertEmpty($output, 'No messages expected');
        self::assertEmpty($errorOutput, 'No messages expected');
    }

    #[Test]
    public function it_will_skip_non_static_methds_when_the_class_constructor_requires_arguments(): void
    {
        $content = <<<'PHP'
<?php
namespace Test;

use Bakame\Stackwatch\Profile;

class TestConstructorsArgument
{
    public function __construct(public readonly string $foo)
    {
    }

    #[Profile(iterations: 3, warmup: 1, type: Profile::SUMMARY)]
    public function testMethod(): int
    {
        return 42;
    }
}
PHP;
        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertEmpty($output, 'No success messages expected');
        self::assertStringContainsString('The non-static method Test\TestConstructorsArgument::testMethod', $errorOutput);
        self::assertStringContainsString('can not be profiled because the class requires constructor arguments.', $errorOutput);
    }

    #[Test]
    public function it_will_skip_abstract_methds(): void
    {
        $content = <<<'PHP'
<?php
namespace Test;

use Bakame\Stackwatch\Profile;

abstract class TestAbstractMethod
{
    #[Profile(iterations: 3, warmup: 1, type: Profile::SUMMARY)]
    public abstract function testMethod(): int;
}
PHP;
        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertEmpty($output, 'No success messages expected');
        self::assertStringContainsString('The method Test\TestAbstractMethod::testMethod', $errorOutput);
        self::assertStringContainsString('can not be profiled because it is abstract.', $errorOutput);
    }

    #[Test]
    public function it_will_skip_functions_without_attributes(): void
    {
        $content = <<<'PHP'
<?php
namespace Test;

function foo(): string
{
    return 'quarante-deux';
}
PHP;
        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertEmpty($output, 'No success messages expected');
        self::assertEmpty($errorOutput, 'No messages expected');
    }

    #[Test]
    public function it_will_not_profile_functions_with_arguments(): void
    {
        $content = <<<'PHP'
<?php
namespace Test;

use Bakame\Stackwatch\Profile as Yolo;

#[Yolo(iterations: 3, warmup: 1, type: Yolo::SUMMARY)]
function foo_with_arguments(string $foo): string
{
    return $foo;
}
PHP;
        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertEmpty($output, 'No success messages expected');
        self::assertStringContainsString('The function Test\foo_with_arguments', $errorOutput);
        self::assertStringContainsString('can not be profiled because it has arguments.', $errorOutput);
    }

    public function it_will_handle_partial_namespace_redering(): void
    {
        $content = <<<'PHP'
<?php

namespace Test;

use Bakame\Stackwatch;

trait PartielTimerTrait {
    #[Stackwatch\Profile(type: Profiler\Profile::METRICS, iterations: 10)]
    public function test() : int {
        usleep(100);

        return random_int(1, 100);
    }
}

enum PartialFoobar
{
    use PartielTimerTrait;

    case Foobar;
}

#[Stackwatch\Profile(type: Profiler\Profile::REPORT, iterations: 20, warmup: 2)]
function test() : void {
    Profiler\Profiler::metrics(PartialFoobar::Foobar->test(...), 5);
}
PHP;

        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertEmpty($output, 'No success messages expected');
        self::assertEmpty($errorOutput, 'No error messages expected');
    }

    #[Test]
    public function it_will_handle_the_attribute_on_the_class(): void
    {
        $content = <<<'PHP'
<?php
namespace Test;

use Bakame\Stackwatch\Profile;

#[Profile(type: Profile::SUMMARY, iterations: 10)]
class PartialFoobarClass
{
    public function test() : int
    {
        usleep(100);

        return random_int(1, 100);
    }
}
PHP;

        $this->loadTempFile($content);
        $this->command->handle($this->tmpFile);

        $output = $this->stdout->fetch();
        $errorOutput = $this->stderr->fetch();

        self::assertNotEmpty($output, 'should contain some data');
        self::assertEmpty($errorOutput, 'No error messages expected');
    }
}
