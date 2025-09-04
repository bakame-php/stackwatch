<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;
use Throwable;

use function array_diff_key;
use function array_keys;
use function header;
use function headers_sent;
use function implode;
use function ob_get_clean;
use function ob_start;

/**
 * @phpstan-import-type StatsMap from Statistics
 * @phpstan-import-type StatsHumanReadable from Statistics
 * @phpstan-import-type MetricsHumanReadable from Metrics
 * @phpstan-type ReportMap array{
 *      cpu_time: StatsMap,
 *      execution_time: StatsMap,
 *      memory_usage: StatsMap,
 *      memory_usage_growth: StatsMap,
 *      real_memory_usage: StatsMap,
 *      real_memory_usage_growth: StatsMap,
 *      peak_memory_usage: StatsMap,
 *      peak_memory_usage_growth: StatsMap,
 *      real_peak_memory_usage: StatsMap,
 *      real_peak_memory_usage_growth: StatsMap,
 *  }
 * @phpstan-type ReportHumanReadable array{
 *       cpu_time: StatsHumanReadable,
 *       execution_time: StatsHumanReadable,
 *       memory_usage: StatsHumanReadable,
 *       memory_usage_growth: StatsHumanReadable,
 *       real_memory_usage: StatsHumanReadable,
 *       real_memory_usage_growth: StatsHumanReadable,
 *       peak_memory_usage: StatsHumanReadable,
 *       peak_memory_usage_growth: StatsHumanReadable,
 *       real_peak_memory_usage: StatsHumanReadable,
 *       real_peak_memory_usage_growth: StatsHumanReadable,
 *  }
 */
final class Report implements JsonSerializable
{
    public function __construct(
        public readonly Statistics $cpuTime,
        public readonly Statistics $executionTime,
        public readonly Statistics $memoryUsage,
        public readonly Statistics $memoryUsageGrowth,
        public readonly Statistics $peakMemoryUsage,
        public readonly Statistics $peakMemoryUsageGrowth,
        public readonly Statistics $realMemoryUsage,
        public readonly Statistics $realMemoryUsageGrowth,
        public readonly Statistics $realPeakMemoryUsage,
        public readonly Statistics $realPeakMemoryUsageGrowth,
    ) {
        Unit::Nanoseconds === $this->cpuTime->unit || throw new InvalidArgument('Invalid cpu time unit specified');
        Unit::Nanoseconds === $this->executionTime->unit || throw new InvalidArgument('Invalid execution time unit specified');
        Unit::Bytes === $this->memoryUsage->unit || throw new InvalidArgument('Invalid memory usage unit specified');
        Unit::Bytes === $this->memoryUsageGrowth->unit || throw new InvalidArgument('Invalid memory usage unit specified');
        Unit::Bytes === $this->peakMemoryUsage->unit || throw new InvalidArgument('Invalid peak memory usage unit specified');
        Unit::Bytes === $this->peakMemoryUsageGrowth->unit || throw new InvalidArgument('Invalid memory usage unit specified');
        Unit::Bytes === $this->realMemoryUsage->unit || throw new InvalidArgument('Invalid real memory usage unit specified');
        Unit::Bytes === $this->realMemoryUsageGrowth->unit || throw new InvalidArgument('Invalid real memory usage unit specified');
        Unit::Bytes === $this->realPeakMemoryUsage->unit || throw new InvalidArgument('Invalid real peak memory usage unit specified');
        Unit::Bytes === $this->realPeakMemoryUsageGrowth->unit || throw new InvalidArgument('Invalid real peak memory usage unit specified');
    }

