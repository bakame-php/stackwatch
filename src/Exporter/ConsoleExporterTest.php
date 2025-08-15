<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\CallLocation;
use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Statistics;
use Bakame\Stackwatch\Timeline;
use Bakame\Stackwatch\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function usleep;

#[CoversClass(ConsoleExporter::class)]
#[CoversClass(DurationUnit::class)]
#[CoversClass(MemoryUnit::class)]
#[CoversClass(Environment::class)]
#[CoversClass(Snapshot::class)]
#[CoversClass(Timeline::class)]
#[CoversClass(Profiler::class)]
#[CoversClass(Unit::class)]
#[CoversClass(Statistics::class)]
#[CoversClass(CallLocation::class)]
final class ConsoleExporterTest extends TestCase
{
    #[Test]
    public function it_can_output_a_cli_table_for_the_profiler(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $profiler = new Profiler(fn () => usleep(1000));
        $profiler->profile('cli_test');
        $renderer->exportProfiler($profiler);
        $content = $output->fetch();

        self::assertStringContainsString('Label', $content);
        self::assertStringContainsString('CPU Time', $content);
        self::assertStringContainsString('Execution Time', $content);

        self::assertStringContainsString('cli_test', $content);
        self::assertMatchesRegularExpression('/\d+\.\d{3}/', $content); // cpu or exec time
    }

    #[Test]
    public function it_can_output_a_cli_table_for_the_profiling_data(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $summary = Profiler::execute(fn () => usleep(1000));
        $renderer->exportSummary($summary);
        $content = $output->fetch();

        self::assertStringContainsString('Label', $content);
        self::assertStringContainsString('CPU Time', $content);
        self::assertStringContainsString('Execution Time', $content);

        self::assertMatchesRegularExpression('/[a-z0-9][a-z0-9_]*/', $content); //random label
        self::assertMatchesRegularExpression('/\d+\.\d{3}/', $content); // cpu or exec time
    }

    #[Test]
    public function it_can_output_a_cli_table_for_a_snapshot(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $profilled = Profiler::execute(fn () => usleep(1000));
        $renderer->exportSnapshot($profilled->summary->start);
        $content = $output->fetch();

        self::assertStringContainsString('Timestamp', $content);
        self::assertStringContainsString('Real Peak Memory Usage', $content);
        self::assertStringContainsString('CPU', $content);
    }

    #[Test]
    public function it_can_output_a_cli_table_for_the_current_system(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $renderer->exportEnvironment(Environment::current());
        $content = $output->fetch();

        self::assertStringContainsString('Operating System', $content);
        self::assertStringContainsString('PHP Architecture', $content);
        self::assertStringContainsString('Free Disk Space', $content);
        self::assertStringContainsString('CPU', $content);
        self::assertStringContainsString('Raw Memory Limit', $content);
    }

    #[Test]
    public function it_can_export_an_empty_timeline(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $renderer->exportTimeline(new Timeline());
        $content = $output->fetch();

        self::assertStringContainsString('Not enough snapshot to generate an export', $content);
    }

    #[Test]
    public function it_can_export_a_finished_timeline(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $timeline = Timeline::start();
        usleep(1_000);
        $timeline->take('end');

        $renderer->exportTimeline($timeline);
        $content = $output->fetch();

        self::assertStringNotContainsString('Not enough snapshot to generate an export', $content);
        self::assertStringContainsString('Label', $content);
        self::assertStringContainsString('CPU Time', $content);
        self::assertStringContainsString('Execution Time', $content);

        self::assertMatchesRegularExpression('/[a-z0-9][a-z0-9_]*/', $content); //random label
        self::assertMatchesRegularExpression('/\d+\.\d{3}/', $content); // cpu or exec time
    }

    #[Test]
    public function it_can_output_a_cli_table_for_the_a_statistic(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $callback = function (): string {
            usleep(1000);

            return 'end';
        };

        $report = Profiler::report($callback, iterations: 2, warmup: 3);

        $renderer->exportStatistics($report->executionTime);
        $content = $output->fetch();

        self::assertStringContainsString('Range', $content);
        self::assertStringContainsString('Std Dev', $content);
        self::assertStringContainsString('Coef Var', $content);
    }

    #[Test]
    public function it_can_output_a_cli_table_for_the_a_report(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $callback = function (): string {
            usleep(1000);

            return 'end';
        };

        $report = Profiler::report($callback, iterations: 2, warmup: 3);

        $renderer->exportReport($report);
        $content = $output->fetch();

        self::assertStringContainsString('CPU', $content);
        self::assertStringContainsString('Range', $content);
        self::assertStringContainsString('Std Dev', $content);
        self::assertStringContainsString('Real Memory Usage', $content);
    }

    #[Test]
    public function it_can_output_a_cli_table_for_some_metrics(): void
    {
        $output = new BufferedOutput();
        $renderer = new ConsoleExporter($output);

        $summary = Profiler::execute(fn () => usleep(1000));
        $renderer->exportMetrics($summary);
        $content = $output->fetch();

        self::assertStringContainsString('CPU Time', $content);
        self::assertStringContainsString('Execution Time', $content);

        self::assertMatchesRegularExpression('/[a-z0-9][a-z0-9_]*/', $content); //random label
        self::assertMatchesRegularExpression('/\d+\.\d{3}/', $content); // cpu or exec time
    }
}
