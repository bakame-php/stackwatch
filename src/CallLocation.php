<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Exporter\Ide;
use JsonSerializable;
use Throwable;

use function count;
use function debug_backtrace;
use function preg_match;
use function preg_quote;
use function str_replace;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * @phpstan-type CallLocationMap array{path: ?string,line: ?int}
 */
final class CallLocation implements JsonSerializable
{
    private const BACKTRACE_LIMIT = 4;

    public function __construct(
        public readonly ?string $path = null,
        public readonly ?int $line = null,
    ) {
        if ((null === $this->path) !== (null === $this->line)) {
            throw new InvalidArgument('Both "path" and "line" must be provided together if any is set.');
        }
    }

    /**
     * Return the **last frame inside the namespace** that led to this call.
     *
     * @param string $namespace The namespace to track
     * @param list<non-empty-string> $skipFilePatterns List of filename patterns to ignore (supports '*' wildcard)
     */
    public static function fromLastInternalCall(string $namespace, array $skipFilePatterns = []): self
    {
        $namespace = trim($namespace, '\\');
        $lastFrame = null;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::BACKTRACE_LIMIT) as $frame) {
            $class = $frame['class'] ?? '';
            if (!isset($frame['file'], $frame['line'])) {
                continue;
            }

            if ('' !== $class && !str_starts_with($class, $namespace)) {
                break;
            }

            foreach ($skipFilePatterns as $pattern) {
                if (1 === preg_match(
                    '/'.str_replace('*', '.*', preg_quote($pattern, '/')).'$/',
                    $frame['file']
                )) {
                    return new self(path: $frame['file'], line: $frame['line']);
                }
            }

            $lastFrame = $frame;
        }

        return null === $lastFrame ? new self() : new self(path: $lastFrame['file'], line: $lastFrame['line']);
    }

    /**
     * Returns a new instance from a specified frame in the debug backtrace.
     * @param int $step 0 = exception origin (default).
     *                  Positive = offset into trace frames (1 = first trace frame).
     *                  Negative = count from the deepest frame (-1 = last).
     *
     * @param int<0, max> $limit limit the number of stack frames returned. By default (limit=0) it returns all stack frames.
     */
    public static function fromDebugBackTrace(int $step = 0, int $limit = 0): self
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);

        if (0 === $step) {
            $frame = $trace[0] ?? [];

            return new self(path: $frame['file'] ?? null, line: $frame['line'] ?? null);
        }

        if (0 > $step) {
            $step += count($trace);
        }

        $frame = $trace[$step] ?? [];

        return new self(path: $frame['file'] ?? null, line: $frame['line'] ?? null);
    }

    /**
     * Returns a new instance from an Exception/Throwable trace.
     *
     * @param int $step 0 = exception origin (default).
     *                  Positive = offset into trace frames (1 = first trace frame).
     *                  Negative = count from the deepest frame (-1 = last).
     */
    public static function fromExceptionTrace(Throwable $exception, int $step = 0): self
    {
        if (0 === $step) {
            return new self(path: $exception->getFile(), line: $exception->getLine());
        }

        $trace = $exception->getTrace();
        if (0 > $step) {
            $step += count($trace);
        }

        $frame = $trace[$step] ?? [];

        return new self(path: $frame['file'] ?? null, line: $frame['line'] ?? null);
    }

    /**
     * @param CallLocationMap|array{} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(path: $data['path'] ?? null, line: $data['line'] ?? null);
    }

    /**
     * @return CallLocationMap
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'line' => $this->line,
        ];
    }

    public function toConsoleString(Ide|string|null $default = null): string
    {
        if ($default instanceof Ide) {
            return $default->path($this);
        }

        return Ide::fromEnv($default)->path($this);
    }

    public function toBrowserString(Ide|string|null $default = null): string
    {
        if ($default instanceof Ide) {
            return $default->uri($this);
        }

        return Ide::fromEnv()->uri($this);
    }

    /**
     * @return CallLocationMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function equals(mixed $value): bool
    {
        return $value instanceof CallLocation
            && $value->path === $this->path
            && $value->line === $this->line;
    }

    public function isSameFile(mixed $value): bool
    {
        return $value instanceof CallLocation
            && $value->path === $this->path;
    }

    public function isCrossFile(mixed $value): bool
    {
        return $value instanceof CallLocation
            && $value->path !== $this->path;
    }

    public function isEmpty(): bool
    {
        return null === $this->path
            && null === $this->line;
    }

    public function isComplete(): bool
    {
        return null !== $this->path
            && null !== $this->line;
    }
}
