<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Report::class)]
#[CoversClass(Statistics::class)]
#[CoversClass(AggregatedMetrics::class)]
#[CoversClass(Unit::class)]
final class ReportTest extends TestCase
{
    #[Test]
    public function it_can_be_converted_to_array_an_json(): void
    {
        $report = new Report(
            cpuTime: $cpuTime = Statistics::fromValues(MetricType::CpuTime, [1_000, 2_000, 3_000]),
            executionTime: $executionTime = Statistics::fromValues(MetricType::ExecutionTime, [1_000, 2_000, 3_000]),
            memoryUsage: $memoryUsage = Statistics::fromValues(MetricType::MemoryUsage, [1_000, 2_000, 3_000]),
            memoryUsageGrowth: $memoryUsageGrowth = Statistics::fromValues(MetricType::MemoryUsageGrowth, [1_000, 2_000, 3_000]),
            peakMemoryUsage: $peakMemoryUsage = Statistics::fromValues(MetricType::PeakMemoryUsage, [1_000, 2_000, 3_000]),
            peakMemoryUsageGrowth: $peakMemoryUsageGrowth = Statistics::fromValues(MetricType::PeakMemoryUsageGrowth, [1_000, 2_000, 3_000]),
            realMemoryUsage: $realMemoryUsage = Statistics::fromValues(MetricType::RealMemoryUsage, [1_000, 2_000, 3_000]),
            realMemoryUsageGrowth: $realMemoryUsageGrowth = Statistics::fromValues(MetricType::RealMemoryUsageGrowth, [1_000, 2_000, 3_000]),
            realPeakMemoryUsage: $realPeakMemoryUsageGrowth = Statistics::fromValues(MetricType::RealPeakMemoryUsage, [1_000, 2_000, 3_000]),
            realPeakMemoryUsageGrowth: $realPeakMemoryUsageGrowth = Statistics::fromValues(MetricType::RealPeakMemoryUsageGrowth, [1_000, 2_000, 3_000]),
        );

        $array = $report->toArray();

        self::assertSame($cpuTime->toArray(), $array['cpu_time']);
        self::assertSame($executionTime->toArray(), $array['execution_time']);
        self::assertSame($memoryUsage->toArray(), $array['memory_usage']);

        $json = $report->jsonSerialize();

        self::assertSame($cpuTime, $json['cpu_time']);
        self::assertSame($executionTime, $json['execution_time']);
        self::assertSame($memoryUsage, $json['memory_usage']);
    }

    #[Test]
    public function it_can_be_instantiated_from_metrics_aggregates(): void
    {
        $metric1 = new Metrics(100, 200, 300, 300, 400, 400, 500, 500, 600, 600);
        $metric2 = new Metrics(110, 210, 310, 310, 410, 410, 510, 510, 610, 600);

        $report = Report::fromMetrics($metric1, $metric2);

        self::assertEquals(
            Statistics::fromValues(MetricType::CpuTime, [100, 110])->toArray(),
            $report->toArray()['cpu_time']
        );

        self::assertEquals(
            Statistics::fromValues(MetricType::MemoryUsage, [300, 310])->toArray(),
            $report->toArray()['memory_usage']
        );
    }

    #[Test]
    public function it_can_do_a_round_trip_with_from_and_to_array_methods(): void
    {
        $cpuTime = Statistics::fromValues(MetricType::CpuTime, [1_000, 2_000, 3_000]);
        $executionTime = Statistics::fromValues(MetricType::ExecutionTime, [1_000, 2_000, 3_000]);
        $memoryUsage = Statistics::fromValues(MetricType::MemoryUsage, [1_000, 2_000, 3_000]);
        $memoryUsageGrowth = Statistics::fromValues(MetricType::MemoryUsageGrowth, [1_000, 2_000, 3_000]);
        $peakMemoryUsage = Statistics::fromValues(MetricType::PeakMemoryUsage, [1_000, 2_000, 3_000]);
        $peakMemoryUsageGrowth = Statistics::fromValues(MetricType::PeakMemoryUsageGrowth, [1_000, 2_000, 3_000]);
        $realMemoryUsage = Statistics::fromValues(MetricType::RealMemoryUsage, [1_000, 2_000, 3_000]);
        $realMemoryUsageGrowth = Statistics::fromValues(MetricType::RealMemoryUsageGrowth, [1_000, 2_000, 3_000]);
        $realPeakMemoryUsage = Statistics::fromValues(MetricType::RealPeakMemoryUsage, [1_000, 2_000, 3_000]);
        $realPeakMemoryUsageGrowth = Statistics::fromValues(MetricType::RealPeakMemoryUsageGrowth, [1_000, 2_000, 3_000]);

        $array = [
            'cpu_time' => $cpuTime->toArray(),
            'execution_time' => $executionTime->toArray(),
            'memory_usage' => $memoryUsage->toArray(),
            'memory_usage_growth' => $memoryUsageGrowth->toArray(),
            'real_memory_usage' => $realMemoryUsage->toArray(),
            'real_memory_usage_growth' => $realMemoryUsageGrowth->toArray(),
            'peak_memory_usage' => $peakMemoryUsage->toArray(),
            'peak_memory_usage_growth' => $peakMemoryUsageGrowth->toArray(),
            'real_peak_memory_usage' => $realPeakMemoryUsage->toArray(),
            'real_peak_memory_usage_growth' => $realPeakMemoryUsageGrowth->toArray(),
        ];

        self::assertSame($array, Report::fromArray($array)->toArray());
    }

