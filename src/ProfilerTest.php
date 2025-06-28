<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

use function array_column;
use function json_encode;
use function usleep;

#[CoversClass(Metrics::class)]
#[CoversClass(Profiler::class)]
#[CoversClass(ProfilingResult::class)]
/**
 * @phpstan-import-type ProfileMetrics from ProfilingData
 */
final class ProfilerTest extends TestCase
{
    #[Test]
    public function it_can_return_each_metrics_separately(): void
    {
        $callback = function (): string {
            usleep(1000);

            return 'end';
        };

        self::assertGreaterThanOrEqual(0, Profiler::executionTime($callback, 2));
        self::assertGreaterThanOrEqual(0, Profiler::cpuTime($callback, 3));
        self::assertGreaterThanOrEqual(0, Profiler::memoryUsage($callback));
        self::assertGreaterThanOrEqual(0, Profiler::realMemoryUsage($callback, 3));
        self::assertGreaterThanOrEqual(0, Profiler::peakMemoryUsage($callback, 2));
        self::assertGreaterThanOrEqual(0, Profiler::realPeakMemoryUsage($callback));
    }

    #[Test]
    public function it_fails_to_return_the_metrics_on_invalid_iteration_argument(): void
    {
        $this->expectException(InvalidArgument::class);

        Profiler::executionTime(fn () => null, 0); /* @phpstan-ignore-line  */
    }

    #[Test]
    public function it_can_be_invoked_and_return_the_result(): void
    {
        $profiler = new Profiler(fn () => 'result');
        $result = $profiler();

        self::assertSame('result', $result);
        self::assertCount(1, $profiler);

        $profile = $profiler->last();
        self::assertInstanceOf(ProfilingData::class, $profile);
    }

    #[Test]
    public function it_can_store_multiple_profiles(): void
    {
        $profiler = new Profiler(fn (int $x) => $x * 2);
        $profiler(2);
        $profiler(3);

        self::assertCount(2, $profiler);
        foreach ($profiler as $profile) {
            self::assertIsFloat($profile->metrics->executionTime);
        }
    }

    #[Test]
    public function it_can_use_labels(): void
    {
        $profiler = new Profiler(fn () => 42);
        $profiler->runWithLabel('custom_label');

        $profile = $profiler->last();
        self::assertInstanceOf(ProfilingData::class, $profile);
        self::assertSame('custom_label', $profile->label);
    }

    #[Test]
    public function it_can_reset_its_profiles(): void
    {
        $profiler = new Profiler(fn () => null);
        $profiler();
        self::assertFalse($profiler->isEmpty());

        $profiler->reset();
        self::assertTrue($profiler->isEmpty());
    }

    #[Test]
    public function it_can_be_converted_to_a_json_string(): void
    {
        $profiler = new Profiler(fn () => null);
        $profiler();
        /** @var non-empty-string $json */
        $json = json_encode($profiler);
        /** @var array<ProfileMetrics> $data */
        $data = json_decode($json, true); /** @phpstan-ignore-line */
        /** @var ProfileMetrics $profile */
        $profile = $data[0];  /* @phpstan-ignore-line */

        self::assertIsArray($profile);
        self::assertArrayHasKey('label', $profile);
        self::assertArrayHasKey('metrics', $profile);
    }

    #[Test]
    public function it_can_store_and_retrieve_multiple_profiles(): void
    {
        $profiler = new Profiler(fn (int $x) => $x * 2);
        $profiler->runWithLabel('custom_label', 4);
        $profiler(2);
        $profiler(3);
        $profiler->runWithLabel('custom_label', 5);

        self::assertCount(4, $profiler);
        self::assertCount(2, $profiler->getAll('custom_label'));
        self::assertInstanceOf(ProfilingData::class, $profiler->get('custom_label'));
        self::assertNull($profiler->get('foo_bar'));
        self::assertCount(0, $profiler->getAll('foo_bar'));
        self::assertTrue($profiler->has('custom_label'));
        self::assertFalse($profiler->has('foo_bar'));
        self::assertCount(3, $profiler->labels());
    }

    #[Test]
    public function testItLogsStartAndEnd(): void
    {
        $logger = new InMemoryLogger();
        $profiler = new Profiler(fn () => usleep(10_000), $logger);
        $profiler->runWithLabel('simple_test');

        self::assertCount(2, $logger->logs, 'Expected 2 log entries');
        self::assertSame('info', $logger->logs[0]['level']);
        self::assertStringContainsString('Starting profiling for label: simple_test.', (string) $logger->logs[0]['message']);

        self::assertSame('info', $logger->logs[1]['level']);
        self::assertStringContainsString('Finished profiling for label: simple_test.', (string) $logger->logs[1]['message']);
        dump($logger->logs[1]['context']);
        self::assertArrayHasKey('metrics', $logger->logs[1]['context']);
    }

    public function it_can_log_failure(): void
    {
        $logger = new InMemoryLogger();
        $profiler = new Profiler(function () {
            throw new RuntimeException('Test crash');
        }, $logger);

        $this->expectException(RuntimeException::class);

        try {
            $profiler->runWithLabel('fail_case');
        } finally {
            self::assertCount(2, $logger->logs);
            self::assertSame('info', $logger->logs[0]['level']);
            self::assertSame('error', $logger->logs[1]['level']);
            self::assertStringContainsString('Profiling failed for label: fail_case', (string) $logger->logs[1]['message']);
            self::assertArrayHasKey('exception', $logger->logs[1]['context']);
        }
    }
}


class InMemoryLogger extends AbstractLogger
{
    /**
     * @var non-empty-array<array{level:mixed, message:string|Stringable, context:array<array-key, mixed>}>|array{}
     */
    public array $logs = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * Get messages only.
     *
     * @return array<string|Stringable>
     */
    public function messages(): array
    {
        return array_column($this->logs, 'message');
    }
}
