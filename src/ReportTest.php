<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Report::class)]
#[CoversClass(Statistics::class)]
#[CoversClass(Unit::class)]
final class ReportTest extends TestCase
{
    #[Test]
    public function it_can_be_converted_to_array_an_json(): void
    {
        $statsNano = Statistics::fromValues(Unit::Nanoseconds, [1_000, 2_000, 3_000]);
        $statsBytes = Statistics::fromValues(Unit::Bytes, [1_000, 2_000, 3_000]);

        $report = new Report(
            cpuTime: $statsNano,
            executionTime: $statsNano,
            memoryUsage: $statsBytes,
            peakMemoryUsage: $statsBytes,
            realMemoryUsage: $statsBytes,
            realPeakMemoryUsage: $statsBytes,
        );

        $array = $report->toArray();

        self::assertSame($statsNano->toArray(), $array['cpu_time']);
        self::assertSame($statsNano->toArray(), $array['execution_time']);
        self::assertSame($statsBytes->toArray(), $array['memory_usage']);
        self::assertSame($statsBytes->toArray(), $array['peak_memory_usage']);
        self::assertSame($statsBytes->toArray(), $array['real_memory_usage']);
        self::assertSame($statsBytes->toArray(), $array['real_peak_memory_usage']);

        $json = $report->jsonSerialize();

        self::assertSame($statsNano, $json['cpu_time']);
        self::assertSame($statsNano, $json['execution_time']);
        self::assertSame($statsBytes, $json['memory_usage']);
        self::assertSame($statsBytes, $json['peak_memory_usage']);
        self::assertSame($statsBytes, $json['real_memory_usage']);
        self::assertSame($statsBytes, $json['real_peak_memory_usage']);
    }

    #[Test]
    public function it_can_be_instantiated_from_metrics_aggregates(): void
    {
        $metric1 = new Metrics(100, 200, 300, 400, 500, 600);
        $metric2 = new Metrics(110, 210, 310, 410, 510, 610);

        $report = Report::fromMetrics($metric1, $metric2);

        self::assertEquals(
            Statistics::fromValues(Unit::Nanoseconds, [100, 110])->toArray(),
            $report->toArray()['cpu_time']
        );

        self::assertEquals(
            Statistics::fromValues(Unit::Bytes, [300, 310])->toArray(),
            $report->toArray()['memory_usage']
        );
    }

    #[Test]
    public function it_can_do_a_roundtrup_with_from_and_to_array_methods(): void
    {
        $statsBytes = Statistics::fromValues(Unit::Bytes, [10, 20, 30]);
        $statsNano = Statistics::fromValues(Unit::Nanoseconds, [10, 20, 30]);

        $array = [
            'cpu_time' => $statsNano->toArray(),
            'execution_time' => $statsNano->toArray(),
            'memory_usage' => $statsBytes->toArray(),
            'real_memory_usage' => $statsBytes->toArray(),
            'peak_memory_usage' => $statsBytes->toArray(),
            'real_peak_memory_usage' => $statsBytes->toArray(),
        ];

        self::assertSame($array, Report::fromArray($array)->toArray());
    }

    #[Test]
    public function it_will_fail_instantiation_with_the_wrong_unit(): void
    {
        $statsBytes = Statistics::fromValues(Unit::Bytes, [10, 20, 30]);

        $array = [
            'cpu_time' => $statsBytes->toArray(),
            'execution_time' => $statsBytes->toArray(),
            'memory_usage' => $statsBytes->toArray(),
            'real_memory_usage' => $statsBytes->toArray(),
            'peak_memory_usage' => $statsBytes->toArray(),
            'real_peak_memory_usage' => $statsBytes->toArray(),
        ];

        $this->expectException(InvalidArgument::class);
        Report::fromArray($array);
    }

    #[Test]
    public function it_will_fail_instantiation_with_missing_keys(): void
    {
        $validStats = Statistics::fromValues(Unit::Bytes, [1, 2]);
        $payload = [
            'cpu_time' => Statistics::fromValues(Unit::Nanoseconds, [1, 2])->toArray(),
            'memory_usage' => $validStats->toArray(),
            'real_memory_usage' => $validStats->toArray(),
            'peak_memory_usage' => $validStats->toArray(),
            'real_peak_memory_usage' => $validStats->toArray(),
        ];

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('The payload is missing the following keys: execution_time');

        Report::fromArray($payload); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_can_return_an_empty_report(): void
    {
        $report = Report::none();

        foreach ($report->toArray() as $key => $stat) {
            self::assertSame(0, $stat['count'], "Failed asserting count is 0 for $key");
            self::assertArrayHasKey('average', $stat);
        }
    }
}
