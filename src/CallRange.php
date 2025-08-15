<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;

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
        return ! $this->isSameFile();
    }

    public function isSameFile(): bool
    {
        return null !== $this->start->path
            && null !== $this->end->path
            && $this->start->path === $this->end->path;
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
        return $this->hasStart() && $this->hasEnd();
    }

    public function lineSpan(): ?int
    {
        if ($this->isCrossFile()) {
            return null;
        }

        if (null === $this->end->line || null === $this->start->line) {
            return null;
        }

        return abs($this->end->line - $this->start->line) + 1;
    }

    public function isForward(): bool
    {
        return $this->isSameFile()
            && $this->end->line >= $this->start->line;
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
