<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    #[Test]
    public function it_can_generate_a_valid_json_representation(): void
    {
        $environment = Environment::current()->jsonSerialize();

        self::assertArrayHasKey('os', $environment);
        self::assertArrayHasKey('osFamily', $environment);
        self::assertArrayHasKey('phpArchitecture', $environment);
    }
}
