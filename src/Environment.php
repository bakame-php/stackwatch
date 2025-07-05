<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;

use function defined;
use function filter_var;
use function getenv;
use function gethostname;
use function ini_get;
use function max;
use function php_sapi_name;
use function php_uname;
use function phpversion;
use function restore_error_handler;
use function set_error_handler;
use function shell_exec;
use function stripos;
use function strncasecmp;
use function trim;

use const FILTER_VALIDATE_INT;
use const PHP_INT_SIZE;
use const PHP_OS;
use const PHP_OS_FAMILY;

/**
 * @phpstan-type EnvironmentStats array{
 *     os: string,
 *     osFamily: string,
 *     hostname: string,
 *     machine: string,
 *     phpIntSize: int,
 *     phpArchitecture: string,
 *     phpVersion: string,
 *     sapi: string,
 *     cpuCores: int,
 *     memoryLimit: float|string,
 *     totalDisk: float,
 *     freeDisk: float,
 * }
 */
final class Environment implements JsonSerializable
{
    /**
     * @param float $memoryLimit expressed in bytes
     * @param float $totalDisk expressed in bytes
     * @param float $freeDisk expressed in bytes
     */
    public function __construct(
        public readonly string $os,
        public readonly string $osFamily,
        public readonly string $hostname,
        public readonly string $machine,
        public readonly int $phpIntSize,
        public readonly string $phpArchitecture,
        public readonly string $phpVersion,
        public readonly string $sapi,
        public readonly float|string $memoryLimit,
        public readonly int $cpuCores,
        public readonly float $totalDisk,
        public readonly float $freeDisk,
    ) {
    }

    public static function current(): self
    {
        /** @var float|false $totalDisk */
        $totalDisk = self::cloak(disk_total_space(...), '/');
        if (false === $totalDisk) {
            $totalDisk = 0;
        }

        /** @var float|false $freeDisk */
        $freeDisk = self::cloak(disk_free_space(...), '/');
        if (false === $freeDisk) {
            $freeDisk = 0;
        }

        $hostname = gethostname();
        if (false === $hostname) {
            $hostname = php_uname('n');
        }

        return new self(
            os:PHP_OS,
            osFamily: defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : 'Unknown',
            hostname: $hostname,
            machine: php_uname('m'),
            phpIntSize: PHP_INT_SIZE,
            phpArchitecture: (PHP_INT_SIZE * 8).'-bit',
            phpVersion: phpversion(),
            sapi: php_sapi_name(),
            memoryLimit: self::detectMemoryLimit(),
            cpuCores:  self::detectCpuCores(),
            totalDisk: $totalDisk,
            freeDisk: $freeDisk,
        );
    }

    public static function detectMemoryLimit(): int|string
    {
        $iniValue = ini_get('memory_limit');
        if (false === $iniValue) {
            return '';
        }

        if ('-1' === $iniValue) {
            return -1;
        }

        return MemoryUnit::tryParse($iniValue) ?? '';
    }

    public static function detectCpuCores(): int
    {
        $shellExec = static fn (string $cmd): string => trim((string) shell_exec($cmd));
        if (0 === strncasecmp(PHP_OS, 'WIN', 3)) {
            $cores = getenv('NUMBER_OF_PROCESSORS');

            return max(1, filter_var($cores, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]));
        }

        /** @var bool $isReadable */
        $isReadable = self::cloak(is_readable(...), '/proc/cpuinfo');
        if ($isReadable) {
            $cores = self::cloak($shellExec, 'nproc');

            return max(1, filter_var($cores, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]));
        }

        if (0 === stripos(PHP_OS, 'Darwin')) {
            $cores =  self::cloak($shellExec, 'sysctl -n hw.ncpu');

            return max(1, filter_var($cores, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]));
        }

        return 1;
    }

    /**
     * Returns true if PHP is running in 64-bit mode.
     */
    public function is64Bit(): bool
    {
        return 8 === $this->phpIntSize;
    }

    /**
     * Returns true if PHP is running in 32-bit mode.
     */
    public function is32Bit(): bool
    {
        return 4 === $this->phpIntSize;
    }

    /**
     * @return EnvironmentStats
     */
    public function stats(): array
    {
        return [
            'os' => $this->os,
            'osFamily' => $this->osFamily,
            'hostname' => $this->hostname,
            'machine' => $this->machine,
            'phpIntSize' => $this->phpIntSize,
            'phpArchitecture' => $this->phpArchitecture,
            'phpVersion' => $this->phpVersion,
            'sapi' => $this->sapi,
            'cpuCores' => $this->cpuCores,
            'memoryLimit' => $this->memoryLimit,
            'totalDisk' => $this->totalDisk,
            'freeDisk' => $this->freeDisk,
        ];
    }

    /**
     * @return EnvironmentStats
     */
    public function jsonSerialize(): array
    {
        return $this->stats();
    }

    /**
     * Calls a callback suppressing PHP warnings/notices during its execution.
     *
     * @param callable $callback The function to call.
     * @param mixed ...$args Arguments to pass to the callback.
     */
    private static function cloak(callable $callback, mixed ...$args): mixed
    {
        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        $result = $callback(...$args);
        restore_error_handler();

        return $result;
    }
}