    /**
     * @return array{
     *     cpu_time: Statistics,
     *     execution_time: Statistics,
     *     memory_usage: Statistics,
     *     memory_usage_growth: Statistics,
     *     real_memory_usage: Statistics,
     *     real_memory_usage_growth: Statistics,
     *     peak_memory_usage: Statistics,
     *     peak_memory_usage_growth: Statistics,
     *     real_peak_memory_usage: Statistics,
     *     real_peak_memory_usage_growth: Statistics,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'cpu_time' => $this->cpuTime,
            'execution_time' => $this->executionTime,
            'memory_usage' => $this->memoryUsage,
            'memory_usage_growth' => $this->memoryUsageGrowth,
            'real_memory_usage' => $this->realMemoryUsage,
            'real_memory_usage_growth' => $this->realMemoryUsageGrowth,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'peak_memory_usage_growth' => $this->peakMemoryUsageGrowth,
            'real_peak_memory_usage' => $this->realPeakMemoryUsage,
            'real_peak_memory_usage_growth' => $this->realPeakMemoryUsageGrowth,
        ];
    }

    /**
     * @return ReportMap
     */
    public function toArray(): array
    {
        return [
            'cpu_time' => $this->cpuTime->toArray(),
            'execution_time' => $this->executionTime->toArray(),
            'memory_usage' => $this->memoryUsage->toArray(),
            'memory_usage_growth' => $this->memoryUsageGrowth->toArray(),
            'real_memory_usage' => $this->realMemoryUsage->toArray(),
            'real_memory_usage_growth' => $this->realMemoryUsageGrowth->toArray(),
            'peak_memory_usage' => $this->peakMemoryUsage->toArray(),
            'peak_memory_usage_growth' => $this->peakMemoryUsageGrowth->toArray(),
            'real_peak_memory_usage' => $this->realPeakMemoryUsage->toArray(),
            'real_peak_memory_usage_growth' => $this->realPeakMemoryUsageGrowth->toArray(),
        ];
    }

    public static function fromMetrics(Timeline|SpanAggregator|Span|Metrics ...$metrics): self
    {
        $statistics = [
            'cpuTime' => [],
            'executionTime' => [],
            'memoryUsage' => [],
            'memoryUsageGrowth' => [],
            'peakMemoryUsage' => [],
            'peakMemoryUsageGrowth' => [],
            'realMemoryUsage' => [],
            'realMemoryUsageGrowth' => [],
            'realPeakMemoryUsage' => [],
            'realPeakMemoryUsageGrowth' => [],
        ];

        foreach (Metrics::yieldFrom(...$metrics) as $metric) {
            $statistics['cpuTime'][] = $metric->cpuTime;
            $statistics['executionTime'][] = $metric->executionTime;
            $statistics['memoryUsage'][] = $metric->memoryUsage;
            $statistics['memoryUsageGrowth'][] = $metric->memoryUsageGrowth;
            $statistics['peakMemoryUsage'][] = $metric->peakMemoryUsage;
            $statistics['peakMemoryUsageGrowth'][] = $metric->peakMemoryUsageGrowth;
            $statistics['realMemoryUsage'][] = $metric->realMemoryUsage;
            $statistics['realMemoryUsageGrowth'][] = $metric->realMemoryUsageGrowth;
            $statistics['realPeakMemoryUsage'][] = $metric->realPeakMemoryUsage;
            $statistics['realPeakMemoryUsageGrowth'][] = $metric->realPeakMemoryUsageGrowth;
        }

        return new self(
            cpuTime: Statistics::fromValues(Unit::Nanoseconds, $statistics['cpuTime']),
            executionTime: Statistics::fromValues(Unit::Nanoseconds, $statistics['executionTime']),
            memoryUsage: Statistics::fromValues(Unit::Bytes, $statistics['memoryUsage']),
            memoryUsageGrowth: Statistics::fromValues(Unit::Bytes, $statistics['memoryUsageGrowth']),
            peakMemoryUsage: Statistics::fromValues(Unit::Bytes, $statistics['peakMemoryUsage']),
            peakMemoryUsageGrowth: Statistics::fromValues(Unit::Bytes, $statistics['peakMemoryUsageGrowth']),
            realMemoryUsage: Statistics::fromValues(Unit::Bytes, $statistics['realMemoryUsage']),
            realMemoryUsageGrowth: Statistics::fromValues(Unit::Bytes, $statistics['realMemoryUsageGrowth']),
            realPeakMemoryUsage: Statistics::fromValues(Unit::Bytes, $statistics['realPeakMemoryUsage']),
            realPeakMemoryUsageGrowth: Statistics::fromValues(Unit::Bytes, $statistics['realPeakMemoryUsageGrowth']),
        );
    }

