<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(UnitOfWork::class)]
final class UnitOfWorkTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'profiler_test').'.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function loadTempFile(string $content): void
    {
        file_put_contents($this->tmpFile, $content);
    }

    #[Test]
    public function it_test_from_array_with_function_from_temporary_file(): void
    {
        $content = <<<PHP
    <?php namespace Test;

    use Bakame\Stackwatch\Profile;

    #[Profile(iterations: 5, warmup: 1, type: Profile::DETAILED)]
    function testCommandLineFunction2(): void
    {
        usleep(1000);
    }
    PHP;

        $this->loadTempFile($content);

        require_once $this->tmpFile;

        $data = [
            'path' => $this->tmpFile,
            'function' => 'Test\\testCommandLineFunction2',
            'type' => 'detailed',
            'iterations' => 5,
            'warmup' => 1,
            'run_at' => null,
            'attributes' => [],
        ];
        $unit = UnitOfWork::fromArray($data);

        self::assertSame('Test\\testCommandLineFunction2', $unit->toArray()['function']);
    }

    public function testFromArrayWithClassMethodTarget(): void
    {
        $content = <<<PHP
<?php
namespace Temp;

use Bakame\Stackwatch\Profile;

class SampleClass
{
    #[Profile(iterations: 3, warmup: 1, type: Profile::SUMMARY)]
    public function sampleMethod(): void
    {
        usleep(500);
    }
}
PHP;

        $this->loadTempFile($content);

        require_once $this->tmpFile;

        $arr = [
            'path' => $this->tmpFile,
            'class' => 'Temp\\SampleClass',
            'method' => 'sampleMethod',
            'type' => 'summary',
            'iterations' => 3,
            'warmup' => 1,
            'run_at' => null,
            'attributes' => [],
        ];
        $unit = UnitOfWork::fromArray($arr); /* @phpstan-ignore-line */

        self::assertSame('Temp\\SampleClass', $unit->toArray()['class']);
        self::assertSame('sampleMethod', $unit->toArray()['method']);
    }

    public function testFromArrayWithEnumCaseMethod(): void
    {
        $content = <<<PHP
<?php
namespace Temp;

use Bakame\Stackwatch\Profile;

enum SampleEnum
{
    case FIRST;

    #[Profile(iterations: 2, warmup: 1, type: Profile::SUMMARY)]
    public function run(): void
    {
        usleep(200);
    }
}
PHP;
        $this->loadTempFile($content);

        require_once $this->tmpFile;

        $data = [
            'path' => $this->tmpFile,
            'class' => 'Temp\\SampleEnum',
            'method' => 'run',
            'type' => 'summary',
            'iterations' => 2,
            'warmup' => 1,
            'run_at' => null,
            'attributes' => [],
        ];
        $unit = UnitOfWork::fromArray($data); /* @phpstan-ignore-line */

        $targetArray = $unit->toArray();
        self::assertSame('Temp\\SampleEnum', $targetArray['class']);
        self::assertSame('run', $targetArray['method']);
    }
}
