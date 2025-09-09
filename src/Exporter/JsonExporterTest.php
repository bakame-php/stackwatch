<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Exporter;

use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\MetricType;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Result;
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;
use Bakame\Stackwatch\Stack;
use Bakame\Stackwatch\Statistics;
use Bakame\Stackwatch\Timeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use stdClass;
use TypeError;

use function file_get_contents;
use function json_encode;
use function usleep;

#[CoversClass(JsonExporter::class)]
final class JsonExporterTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'json_exporter_test');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpFile);
    }

    #[Test]
    public function it_throws_on_invalid_path_type(): void
    {
        $this->expectException(TypeError::class);

        new JsonExporter(new stdClass(), 0); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_can_export_to_a_file_path(): void
    {
        $data = Snapshot::now();
        $exporter = new JsonExporter($this->tmpFile, 0);
        $exporter->exportSnapshot($data);

        /** @var string $json */
        $json = file_get_contents($this->tmpFile);
        self::assertJson($json);

        /** @var non-empty-string $jsonData */
        $jsonData = json_encode($data);
        self::assertSame($jsonData, $json);
    }

    #[Test]
    public function it_can_export_to_a_stream_resource(): void
    {
        /** @var resource $handle */
        $handle = fopen($this->tmpFile, 'wb');
        $exporter = new JsonExporter($handle, 0);
        $data = Statistics::fromOne(MetricType::CpuTime, 3);
        $exporter->exportStatistics($data);
        fclose($handle);

        /** @var string $json */
        $json = file_get_contents($this->tmpFile);
        self::assertJson($json);

        /** @var non-empty-string $jsonData */
        $jsonData = json_encode($data);
        self::assertSame($jsonData, $json);
    }

    #[Test]
    public function it_can_export_to_a_splfileinfo(): void
    {
        $info = new SplFileInfo($this->tmpFile);
        $exporter = new JsonExporter($info, 0);

        $data = Environment::current();
        $exporter->exportEnvironment($data);

        /** @var string $json */
        $json = file_get_contents($this->tmpFile);
        self::assertJson($json);

        /** @var non-empty-string $jsonData */
        $jsonData = json_encode($data);
        self::assertSame($jsonData, $json);
    }

    #[Test]
    public function it_handles_result_summary_export(): void
    {
        $data = new Span('test', Snapshot::now(), Snapshot::now());
        $result = new Result(42, $data);
        $exporter = new JsonExporter($this->tmpFile, 0);
        $exporter->exportSpan($result);

        /** @var string $json */
        $json = file_get_contents($this->tmpFile);
        self::assertJson($json);

        /** @var non-empty-string $jsonData */
        $jsonData = json_encode($data);
        self::assertSame($jsonData, $json);
    }

    #[Test]
    public function it_exports_all_supported_types(): void
    {
        $exporter = new JsonExporter($this->tmpFile, 0);
        $timeline = Timeline::start('yo');
        usleep(100);
        $timeline->capture('mtv');
        $profiler = new Profiler(function () {
            usleep(100);
        });
        $profiler->run();

        $report = Stack::report(function () {
            usleep(100);
        }, 10);

        $exporter->exportMetrics(Metrics::none());
        $exporter->exportTimeline($timeline);
        $exporter->exportProfiler($profiler);
        $exporter->exportReport($report);

        /** @var string $ndjson */
        $ndjson = file_get_contents($this->tmpFile);

        /** @var non-empty-string $jsonData */
        $jsonData = json_encode($report);

        self::assertStringContainsString($jsonData, $ndjson);
    }
}
