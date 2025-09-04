<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

use function array_column;
use function json_decode;
use function json_encode;
use function usleep;

#[CoversClass(Metrics::class)]
#[CoversClass(Profiler::class)]
#[CoversClass(Result::class)]
#[CoversClass(LabelGenerator::class)]
#[CoversClass(SpanAggregator::class)]
/**
 * @phpstan-import-type SpanMap from Span
 */
final class SpanAggregatorTest extends TestCase
{
    #[Test]
    public function it_can_be_invoked_and_return_the_result(): void
    {
        $spanAggregator = new SpanAggregator(fn () => 'result');
        $result = $spanAggregator->run();

        self::assertSame('result', $result);
        self::assertCount(1, $spanAggregator);

        $span = $spanAggregator->latest();
        self::assertInstanceOf(Span::class, $span);
        self::assertSame($spanAggregator->latest(), $spanAggregator->first());
    }

    #[Test]
    public function it_can_store_multiple_profiles(): void
    {
        $spanAggregator = new SpanAggregator(fn (int $x) => $x * 2);
        $spanAggregator->run(2);
        $spanAggregator->run(3);

        self::assertCount(2, $spanAggregator);
        foreach ($spanAggregator as $span) {
            self::assertIsFloat($span->metrics->executionTime);
        }
    }

    #[Test]
    public function it_can_use_labels(): void
    {
        $spanAggregator = new SpanAggregator(fn () => 42);
        $spanAggregator->profile('custom_label');

        $span = $spanAggregator->latest();
        self::assertInstanceOf(Span::class, $span);
        self::assertSame('custom_label', $span->label);
    }

    #[Test]
    public function it_can_reset_its_profiles(): void
    {
        $spanAggregator = new SpanAggregator(fn () => null);
        $spanAggregator->run();
        self::assertFalse($spanAggregator->hasNoSpan());
        self::assertTrue($spanAggregator->hasSpans());

        $spanAggregator->reset();
        self::assertTrue($spanAggregator->hasNoSpan());
        self::assertFalse($spanAggregator->hasSpans());
    }

    #[Test]
    public function it_can_be_converted_to_a_json_string(): void
    {
        $spanAggregator = new SpanAggregator(fn () => null);
        $spanAggregator->run();
        /** @var non-empty-string $json */
        $json = json_encode($spanAggregator);
        /** @var array<SpanMap> $data */
        $data = json_decode($json, true); /** @phpstan-ignore-line */
        /** @var SpanMap $spanMaps */
        $spanMaps = $data['summaries'][0];  /* @phpstan-ignore-line */

        self::assertIsArray($spanMaps);
        self::assertArrayHasKey('label', $spanMaps);
        self::assertArrayHasKey('metrics', $spanMaps);
    }

    #[Test]
    public function it_can_store_and_retrieve_multiple_profiles(): void
    {
        $spanAggregator = new SpanAggregator(fn (int $x) => $x * 2);
        $spanAggregator->profile('custom_label', 4);
        $spanAggregator->run(2);
        $spanAggregator->run(3);
        $spanAggregator->profile('custom_label', 5);

        self::assertCount(4, $spanAggregator);
        self::assertCount(2, $spanAggregator->getAll('custom_label'));
        self::assertInstanceOf(Span::class, $spanAggregator->get('custom_label'));
        self::assertNull($spanAggregator->get('foo_bar'));
        self::assertCount(0, $spanAggregator->getAll('foo_bar'));
        self::assertTrue($spanAggregator->has('custom_label'));
        self::assertFalse($spanAggregator->has('foo_bar'));
        self::assertCount(3, $spanAggregator->labels());
    }

    #[Test]
    public function testItLogsStartAndEnd(): void
    {
        $logger = new InMemoryLogger();
        $spanAggregator = new SpanAggregator(fn () => usleep(10_000), logger: $logger);
        $spanAggregator->profile('simple_test');

        self::assertCount(2, $logger->logs, 'Expected 2 log entries');
        self::assertSame('info', $logger->logs[0]['level']);
        self::assertStringContainsString('starting profiling for label: simple_test.', (string) $logger->logs[0]['message']);

        self::assertSame('info', $logger->logs[1]['level']);
        self::assertStringContainsString('ending profiling for label: simple_test.', (string) $logger->logs[1]['message']);
        self::assertArrayHasKey('metrics', $logger->logs[1]['context']);
    }

    #[Test]
    public function it_can_log_failure(): void
    {
        $logger = new InMemoryLogger();
        $spanAggregator = new SpanAggregator(fn () => throw new RuntimeException('Test crash'), logger: $logger);

        $this->expectException(RuntimeException::class);

        try {
            $spanAggregator->profile('fail_case');
        } finally {
            self::assertCount(2, $logger->logs);
            self::assertSame('info', $logger->logs[0]['level']);
            self::assertSame('error', $logger->logs[1]['level']);
            self::assertStringContainsString('profiling aborted for label: fail_case due to an error in the executed code.', (string) $logger->logs[1]['message']);
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
