<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;

use function array_keys;
use function defined;
use function filter_var;
use function getenv;
use function gethostname;
use function implode;
use function in_array;
use function ini_get;
use function is_int;
use function is_readable;
use function max;
use function php_sapi_name;
use function php_uname;
use function phpversion;
use function preg_replace;
use function shell_exec;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_INT;
use const PHP_INT_SIZE;
use const PHP_OS;
use const PHP_OS_FAMILY;

/**
 * @phpstan-type EnvironmentMap array{
 *     os: string,
 *     os_family: string,
 *     hostname: string,
 *     machine: string,
 *     php_int_size: int,
 *     php_architecture: string,
 *     php_version: string,
 *     sapi: string,
 *     memory_limit: ?int,
 *     raw_memory_limit: string,
 *     cpu_cores: int,
 *     total_disk: float,
 *     free_disk: float,
 * }
 * @phpstan-type EnvironmentHumanReadable array{
 *     os: string,
 *     os_family: string,
 *     hostname: string,
 *     machine: string,
 *     php_int_size: string,
 *     php_architecture: string,
 *     php_version: string,
 *     sapi: string,
 *     memory_limit: string,
 *     raw_memory_limit: string,
 *     cpu_cores: string,
 *     total_disk: string,
 *     free_disk: string,
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
        $totalDisk = Warning::cloak(disk_total_space(...), '/');
        if (false === $totalDisk) {
            $totalDisk = 0;
        }

        /** @var float|false $freeDisk */
        $freeDisk = Warning::cloak(disk_free_space(...), '/');
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
        if (self::isWindowsPlatform()) {
            return self::getCpuCoresCount(getenv('NUMBER_OF_PROCESSORS'));
        }

        $shellExec = static fn (string $cmd): string => trim((string) shell_exec($cmd));
        if (is_readable('/proc/cpuinfo')) {
            $cores = $shellExec('nproc');

            return self::getCpuCoresCount($cores);
        }

        if (self::isMacPlatform()) {
            /** @var string $cores */
            $cores = $shellExec('sysctl -n hw.ncpu');

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

    public function isCli(): bool
    {
        return in_array($this->sapi, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     *  - `memoryLimit`: int|null — bytes, -1 if unlimited, null if undetected
     *  - `rawMemoryLimit`: string — raw INI string, e.g., "512M" or "-1".
     *
     * @return EnvironmentMap
     */
    public function toArray(): array
    {
        return [
            'os' => $this->os,
            'os_family' => $this->osFamily,
            'hostname' => $this->hostname,
            'machine' => $this->machine,
            'php_int_size' => $this->phpIntSize,
            'php_architecture' => $this->phpArchitecture,
            'php_version' => $this->phpVersion,
            'sapi' => $this->sapi,
            'memory_limit' => $this->memoryLimit,
            'raw_memory_limit' => $this->rawMemoryLimit,
            'cpu_cores' => $this->cpuCores,
            'total_disk' => $this->totalDisk,
            'free_disk' => $this->freeDisk,
        ];
    }

    /**
     * @return EnvironmentHumanReadable
     */
    public function toHuman(): array
    {
        return [
            'os' => $this->os,
            'os_family' => $this->osFamily,
            'hostname' => $this->hostname,
            'machine' => $this->machine,
            'php_int_size' => (string) $this->phpIntSize,
            'php_architecture' => $this->phpArchitecture,
            'sapi' => $this->sapi,
            'php_version' => $this->phpVersion,
            'memory_limit' => is_int($this->memoryLimit) && ! $this->unlimitedMemory() ? MemoryUnit::format($this->memoryLimit) : (string) $this->memoryLimit,
            'raw_memory_limit' => $this->rawMemoryLimit,
            'cpu_cores' => (string) $this->cpuCores,
            'total_disk' => MemoryUnit::format($this->totalDisk, 0),
            'free_disk' => MemoryUnit::format($this->freeDisk, 0),
        ];
    }

    /**
     * Returns a human-readable version of a property.
     *
     * @param non-empty-string $property
     *
     * @throws InvalidArgument if the property is unknown
     */
    public function human(string $property): string
    {
        $humans = $this->toHuman();
        $propertyNormalized = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
        return $humans[$propertyNormalized] ?? throw new InvalidArgument('Unknown environment name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
    }

    /**
     * @return EnvironmentMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function dump(): self
    {
        (new ViewExporter())->exportEnvironment($this);

        return $this;
    }

    public function dd(): never
    {
        CallbackDumper::dd($this->dump(...));
    }
}
