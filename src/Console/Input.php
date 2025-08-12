<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\InvalidArgument;
use Symfony\Component\Console\Input\InputInterface;

use function filter_var;
use function getopt;
use function in_array;
use function is_string;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_INT;

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
 *     isolation?: string|false,
 *     x?: string|false,
 *     depth?: string|false,
 *     d?: string|false
 * }
 */
final class Input
{
    public const JSON_FORMAT = 'json';
    public const TABLE_FORMAT = 'table';

    public function __construct(
        public readonly ?string $path,
        public readonly bool $showHelp = false,
        public readonly bool $showInfo = false,
        public readonly bool $showVersion = false,
        public readonly string $format = self::TABLE_FORMAT,
        public readonly ?string $output = null,
        public readonly bool $pretty = false,
        public readonly bool $inIsolation = false,
        public readonly int $depth = -1,
    ) {
        in_array($this->format, [self::JSON_FORMAT, self::TABLE_FORMAT], true) || throw new InvalidArgument('Output format is not supported');
        null === $this->path || '' !== trim($this->path) || throw new InvalidArgument('path format is not valid');
        ($this->showHelp || $this->showInfo || $this->showVersion || null !== $this->path) || throw new InvalidArgument('Missing required option: --path');
        -1 <= $this->depth || throw new InvalidArgument('depth option must be greater or equal to -1.');
    }

    /**
     * @param OptionMap|InputInterface $input
     */
    public static function fromInput(array|InputInterface $input): self
    {
        $depth = self::resolveDepth($input);

        return new self(
            path: self::getFirstValue($input, 'path', 'p'),
            showHelp: self::hasFlag($input, 'help', 'h'),
            showInfo: self::hasFlag($input, 'info', 'i'),
            showVersion: self::hasFlag($input, 'version', 'V'),
            format: self::normalizeFormat(self::getFirstValue($input, 'format', 'f') ?? self::TABLE_FORMAT),
            output: self::getFirstValue($input, 'output', 'o'),
            pretty: self::hasFlag($input, 'pretty', 'P'),
            inIsolation: self::hasFlag($input, 'isolation', 'x'),
            depth: $depth,
        );
    }

    /**
     * @param OptionMap|InputInterface $input
     *
     * @throws InvalidArgument If both --no-recursion and --depth are set.
     */
    private static function resolveDepth(array|InputInterface $input): int
    {
        $depth = filter_var(
            self::getFirstValue($input, 'depth', 'd'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => -1, 'default' => false]],
        );
        $noRecursion = self::hasFlag($input, 'no-recursion', 'n');
        if ($noRecursion && false !== $depth) {
            throw new InvalidArgument('--no-recursion and --depth cannot be used together.');
        }

        if ($noRecursion) {
            return 0;
        }

        if (false === $depth) {
            return -1;
        }

        return $depth;
    }

    public static function fromCli(): self
    {
        /** @var OptionMap $options */
        $options = getopt(
            'p:f:o:d:ihVPxn',
            [
                'path:',
                'format:',
                'output:',
                'depth:',
                'info',
                'help',
                'version',
                'pretty',
                'isolation',
                'no-recursion',
            ]
        );

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
        return '--path=PATH  [--output=OUTPUT] [--format=FORMAT] [--depth=DEPTH] [--no-recursion] [--isolation] [--pretty] [--info] [--help] [--version]';
    }

    public static function consoleDescription(): string
    {
        return <<<HELP
<fg=green>  -p, --path=PATH</>       Path to scan for PHP files to profile (required)
<fg=green>  -d, --depth=DEPTH</>     Recursion depth (0 = current dir only, default: unlimited) (optional)
<fg=green>  -n, --no-recursion</>    Disable directory recursion (optional)
<fg=green>  -x, --isolation</>       Profile by isolation each file (optional)
<fg=green>  -f, --format=FORMAT</>   Output format: 'table' or 'json' (default: 'table')
<fg=green>  -o, --output=OUTPUT</>   Path to store the profiling output (optional)
<fg=green>  -P, --pretty</>          Pretty-print the JSON/NDJSON output (json only; optional)
<fg=green>  -i, --info</>            Show additional system/environment information (optional)
<fg=green>  -h, --help</>            Display this help message (optional)
<fg=green>  -V, --version</>         Display the version and exit (optional)
HELP;
    }
}
