<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function json_encode;

#[CoversClass(Profiler::class)]
#[CoversClass(ProfilingResult::class)]
final class ProfilerTest extends TestCase
{
    #[Test]
    public function it_can_be_invoked_and_return_the_result(): void
    {
        $profiler = new Profiler(fn () => 'result');
        $result = $profiler();

        self::assertSame('result', $result);
        self::assertCount(1, $profiler);

        $profile = $profiler->lastProfile();
        self::assertInstanceOf(Profile::class, $profile);
        self::assertTrue($profile->hasEnded());
    }

    #[Test]
    public function it_can_store_multiple_profiles(): void
    {
        $profiler = new Profiler(fn ($x) => $x * 2);
        $profiler(2);
        $profiler(3);

        self::assertCount(2, $profiler);

        $values =  array_map(fn (Profile $p) => $p->executionTime(), $profiler->profiles());
        if (method_exists(self::class, 'assertContainsOnlyFloat')) { /* @phpstan-ignore-line */
            self::assertContainsOnlyFloat($values);
        } else {
            self::assertContainsOnly('float', $values);  /* @phpstan-ignore-line */
        }
    }

    #[Test]
    public function it_can_use_labels(): void
    {
        $profiler = new Profiler(fn () => 42);
        $profiler->runWithLabel('custom_label');

        $profile = $profiler->lastProfile();
        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('custom_label', $profile->label());
    }

    #[Test]
    public function it_can_rest_its_profiles(): void
    {
        $profiler = new Profiler(fn () => null);

        $profiler();
        self::assertNotEmpty($profiler->profiles());

        $profiler->reset();
        self::assertEmpty($profiler->profiles());
    }

    #[Test]
    public function it_can_be_converted_to_a_json_string(): void
    {
        $profiler = new Profiler(fn () => null);
        $profiler();
        /** @var string $json */
        $json = json_encode($profiler);
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertArrayHasKey('label', $data[0]);
        self::assertArrayHasKey('metrics', $data[0]);
    }
}
