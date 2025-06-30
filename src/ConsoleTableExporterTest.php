<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function usleep;

#[CoversClass(ConsoleTableExporter::class)]
final class ConsoleTableExporterTest extends TestCase
{
    #[Test]
    public function it_can_output_a_cli_table_for_the_profiler(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleTableExporter($output);

        $profiler = new Profiler(fn () => usleep(1000));
        $profiler->runWithLabel('cli_test');
        $renderer->exportProfiler($profiler);
        $content = $output->fetch();

        self::assertStringContainsString('Label', $content);
        self::assertStringContainsString('CPU Time (s)', $content);
        self::assertStringContainsString('Exec Time (s)', $content);

        self::assertStringContainsString('cli_test', $content);
        self::assertMatchesRegularExpression('/\d+\.\d{6}/', $content); // cpu or exec time
    }

    #[Test]
    public function it_can_output_a_cli_table_for_the_profiling_data(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleTableExporter($output);

        $profilingData = Profiler::execute(fn () => usleep(1000));
        $renderer->exportProfilingData($profilingData);
        $content = $output->fetch();

        self::assertStringContainsString('Label', $content);
        self::assertStringContainsString('CPU Time (s)', $content);
        self::assertStringContainsString('Exec Time (s)', $content);

        self::assertMatchesRegularExpression('/[a-z0-9][a-z0-9_]*/', $content); //random label
        self::assertMatchesRegularExpression('/\d+\.\d{6}/', $content); // cpu or exec time
    }
}
