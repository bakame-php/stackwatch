<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\InvalidArgument;
use Symfony\Component\Console\Input\InputInterface;

use function getopt;
use function in_array;
use function is_string;
use function strtolower;
use function trim;

/**
 * @phpstan-type OptionMap array{
 *     path?: string|false,
 *     p?: string|false,
 *     format?: string,
 *     f?: string,
 *     output?: string,
 *     o?: string,
 *     info?: string|false,
 *     i?: string|false,
 *     pretty?: string|false,
 *     P?: string|false,
 *     help?: string|false,
 *     h?: string|false,
 *     version?: string|false,
 *     V?: string|false,
 *     no-recursion?: string|false,
 *     n?: string|false
 * }
 */
final class Input
{
    public const JSON_FORMAT = 'json';
    public const TABLE_FORMAT = 'table';

    public function __construct(
        public readonly ?string $path,
        public readonly bool $showHelp,
        public readonly bool $showInfo,
        public readonly bool $showVersion,
        public readonly bool $recursive,
        public readonly string $format,
        public readonly ?string $output = null,
        public readonly bool $pretty = false,
    ) {
        in_array($this->format, [self::JSON_FORMAT, self::TABLE_FORMAT], true) || throw new InvalidArgument('Output format is not supported');
        null === $this->path || '' !== trim($this->path) || throw new InvalidArgument('path format is not valid');
        ($this->showHelp || $this->showInfo || $this->showVersion || null !== $this->path) || throw new InvalidArgument('Missing required option: --path');
    }

    /**
     * @param OptionMap|InputInterface $input
     */
    public static function fromInput(array|InputInterface $input): self
    {
        return new self(
            path: self::getFirstValue($input, 'path', 'p'),
            showHelp: self::hasFlag($input, 'help', 'h'),
            showInfo: self::hasFlag($input, 'info', 'i'),
            showVersion: self::hasFlag($input, 'version', 'V'),
            recursive: ! self::hasFlag($input, 'no-recursion', 'n'),
            format: self::normalizeFormat(self::getFirstValue($input, 'format', 'f') ?? self::TABLE_FORMAT),
            output: self::getFirstValue($input, 'output', 'o'),
            pretty: self::hasFlag($input, 'pretty', 'P'),
        );
    }

    public static function fromCli(): self
    {
        /** @var OptionMap $options */
        $options = getopt('ihVPp:f:o:', ['path:', 'format:', 'output:', 'info', 'help', 'version', 'pretty']);

        return self::fromInput($options);
    }

    /**
     * @param array<string, string|false>|InputInterface $input
     */
    private static function getFirstValue(array|InputInterface $input, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = is_array($input)
                ? ($input[$key] ?? null)
                : $input->getOption($key);

            if (is_string($value)) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param array<string, string|false>|InputInterface $input
     */
    private static function hasFlag(array|InputInterface $input, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (is_array($input) && array_key_exists($key, $input)) {
                return true;
            }

            if ($input instanceof InputInterface && null !== $input->getOption($key)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        return match ($format) {
            self::JSON_FORMAT => self::JSON_FORMAT,
            self::TABLE_FORMAT => self::TABLE_FORMAT,
            default => throw new InvalidArgument("Unsupported format: $format"),
        };
    }

    public static function usage(): string
    {
        return '--path=PATH [--no-recursion] [--output=OUTPUT] [--format=FORMAT] [--pretty] [--info] [--help] [--version]';
    }

    public static function consoleDescription(): string
    {
        return <<<HELP
<fg=green>  -p, --path=PATH</>       Path to scan for PHP files to profile (required)
<fg=green>  -n, --no-recursion</>    Disable default recursion fo directories (optional)
<fg=green>  -f, --format=FORMAT</>   Output format: 'cli' or 'json' (default: 'cli')
<fg=green>  -o, --output=OUTPUT</>   Path to store the profiling output (optional)
<fg=green>  -P, --pretty</>          Pretty-print the JSON/NDJSON output (json only; optional)
<fg=green>  -i, --info</>            Show additional system/environment information (optional)
<fg=green>  -h, --help</>            Display this help message (optional)
<fg=green>  -V, --version</>         Display the version and exit (optional)
HELP;
    }
}