    #[Test]
    public function it_will_fail_instantiation_with_the_wrong_unit(): void
    {
        $cpuTime = Statistics::fromValues(MetricType::CpuTime, [1_000, 2_000, 3_000]);
        $memoryUsage = Statistics::fromValues(MetricType::MemoryUsage, [1_000, 2_000, 3_000]);
        $memoryUsageGrowth = Statistics::fromValues(MetricType::MemoryUsageGrowth, [1_000, 2_000, 3_000]);
        $peakMemoryUsage = Statistics::fromValues(MetricType::PeakMemoryUsage, [1_000, 2_000, 3_000]);
        $peakMemoryUsageGrowth = Statistics::fromValues(MetricType::PeakMemoryUsageGrowth, [1_000, 2_000, 3_000]);
        $realMemoryUsage = Statistics::fromValues(MetricType::RealMemoryUsage, [1_000, 2_000, 3_000]);
        $realMemoryUsageGrowth = Statistics::fromValues(MetricType::RealMemoryUsageGrowth, [1_000, 2_000, 3_000]);
        $realPeakMemoryUsage = Statistics::fromValues(MetricType::RealPeakMemoryUsage, [1_000, 2_000, 3_000]);
        $realPeakMemoryUsageGrowth = Statistics::fromValues(MetricType::RealPeakMemoryUsageGrowth, [1_000, 2_000, 3_000]);

        $array = [
            'cpu_time' => $cpuTime->toArray(),
            'execution_time' => $cpuTime->toArray(),
            'memory_usage' => $memoryUsage->toArray(),
            'memory_usage_growth' => $memoryUsageGrowth->toArray(),
            'real_memory_usage' => $realMemoryUsage->toArray(),
            'real_memory_usage_growth' => $realMemoryUsageGrowth->toArray(),
            'peak_memory_usage' => $peakMemoryUsage->toArray(),
            'peak_memory_usage_growth' => $peakMemoryUsageGrowth->toArray(),
            'real_peak_memory_usage' => $realPeakMemoryUsage->toArray(),
            'real_peak_memory_usage_growth' => $realPeakMemoryUsageGrowth->toArray(),
        ];

        $this->expectException(InvalidArgument::class);
        Report::fromArray($array);
    }

    #[Test]
    public function it_will_fail_instantiation_with_missing_keys(): void
    {
        $cpuTime = Statistics::fromValues(MetricType::CpuTime, [1_000, 2_000, 3_000]);
        $executionTime = Statistics::fromValues(MetricType::ExecutionTime, [1_000, 2_000, 3_000]);
        $memoryUsage = Statistics::fromValues(MetricType::MemoryUsage, [1_000, 2_000, 3_000]);
        $memoryUsageGrowth = Statistics::fromValues(MetricType::MemoryUsageGrowth, [1_000, 2_000, 3_000]);
        $peakMemoryUsage = Statistics::fromValues(MetricType::PeakMemoryUsage, [1_000, 2_000, 3_000]);
        $realMemoryUsage = Statistics::fromValues(MetricType::RealMemoryUsage, [1_000, 2_000, 3_000]);
        $realMemoryUsageGrowth = Statistics::fromValues(MetricType::RealMemoryUsageGrowth, [1_000, 2_000, 3_000]);

        $array = [
            'cpu_time' => $cpuTime->toArray(),
            'execution_time' => $executionTime->toArray(),
            'memory_usage' => $memoryUsage->toArray(),
            'memory_usage_growth' => $memoryUsageGrowth->toArray(),
            'real_memory_usage' => $realMemoryUsage->toArray(),
            'real_memory_usage_growth' => $realMemoryUsageGrowth->toArray(),
            'peak_memory_usage' => $peakMemoryUsage->toArray(),
        ];

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('The payload is missing the following keys: peak_memory_usage_growth, real_peak_memory_usage, real_peak_memory_usage_growth');

        Report::fromArray($array); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_can_return_an_empty_report(): void
    {
        $report = Report::none();

        foreach ($report->toArray() as $key => $stat) {
            self::assertSame(0, $stat['iterations'], "Failed asserting count is 0 for $key");
            self::assertArrayHasKey('average', $stat);
        }
    }
}
