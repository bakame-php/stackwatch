<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    private Environment $env;

    protected function setUp(): void
    {
        // Fake values so we control the output
        $this->env = new Environment(
            os: 'Linux',
            osFamily: 'Unix',
            hostname: 'github-runner',
            machine: 'x86_64',
            phpIntSize: 8,
            phpArchitecture: '64bit',
            phpVersion: '8.3.24',
            sapi: 'cli',
            memoryLimit: 1024 * 1024 * 1024, // 1 GB
            rawMemoryLimit: '1G',
            cpuCores: 4,
            totalDisk: 500 * 1024 * 1024 * 1024, // 500 GB
            freeDisk: 100 * 1024 * 1024 * 1024   // 100 GB
        );
    }

    #[Test]
    public function it_can_generate_a_valid_json_representation(): void
    {
        $environment = Environment::current()->jsonSerialize();

        self::assertArrayHasKey('os', $environment);
        self::assertArrayHasKey('os_family', $environment);
        self::assertArrayHasKey('php_architecture', $environment);
    }

    #[Test]
    public function it_retuns_the_array_in_full_with_for_human_without_property_given(): void
    {
        $result = $this->env->toHuman();

        self::assertIsArray($result);
        self::assertArrayHasKey('os', $result);
        self::assertSame('Linux', $result['os']);
        self::assertSame('1 GB', $result['memory_limit']);   // MemoryUnit::format()
        self::assertSame('500 GB', $result['total_disk']);  // MemoryUnit::format()
    }

    #[Test]
    public function it_retuns_a_single_value_with_for_human_with_property_given(): void
    {
        self::assertSame('Linux', $this->env->human('os'));
        self::assertSame('Unix', $this->env->human('os_family'));
        self::assertSame('1 GB', $this->env->human('memory_limit'));
        self::assertSame('500 GB', $this->env->human('total_disk'));
        self::assertSame('500 GB', $this->env->human('totalDisk'));
    }

    #[Test]
    public function it_throws_if_the_property_is_unkown(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Unknown environment name: "foobar"');

        $this->env->human('foobar');
    }
}
