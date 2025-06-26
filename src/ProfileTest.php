<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * @phpstan-import-type ProfileMetrics from Profile
 */
#[CoversClass(Profile::class)]
final class ProfileTest extends TestCase
{
    #[Test]
    public function it_has_a_specific_lifecycle(): void
    {
        $profile = new Profile('test');

        self::assertTrue($profile->hasNotBegun());
        self::assertFalse($profile->hasBegun());
        self::assertFalse($profile->hasEnded());
        self::assertFalse($profile->isRunning());

        $profile->beginProfiling();

        self::assertFalse($profile->hasNotBegun());
        self::assertTrue($profile->hasBegun());
        self::assertFalse($profile->hasEnded());
        self::assertTrue($profile->isRunning());

        usleep(1000);

        $profile->endProfiling();

        self::assertTrue($profile->hasEnded());
        self::assertFalse($profile->isRunning());

        self::assertSame('test', $profile->label());
        self::assertGreaterThan(0, $profile->executionTime());
        self::assertGreaterThanOrEqual(0, $profile->cpuTime());
        self::assertGreaterThanOrEqual(0, $profile->memoryUsage());
        self::assertGreaterThanOrEqual(0, $profile->realMemoryUsage());
        self::assertGreaterThanOrEqual(0, $profile->peakMemoryUsage());
        self::assertGreaterThanOrEqual(0, $profile->realPeakMemoryUsage());
    }

    #[Test]
    public function it_will_returns_metrics_in_an_associative_array(): void
    {
        $profile = new Profile('metrics');
        $profile->beginProfiling();
        usleep(1000);
        $profile->endProfiling();

        $metrics = $profile->metrics();

        self::assertArrayHasKey('label', $metrics);
        self::assertArrayHasKey('start', $metrics);
        self::assertArrayHasKey('end', $metrics);
        self::assertArrayHasKey('metrics', $metrics);

        self::assertIsArray($metrics['metrics']);
        self::assertArrayHasKey('cpu_time', $metrics['metrics']);
        self::assertArrayHasKey('memory_usage', $metrics['metrics']);
    }

    #[Test]
    public function it_can_be_json_encoded(): void
    {
        $profile = new Profile('serialize');
        $profile->beginProfiling();
        usleep(1000);
        $profile->endProfiling();

        /** @var string $json */
        $json = json_encode($profile);
        self::assertJson($json);

        /** @var ProfileMetrics $decoded */
        $decoded = json_decode($json, true);
        self::assertArrayHasKey('label', $decoded);
    }

    #[Test]
    public function it_will_reject_invalid_label(): void
    {
        $this->expectException(ValueError::class);

        new Profile('_123invalid');
    }

    #[Test]
    public function it_will_fail_if_you_try_to_start_twice_profiling(): void
    {
        $this->expectException(LogicException::class);

        $profile = new Profile('double_start');
        $profile->beginProfiling();
        $profile->beginProfiling();
    }

    #[Test]
    public function it_will_fail_if_you_try_to_end_a_profiling_without_starting_it(): void
    {
        $this->expectException(LogicException::class);

        $profile = new Profile('no_start');
        $profile->endProfiling(); // should throw
    }

    #[Test]
    public function it_will_throw_if_you_try_to_access_a_property_before_profiling_has_ended(): void
    {
        $this->expectException(LogicException::class);

        $profile = new Profile('too_soon');
        $profile->beginProfiling();
        $profile->executionTime();
    }
}
