<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use ErrorException;
use JsonException;
use RuntimeException;
use SplFileInfo;
use SplFileObject;
use TypeError;

use function is_resource;
use function is_string;
use function json_encode;
use function stream_get_meta_data;

use const JSON_THROW_ON_ERROR;

final class JsonExporter implements Exporter
{
    /** @var resource|SplFileObject */
    private mixed $path;

    /**
     * @param SplFileInfo|resource|string $path
     * @param ?resource $context
     */
    public function __construct(mixed $path, private readonly int $jsonOptions = 0, $context = null)
    {
        $this->path = match (true) {
            is_string($path) => new SplFileObject($path, 'wb', context: $context),
            $path instanceof SplFileObject => $path,
            $path instanceof SplFileInfo => $path->openFile('wb', context: $context),
            is_resource($path) => $path,
            default => throw new TypeError('The destination path must be a filename, a stream or a SplFileInfo object.'),
        };
    }

    public function write(mixed $data): int
    {
        return $this->writeJson($data, appendNewline: false);
    }

    public function writeln(mixed $data): int
    {
        return $this->writeJson($data, appendNewline: true);
    }

    /**
     * @throws JsonException|RuntimeException|ErrorException
     */
    private function writeJson(mixed $data, bool $appendNewline): int
    {
        $stream = match (true) {
            $this->path instanceof SplFileObject => $this->path,
            default => new class ($this->path) {
                /** @var resource */
                private $resource;

                /**
                 * @param resource $resource
                 */
                public function __construct($resource)
                {
                    $this->resource = $resource;
                }

                /**
                 *
                 * @throws ErrorException If the resource is invalid
                 *
                 */
                public function fwrite(string $data): int|false
                {
                    /** @var int|false $bytes */
                    $bytes = Warning::trap(fwrite(...), $this->resource, $data);

                    return $bytes;
                }

                public function getPathname(): string
                {
                    return stream_get_meta_data($this->resource)['uri'];
                }
            },
        };

        $json = json_encode($data, JSON_THROW_ON_ERROR | $this->jsonOptions);
        $json .= $appendNewline ? "\n" : '';

        $bytes = $stream->fwrite($json);
        false !== $bytes || throw new RuntimeException('Unable to write to the destination path: `'.$stream->getPathname().'`');

        return $bytes;
    }

    public function exportSnapshot(Snapshot $snapshot): void
    {
        $this->write($snapshot);
    }

    public function exportMetrics(Metrics $metrics): void
    {
        $this->write($metrics);
    }

    public function exportSpan(Result|Span $span): void
    {
        if ($span instanceof Result) {
            $span = $span->span;
        }

        $this->write($span);
    }

    /**
     * @param (callable(Span): bool)|string|null $label
     */
    public function exportProfiler(Profiler $profiler, callable|string|null $label = null): void
    {
        $this->write(match (true) {
            null === $label => $profiler,
            is_callable($label) => [
                'identifier' => $profiler->identifier(),
                'spans' => $profiler->filter($label),
            ],
            default => [
                'identifier' => $profiler->identifier(),
                'spans' => $profiler->getAll($label),
            ],
        });
    }

    /**
     * @param ?callable(Snapshot): bool $filter
     */
    public function exportTimeline(Timeline $timeline, ?callable $filter = null): void
    {
        $this->write(match (true) {
            null === $filter => $timeline,
            is_callable($filter) => [
                'identifier' => $timeline->identifier(),
                'snapshots' => $timeline->filter($filter),
            ],
        });
    }

    public function exportReport(Report $report): void
    {
        $this->write($report);
    }

    public function exportEnvironment(Environment $environment): void
    {
        $this->write($environment);
    }

    public function exportStatistics(Statistics $statistics, string $name): void
    {
        $this->write([...['label' => $name], ...$statistics->toArray()]);
    }
}