    public static function none(): self
    {
        $nanoStats = Statistics::none(Unit::Nanoseconds);
        $byteStats = Statistics::none(Unit::Bytes);

        return new self(
            cpuTime: $nanoStats,
            executionTime: $nanoStats,
            memoryUsage: $byteStats,
            memoryUsageGrowth: $byteStats,
            peakMemoryUsage: $byteStats,
            peakMemoryUsageGrowth: $byteStats,
            realMemoryUsage: $byteStats,
            realMemoryUsageGrowth: $byteStats,
            realPeakMemoryUsage: $byteStats,
            realPeakMemoryUsageGrowth: $byteStats,
        );
    }

    /**
     * @param ReportMap $data
     *
     * @throws InvalidArgument
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key([
            'cpu_time' => 1,
            'execution_time' => 1,
            'memory_usage' => 1,
            'memory_usage_growth' => 1,
            'real_memory_usage' => 1,
            'real_memory_usage_growth' => 1,
            'peak_memory_usage' => 1,
            'peak_memory_usage_growth' => 1,
            'real_peak_memory_usage' => 1,
            'real_peak_memory_usage_growth' => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        try {
            return new self(
                cpuTime: Statistics::fromArray($data['cpu_time']),
                executionTime: Statistics::fromArray($data['execution_time']),
                memoryUsage: Statistics::fromArray($data['memory_usage']),
                memoryUsageGrowth: Statistics::fromArray($data['memory_usage_growth']),
                peakMemoryUsage: Statistics::fromArray($data['peak_memory_usage']),
                peakMemoryUsageGrowth: Statistics::fromArray($data['peak_memory_usage_growth']),
                realMemoryUsage: Statistics::fromArray($data['real_memory_usage']),
                realMemoryUsageGrowth: Statistics::fromArray($data['real_memory_usage_growth']),
                realPeakMemoryUsage: Statistics::fromArray($data['real_peak_memory_usage']),
                realPeakMemoryUsageGrowth: Statistics::fromArray($data['real_peak_memory_usage_growth']),
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a report from the payload', previous: $exception);
        }
    }

    /**
     * @return ReportHumanReadable
     */
    public function toHuman(): array
    {
        return [
            'cpu_time' => $this->cpuTime->toHuman(),
            'execution_time' => $this->executionTime->toHuman(),
            'memory_usage' => $this->memoryUsage->toHuman(),
            'memory_usage_growth' => $this->memoryUsageGrowth->toHuman(),
            'real_memory_usage' => $this->realMemoryUsage->toHuman(),
            'real_memory_usage_growth' => $this->realMemoryUsageGrowth->toHuman(),
            'peak_memory_usage' => $this->peakMemoryUsage->toHuman(),
            'peak_memory_usage_growth' => $this->peakMemoryUsageGrowth->toHuman(),
            'real_peak_memory_usage' => $this->realPeakMemoryUsage->toHuman(),
            'real_peak_memory_usage_growth' => $this->realPeakMemoryUsage->toHuman(),
        ];
    }

    /**
     * @throws InvalidArgument if the property is unknown or unsupported
     *
     * @return MetricsHumanReadable
     */
    public function human(string $property): array
    {
        return [
            'cpu_time' => $this->cpuTime->human($property),
            'execution_time' => $this->executionTime->human($property),
            'memory_usage' => $this->memoryUsage->human($property),
            'memory_usage_growth' => $this->memoryUsageGrowth->human($property),
            'real_memory_usage' => $this->realMemoryUsage->human($property),
            'real_memory_usage_growth' => $this->realMemoryUsageGrowth->human($property),
            'peak_memory_usage' => $this->peakMemoryUsage->human($property),
            'peak_memory_usage_growth' => $this->peakMemoryUsageGrowth->human($property),
            'real_peak_memory_usage' => $this->realPeakMemoryUsage->human($property),
            'real_peak_memory_usage_growth' => $this->realPeakMemoryUsage->human($property),
        ];
    }

    public function dump(): self
    {
        (new Renderer())->renderReport($this);

        return $this;
    }

    public function dd(): never
    {
        ob_start();
        self::dump();
        $dumpOutput = ob_get_clean();

        if (Environment::current()->isCli()) {
            echo $dumpOutput;
            exit(1);
        }

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $dumpOutput;
        exit(1);
    }
}
