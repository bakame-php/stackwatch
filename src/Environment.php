<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

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
 *     memoryLimit: ?int,
 *     rawMemoryLimit: string,
 *     cpuCores: int,
 *     totalDisk: float,
 *     freeDisk: float,
 * }
 */
final class Environment implements JsonSerializable
{
    /**
     * @param float $totalDisk expressed in bytes
     * @param float $freeDisk expressed in bytes
     * @param ?int $memoryLimit Parsed memory limit in bytes.
     *                          `-1` if unlimited, `null` if parsing failed.
     * @param string $rawMemoryLimit Original INI value, e.g., "128M", "-1"
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
        public readonly ?int $memoryLimit,
        public readonly string $rawMemoryLimit,
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
            rawMemoryLimit: ini_get('memory_limit'),
            cpuCores:  self::detectCpuCores(),
            totalDisk: $totalDisk,
            freeDisk: $freeDisk,
        );
    }

    public static function detectMemoryLimit(): ?int
    {
        $iniValue = ini_get('memory_limit');
        if (false === $iniValue) {
            return null;
        }

        if ('-1' === $iniValue) {
            return -1;
        }

        return MemoryUnit::tryParse($iniValue);
    }

    public static function detectCpuCores(): int
    {
        $shellExec = static fn (string $cmd): string => trim((string) shell_exec($cmd));
        if (self::isWindowsPlatform()) {
            return self::getCpuCoresCount(getenv('NUMBER_OF_PROCESSORS'));
        }

        /** @var bool $isReadable */
        $isReadable = self::cloak(is_readable(...), '/proc/cpuinfo');
        if ($isReadable) {
            /** @var string $cores */
            $cores = self::cloak($shellExec, 'nproc');

            return self::getCpuCoresCount($cores);
        }

        if (self::isMacPlatform()) {
            /** @var string $cores */
            $cores = self::cloak($shellExec, 'sysctl -n hw.ncpu');

            return self::getCpuCoresCount($cores);
        }

        return 1;
    }

    private static function isWindowsPlatform(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    private static function isMacPlatform(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    private static function getCpuCoresCount(int|string|float|bool $cores): int
    {
        return max(1, filter_var($cores, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]));
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
     * Returns true if the memory limit is unlimited.
     */
    public function unlimitedMemory(): bool
    {
        return -1 === (int) $this->rawMemoryLimit;
    }

    /**
     * Returns true if the system is running on a Unix Like Operating System.
     */
    public function isWindows(): bool
    {
        return 'Windows' === $this->osFamily;
    }

    /**
     * Returns true if the system is running on a Unix Like Operating System.
     */
    public function isMac(): bool
    {
        return 'Darwin' === $this->osFamily;
    }

    /**
     * Returns true if the system is running on a Unix Like Operating System.
     */
    public function isUnixLike(): bool
    {
        return ! $this->isWindows();
    }

    /**
     *  - `memoryLimit`: int|null — bytes, -1 if unlimited, null if undetected
     *  - `rawMemoryLimit`: string — raw INI string, e.g., "512M" or "-1".
     *
     * @return EnvironmentStats
     */
    public function toArray(): array
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
            'memoryLimit' => $this->memoryLimit,
            'rawMemoryLimit' => $this->rawMemoryLimit,
            'cpuCores' => $this->cpuCores,
            'totalDisk' => $this->totalDisk,
            'freeDisk' => $this->freeDisk,
        ];
    }

    /**
     * @return EnvironmentStats
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
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
