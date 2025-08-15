<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;

use function debug_backtrace;
use function preg_quote;
use function str_replace;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * @phpstan-type CallLocationMap array{path: ?string,line: ?int}
 */
final class CallLocation implements JsonSerializable
{
    private const DEBUG_BACKTRACE_LIMIT = 10;

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
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::DEBUG_BACKTRACE_LIMIT) as $frame) {
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
     * @param CallLocationMap $data
     *
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

    public function isEmpty(): bool
    {
        return null === $this->path
            && null === $this->line;
    }
}
