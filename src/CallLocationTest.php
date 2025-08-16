<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallLocation::class)]
#[CoversClass(Snapshot::class)]
final class CallLocationTest extends TestCase
{
    #[Test]
    public function it_returns_an_empty_instance_by_default(): void
    {
        $location = new CallLocation();

        self::assertNull($location->path);
        self::assertNull($location->line);
    }

    #[Test]
    public function it_returns_a_completed_instance_with_both_properties(): void
    {
        $location = new CallLocation('/path/to/file.php', 42);

        self::assertSame('/path/to/file.php', $location->path);
        self::assertSame(42, $location->line);
    }

    #[Test]
    public function it_fails_creating_a_new_instance_without_the_line(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Both "path" and "line" must be provided together if any is set.');

        new CallLocation('/path/to/file.php');
    }

    #[Test]
    public function it_fails_creating_a_new_instance_without_the_path(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Both "path" and "line" must be provided together if any is set.');

        new CallLocation(null, 42);
    }

    #[Test]
    public function it_can_records_nested_calls(): void
    {
        $timeline = Timeline::start('init');
        usleep(1_000);
        $timeline->capture('step');

        $start = $timeline->get('init');
        $step = $timeline->get('step');

        self::assertNotNull($start->originPath);
        self::assertStringContainsString('CallLocationTest.php', $start->originPath);

        self::assertNotNull($step->originPath);
        self::assertStringContainsString('CallLocationTest.php', $step->originPath);

        self::assertIsInt($start->originLine);
        self::assertIsInt($step->originLine);

        self::assertLessThan($step->originLine, $start->originLine);
    }

    public function testIsEmpty(): void
    {
        $location = new CallLocation();

        self::assertTrue($location->isEmpty());
        self::assertFalse($location->isComplete());
    }

    public function testIsComplete(): void
    {
        $location = new CallLocation(path: '/tmp/foo.php', line: 42);

        self::assertFalse($location->isEmpty());
        self::assertTrue($location->isComplete());
    }

    public function testFromArrayEmpty(): void
    {
        self::assertTrue(CallLocation::fromArray([])->isEmpty());
    }

    public function testFromArrayComplete(): void
    {
        self::assertTrue(CallLocation::fromArray(['path' => '/tmp/foo.php', 'line' => 123])->isComplete());
    }

    public function testEquals(): void
    {
        $a = new CallLocation(path: '/tmp/foo.php', line: 10);
        $b = new CallLocation(path: '/tmp/foo.php', line: 10);
        $c = new CallLocation(path: '/tmp/bar.php', line: 20);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals(null));
    }
}
