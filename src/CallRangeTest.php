<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallRange::class)]
#[CoversClass(CallLocation::class)]
final class CallRangeTest extends TestCase
{
    public function testFromArrayAndToArray(): void
    {
        $data = [
            'start_path' => '/path/to/start.php',
            'start_line' => 10,
            'end_path' => '/path/to/start.php',
            'end_line' => 20,
            'same_file' => true,
            'line_span' => 11,
            'complete' => true,
            'forward' => true,
        ];

        $range = CallRange::fromArray($data);

        self::assertSame('/path/to/start.php', $range->start->path);
        self::assertSame(10, $range->start->line);
        self::assertSame('/path/to/start.php', $range->end->path);
        self::assertSame(20, $range->end->line);

        $array = $range->toArray();

        self::assertSame($data['start_path'], $array['start_path']);
        self::assertSame($data['start_line'], $array['start_line']);
        self::assertSame($data['end_path'], $array['end_path']);
        self::assertSame($data['end_line'], $array['end_line']);
        self::assertTrue($array['same_file']);
        self::assertSame(11, $array['line_span']);
        self::assertTrue($array['complete']);
        self::assertTrue($array['forward']);
    }

    public function testIsSameFileAndIsCrossFile(): void
    {
        $range = new CallRange(
            new CallLocation('/path/file1.php', 5),
            new CallLocation('/path/file1.php', 15)
        );

        self::assertTrue($range->isSameFile());
        self::assertFalse($range->isCrossFile());

        $range2 = new CallRange(
            new CallLocation('/path/file1.php', 5),
            new CallLocation('/path/file2.php', 15)
        );

        self::assertFalse($range2->isSameFile());
        self::assertTrue($range2->isCrossFile());
    }

    public function testHasStartHasEndAndIsComplete(): void
    {
        $range = new CallRange(
            new CallLocation('/path/file.php', 1),
            new CallLocation('/path/file.php', 10)
        );

        self::assertTrue($range->hasStart());
        self::assertTrue($range->hasEnd());
        self::assertTrue($range->isComplete());

        $emptyRange = new CallRange(
            new CallLocation(null, null),
            new CallLocation(null, null)
        );

        self::assertFalse($emptyRange->hasStart());
        self::assertFalse($emptyRange->hasEnd());
        self::assertFalse($emptyRange->isComplete());
    }

    public function testLineSpanAndIsForward(): void
    {
        $range = new CallRange(
            new CallLocation('/file.php', 5),
            new CallLocation('/file.php', 10)
        );

        self::assertSame(6, $range->lineSpan());
        self::assertTrue($range->isForward());

        $rangeReverse = new CallRange(
            new CallLocation('/file.php', 10),
            new CallLocation('/file.php', 5)
        );

        self::assertSame(6, $rangeReverse->lineSpan());
        self::assertFalse($rangeReverse->isForward());

        $crossFile = new CallRange(
            new CallLocation('/file1.php', 1),
            new CallLocation('/file2.php', 1)
        );

        self::assertNull($crossFile->lineSpan());
        self::assertFalse($crossFile->isForward());
    }

    public function testJsonSerialize(): void
    {
        $range = new CallRange(
            new CallLocation('/file.php', 1),
            new CallLocation('/file.php', 2)
        );

        $json = json_encode($range);
        self::assertNotFalse($json);

        $decoded = json_decode($json, true);
        self::assertSame($range->toArray(), $decoded);
    }

    public function testLengthAndLineSpanForward(): void
    {
        $range = new CallRange(
            new CallLocation('/file.php', 10),
            new CallLocation('/file.php', 12)
        );

        self::assertSame(2, $range->length());       // exclusive
        self::assertSame(3, $range->lineSpan());     // inclusive
        self::assertTrue($range->isForward());
        self::assertFalse($range->isBackward());
    }

    public function testLengthAndLineSpanBackward(): void
    {
        $range = new CallRange(
            new CallLocation('/file.php', 12),
            new CallLocation('/file.php', 10)
        );

        self::assertSame(-2, $range->length());      // exclusive (signed)
        self::assertSame(3, $range->lineSpan());     // inclusive
        self::assertFalse($range->isForward());
        self::assertTrue($range->isBackward());
    }

    public function testLengthAndLineSpanSameLine(): void
    {
        $range = new CallRange(
            new CallLocation('/file.php', 5),
            new CallLocation('/file.php', 5)
        );

        self::assertSame(0, $range->length());
        self::assertSame(1, $range->lineSpan());
        self::assertTrue($range->isForward());
        self::assertFalse($range->isBackward());
    }

    public function testLengthAndLineSpanCrossFile(): void
    {
        $range = new CallRange(
            new CallLocation('/file1.php', 5),
            new CallLocation('/file2.php', 10)
        );

        self::assertNull($range->length());
        self::assertNull($range->lineSpan());
        self::assertFalse($range->isForward());
        self::assertFalse($range->isBackward());
    }
}
