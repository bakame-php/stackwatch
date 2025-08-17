<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;

use function abs;
use function array_diff_key;
use function array_keys;
use function implode;

/**
 * @phpstan-type CallRangeMap array{
 *     start_path: ?string,
 *     start_line: ?int,
 *     end_path: ?string,
 *     end_line: ?int,
 *     same_file: bool,
 *     line_span: ?int,
 *     complete: bool,
 *     forward: bool,
 * }
 */
final class CallRange implements JsonSerializable
{
    public function __construct(
        public readonly CallLocation $start,
        public readonly CallLocation $end,
    ) {
    }

    /**
     * @param CallRangeMap $data
     *
     * @throws InvalidArgument
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key([
            'start_path' => 1,
            'start_line' => 1,
            'end_path' => 1,
            'end_line' => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        return new self(
            new CallLocation($data['start_path'], $data['start_line']),
            new CallLocation($data['end_path'], $data['end_line'])
        );
    }

    public static function fromSnapshots(Snapshot $start, Snapshot $end): self
    {
        return new self(
            new CallLocation($start->originPath, $start->originLine),
            new CallLocation($end->originPath, $end->originLine)
        );
    }

    public function isCrossFile(): bool
    {
        return $this->start->isCrossFile($this->end);
    }

    public function isSameFile(): bool
    {
        return $this->start->isSameFile($this->end);
    }

    public function hasStart(): bool
    {
        return ! $this->start->isEmpty();
    }

    public function hasEnd(): bool
    {
        return ! $this->end->isEmpty();
    }

    public function isComplete(): bool
    {
        return $this->start->isComplete()
            && $this->end->isComplete();
    }

    public function isEmpty(): bool
    {
        return $this->start->isEmpty()
            && $this->end->isEmpty();
    }

    /**
     * Returns the inclusive line span of the range (number of lines covered, including start and end).
     * Returns null if the range is cross-file or either line is undefined.
     */
    public function lineSpan(): ?int
    {
        $length = $this->length();

        return null !== $length ? abs($length) + 1 : $length;
    }

    /**
     * Returns the exclusive length of the range (number of lines between start and end).
     * Returns null if the range is cross-file or either line is undefined.
     */
    public function length(): ?int
    {
        if ($this->isCrossFile()) {
            return null;
        }

        if (null === $this->end->line || null === $this->start->line) {
            return null;
        }

        return $this->end->line - $this->start->line;
    }

    public function isForward(): bool
    {
        return $this->isSameFile()
            && $this->end->line >= $this->start->line;
    }

    public function isBackward(): bool
    {
        return $this->isSameFile()
            && $this->end->line < $this->start->line;
    }

    /**
     * @return CallRangeMap
     */
    public function toArray(): array
    {
        return [
            'start_path' => $this->start->path,
            'start_line' => $this->start->line,
            'end_path' => $this->end->path,
            'end_line' => $this->end->line,
            'same_file' => $this->isSameFile(),
            'line_span' => $this->lineSpan(),
            'complete' => $this->isComplete(),
            'forward' => $this->isForward(),
        ];
    }

    /**
     * @return CallRangeMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
