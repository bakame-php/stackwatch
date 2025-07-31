<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

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
 * }
 */
final class StackwatchInput
{
    public const JSON_FORMAT = 'json';
    public const CLI_FORMAT = 'cli';

    public function __construct(
        public readonly ?string $path,
        public readonly bool $showHelp,
        public readonly bool $showInfo,
        public readonly bool $showVersion,
        public readonly string $format,
        public readonly ?string $output = null,
        public readonly bool $pretty = false,
    ) {
        in_array($this->format, [self::JSON_FORMAT, self::CLI_FORMAT], true) || throw new InvalidArgument('Output format is not supported');
        null === $this->path || '' !== trim($this->path) || throw new InvalidArgument('path format is not valid');
    }

    /**
     * @param OptionMap $options
     */
    public static function fromOptions(array $options): self
    {
        $showInfo = isset($options['info']) || isset($options['i']);
        $showHelp = isset($options['help']) || isset($options['h']);
        $showVersion = isset($options['version']) || isset($options['V']);
        $pretty = isset($options['pretty']) || isset($options['P']);
        $output = null;
        if (isset($options['output']) && is_string($options['output'])) {
            $output = trim($options['output']);
        }
        if (isset($options['o']) && is_string($options['o'])) {
            $output = trim($options['o']);
        }

        $path = null;
        if (isset($options['path']) && is_string($options['path'])) {
            $path = trim($options['path']);
        } elseif (isset($options['p']) && is_string($options['p'])) {
            $path = trim($options['p']);
        }

        $format = strtolower($options['format'] ?? $options['f'] ?? self::CLI_FORMAT);

        return new self(
            path: $path,
            showHelp: $showHelp,
            showInfo: $showInfo,
            showVersion: $showVersion,
            format: self::JSON_FORMAT === $format ? self::JSON_FORMAT : self::CLI_FORMAT,
            output: $output,
            pretty: $pretty,
        );
    }

    public static function fromCli(): self
    {
        /** @var OptionMap $options */
        $options = getopt('ihvPp:f:o:', ['path:', 'format:', 'output:', 'info', 'help', 'version', 'pretty']);

        return self::fromOptions($options);
    }
}
