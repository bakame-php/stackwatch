<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[CoversClass(Profiler::class)]
#[CoversClass(ProfilingResult::class)]
/**
 * @phpstan-import-type ProfileMetrics from Profile
 */
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
        $profiler = new Profiler(fn (int $x) => $x * 2);
        $profiler(2);
        $profiler(3);

        self::assertCount(2, $profiler);
        foreach ($profiler as $profile) {
            self::assertIsFloat($profile->executionTime());
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
    public function it_can_reset_its_profiles(): void
    {
        $profiler = new Profiler(fn () => null);
        $profiler();
        self::assertFalse($profiler->isEmpty());

        $profiler->reset();
        self::assertTrue($profiler->isEmpty());
    }

    #[Test]
    public function it_can_be_converted_to_a_json_string(): void
    {
        $profiler = new Profiler(fn () => null);
        $profiler();
        /** @var non-empty-string $json */
        $json = json_encode($profiler);
        /** @var array<ProfileMetrics> $data */
        $data = json_decode($json, true); /** @phpstan-ignore-line */
        /** @var ProfileMetrics $profile */
        $profile = $data[0];  /* @phpstan-ignore-line */

        self::assertIsArray($profile);
        self::assertArrayHasKey('label', $profile);
        self::assertArrayHasKey('metrics', $profile);
    }

    #[Test]
    public function it_can_store_and_retrieve_multiple_profiles(): void
    {
        $profiler = new Profiler(fn (int $x) => $x * 2);
        $profiler->runWithLabel('custom_label', 4);
        $profiler(2);
        $profiler(3);
        $profiler->runWithLabel('custom_label', 5);

        self::assertCount(4, $profiler);
        self::assertCount(2, $profiler->getAll('custom_label'));
        self::assertInstanceOf(Profile::class, $profiler->get('custom_label'));
        self::assertNull($profiler->get('foo_bar'));
        self::assertCount(0, $profiler->getAll('foo_bar'));
        self::assertTrue($profiler->has('custom_label'));
        self::assertFalse($profiler->has('foo_bar'));
        self::assertCount(3, $profiler->labels());
    }
}
