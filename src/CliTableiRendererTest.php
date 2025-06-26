<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(CliTableRenderer::class)]
final class CliTableiRendererTest extends TestCase
{
    #[Test]
    public function it_can_output_a_cli_table(): void
    {
        $output = new BufferedOutput();
        $renderer = new CliTableRenderer($output);

        $profiler = new Profiler(fn () => usleep(1000));
        $profiler->runWithLabel('cli_test');
        $renderer->render($profiler);
        $content = $output->fetch();

        self::assertStringContainsString('Label', $content);
        self::assertStringContainsString('CPU Time (s)', $content);
        self::assertStringContainsString('Exec Time (s)', $content);

        self::assertStringContainsString('cli_test', $content);
        self::assertMatchesRegularExpression('/\d+\.\d{6}/', $content); // cpu or exec time
    }

    #[Test]
    public function it_renders_incomplete_or_not_yet_runned_profile(): void
    {
        $output = new BufferedOutput();
        $renderer = new CliTableRenderer($output);
        $profiler = new Profiler(fn () => null);

        $incompleteProfile = new Profile('incomplete');
        $incompleteProfile->beginProfiling();

        $notRunningProfile = new Profile('not_running');

        $reflection = new ReflectionProperty($profiler, 'profiles');
        $reflection->setValue($profiler, [$incompleteProfile, $notRunningProfile]);

        $renderer->render($profiler);
        $outputText = $output->fetch();

        self::assertStringContainsString('Label', $outputText);
        self::assertStringContainsString('incomplete', $outputText);
        self::assertStringContainsString('The profiling is not finished', $outputText);
        self::assertStringContainsString('not_running', $outputText);
        self::assertStringContainsString('The profiling has not started', $outputText);
    }

}
