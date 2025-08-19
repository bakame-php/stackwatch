<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\InvalidArgument;
use JsonSerializable;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

use function array_diff_key;
use function array_filter;
use function array_keys;
use function explode;
use function filter_var;
use function getopt;
use function implode;
use function in_array;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_INT;

/**
 * @phpstan-type InputMap array{
 *      path: ?string,
 *      depth: int,
 *      format: string,
 *      output: ?string,
 *      memory_limit : ?string,
 *      log_file: ?string,
 *      help: string,
 *      info: string,
 *      version: string,
 *      json_pretty_print: string,
 *      tags: list<non-empty-string>,
 *      isolation: string,
 *      method_visibility: list<non-empty-string>,
 *      progress_bar: string,
 *      dry_run: string,
 *      file_suffixes: list<non-empty-string>,
 *  }
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
 *     d?: string|false,
 *     no-recursion?: string|false,
 *     n?: string|false,
 *     tags?: string|false,
 *     t?: string|false,
 *     dry-run?: string|false,
 *     memory-limit?: string|false,
 *     log?: string|false,
 *     file-suffix?: string|false,
 *     method-visibility?: string|false,
 *     no-progress?: string|false,
 * }
 */
final class Input implements JsonSerializable
{
    public const JSON_FORMAT = 'json';
    public const TEXT_FORMAT = 'text';

    /**
     * @param list<non-empty-string> $tags
     * @param list<non-empty-string> $fileSuffixes
     * @param list<non-empty-string> $methodVisibilityList
     */
    public function __construct(
        public readonly ?string $path,
        public readonly Display $helpSection = Display::Hidden,
        public readonly Display $infoSection = Display::Hidden,
        public readonly Display $versionSection = Display::Hidden,
        public readonly string $format = self::TEXT_FORMAT,
        public readonly ?string $output = null,
        public readonly Feature $jsonPrettyPrint = Feature::Disabled,
        public readonly Feature $inIsolation = Feature::Disabled,
        public readonly Feature $dryRun = Feature::Disabled,
        public readonly int $depth = -1,
        public readonly array $tags = [],
        public readonly ?string $memoryLimit = null,
        public readonly ?string $logFile = null,
        public readonly array $fileSuffixes = [],
        public readonly array $methodVisibilityList = [],
        public readonly Display $progressBar = Display::Visible,
    ) {
        in_array($this->format, [self::JSON_FORMAT, self::TEXT_FORMAT], true) || throw new InvalidArgument('Output format is not supported');
        null === $this->path || '' !== trim($this->path) || throw new InvalidArgument('path format is not valid');
        ($this->helpSection->isVisible() || $this->infoSection->isVisible() || $this->versionSection->isVisible() || null !== $this->path) || throw new InvalidArgument('Missing required option: --path');
        -1 <= $this->depth || throw new InvalidArgument('depth option must be greater or equal to -1.');
    }

    /**
     * @return InputMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return InputMap
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'depth' => $this->depth,
            'format' => $this->format,
            'output' => $this->output,
            'memory_limit' => $this->memoryLimit,
            'log_file' => $this->logFile,
            'help' => $this->helpSection->value,
            'info' => $this->infoSection->value,
            'version' => $this->versionSection->value,
            'json_pretty_print' => $this->jsonPrettyPrint->value,
            'tags' => $this->tags,
            'isolation' => $this->inIsolation->value,
            'method_visibility' => $this->methodVisibilityList,
            'progress_bar' => $this->progressBar->value,
            'dry_run' => $this->dryRun->value,
            'file_suffixes' => $this->fileSuffixes,
        ];
    }

    public function withPath(string $path): self
    {
        $path = trim($path);
        if ($path === $this->path) {
            return $this;
        }

        '' !== $path || throw new InvalidArgument('path format is not valid');

        return new self(
            $path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withHelpSection(Display $visibility): self
    {
        if ($visibility === $this->helpSection) {
            return $this;
        }

        return new self(
            $this->path,
            $visibility,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withInfoSection(Display $visibility): self
    {
        if ($visibility === $this->infoSection) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $visibility,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withVersionSection(Display $visibility): self
    {
        if ($visibility === $this->versionSection) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $visibility,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withFormat(string $format): self
    {
        if ($format === $this->format) {
            return $this;
        }

        in_array($format, [self::JSON_FORMAT, self::TEXT_FORMAT], true) || throw new InvalidArgument(sprintf('Format %s is not supported', $format));

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withOutput(?string $output): self
    {
        if ($output === $this->output) {
            return $this;
        }

        if (null !== $output) {
            $output = trim($output);
            '' !== $output || throw new InvalidArgument('Output must be a valid output');
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withJsnPrettyPrint(Feature $state): self
    {
        if ($state === $this->jsonPrettyPrint) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $state,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withInIsolation(Feature $state): self
    {
        if ($state === $this->inIsolation) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $state,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withDryRun(Feature $state): self
    {
        if ($state === $this->dryRun) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $state,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withDepth(int $depth): self
    {
        if ($depth === $this->depth) {
            return $this;
        }

        - 1 <= $depth || throw new InvalidArgument('Depth option must be greater or equal to -1.');

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    /**
     * @param list<non-empty-string> $tags
     *
     */
    public function withTags(array $tags): self
    {
        $foundNonEmptyString = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ('' === $tag) {
                continue;
            }

            $foundNonEmptyString[$tag] = 1;
        }

        $tags = array_keys($foundNonEmptyString);
        if ($tags === $this->tags) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withMemoryLimit(?string $memoryLimit): self
    {
        if (null !== $memoryLimit) {
            $memoryLimit = trim($memoryLimit);
            '' !== $memoryLimit || throw new InvalidArgument('memory limit is not valid');
        }

        if ($memoryLimit === $this->memoryLimit) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    public function withLogFile(?string $logFile): self
    {
        if (null !== $logFile) {
            $logFile = trim($logFile);
            '' !== $logFile || throw new InvalidArgument('log file path is not valid');
        }

        if ($logFile === $this->logFile) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    /**
     * @param list<non-empty-string> $suffixes
     *
     */
    public function withFileSuffixes(array $suffixes): self
    {
        $foundNonEmptyString = [];
        foreach ($suffixes as $tag) {
            $tag = trim($tag);
            if ('' === $tag) {
                continue;
            }

            $foundNonEmptyString[$tag] = 1;
        }

        $suffixes = array_keys($foundNonEmptyString);
        if ($suffixes === $this->fileSuffixes) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $suffixes,
            $this->methodVisibilityList,
            $this->progressBar,
        );
    }

    /**
     * @param list<non-empty-string> $visibilities
     *
     */
    public function withMethodVisbility(array $visibilities): self
    {
        $foundNonEmptyString = [];
        foreach ($visibilities as $tag) {
            $tag = trim($tag);
            if ('' === $tag) {
                continue;
            }

            $foundNonEmptyString[$tag] = 1;
        }

        $visibilities = array_values(
            array_filter(
                array_keys($foundNonEmptyString),
                fn (string $value): bool => in_array($value, ['public', 'protected', 'private'], true)
            )
        );
        if ($visibilities === $this->methodVisibilityList) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $visibilities,
            $this->progressBar,
        );
    }

    public function withProgressBar(Display $visibility): self
    {
        if ($visibility === $this->progressBar) {
            return $this;
        }

        return new self(
            $this->path,
            $this->helpSection,
            $this->infoSection,
            $this->versionSection,
            $this->format,
            $this->output,
            $this->jsonPrettyPrint,
            $this->inIsolation,
            $this->dryRun,
            $this->depth,
            $this->tags,
            $this->memoryLimit,
            $this->logFile,
            $this->fileSuffixes,
            $this->methodVisibilityList,
            $visibility,
        );
    }

    /**
     * @return list<string>
     */
    public function toArguments(): array
    {
        $arguments = [];
        if (null !== $this->path) {
            $arguments[] = '--path';
            $arguments[] = $this->path;
        }

        if ($this->infoSection->isVisible()) {
            $arguments[] = '--info';
        }

        if ($this->helpSection->isVisible()) {
            $arguments[] = '--help';
        }

        if ($this->versionSection->isVisible()) {
            $arguments[] = '--version';
        }

        $arguments[] = '--format';
        $arguments[] = $this->format;

        if (null !== $this->output) {
            $arguments[] = '--output';
            $arguments[] = $this->output;
        }

        if (null !== $this->logFile) {
            $arguments[] = '--log';
            $arguments[] = $this->logFile;
        }

        if ($this->jsonPrettyPrint->isEnabled()) {
            $arguments[] = '--pretty';
        }

        if (-1 < $this->depth) {
            $arguments[] = '--depth';
            $arguments[] = (string) $this->depth;
        }

        if ([] !== $this->tags) {
            $arguments[] = '--tags';
            $arguments[] = implode(',', $this->tags);
        }

        if ([] !== $this->methodVisibilityList) {
            $arguments[] = '--method-visibility';
            $arguments[] = implode(',', $this->methodVisibilityList);
        }

        if ([] !== $this->fileSuffixes) {
            $arguments[] = '--file-suffix';
            $arguments[] = implode(',', $this->fileSuffixes);
        }

        if ($this->dryRun->isEnabled()) {
            $arguments[] = '--dry-run';
        }

        if ($this->inIsolation->isEnabled()) {
            $arguments[] = '--isolation';
        }

        if (null !== $this->memoryLimit) {
            $arguments[] = '--memory-limit';
            $arguments[] = $this->memoryLimit;
        }

        if ($this->progressBar->isHidden()) {
            $arguments[] = '--no-progress';
        }

        return $arguments;
    }

    /**
     * @param InputMap $data
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key([
            'path' => 1,
            'help' => 1,
            'info' => 1,
            'version' => 1,
            'format' => 1,
            'output' => 1,
            'json_pretty_print' => 1,
            'isolation' => 1,
            'dry_run' => 1,
            'depth' => 1,
            'tags' => 1,
            'memory_limit' => 1,
            'log_file' => 1,
            'file_suffixes' => 1,
            'method_visibility' => 1,
            'progress_bar' => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        try {
            return new self(
                path: $data['path'],
                helpSection: Display::from($data['help']),
                infoSection: Display::from($data['info']),
                versionSection: Display::from($data['version']),
                format: $data['format'],
                output: $data['output'],
                jsonPrettyPrint: Feature::from($data['json_pretty_print']),
                inIsolation: Feature::from($data['isolation']),
                dryRun: Feature::from($data['dry_run']),
                depth: $data['depth'],
                tags: $data['tags'],
                memoryLimit: $data['memory_limit'],
                logFile: $data['log_file'],
                fileSuffixes: $data['file_suffixes'],
                methodVisibilityList: $data['method_visibility'],
                progressBar: Display::from($data['progress_bar']),
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a input from the payload', previous: $exception);
        }
    }

    /**
     * @param OptionMap|InputInterface $input
     */
    public static function fromInput(array|InputInterface $input): self
    {
        return new self(
            path: self::getFirstValue($input, 'path', 'p'),
            helpSection: Display::fromBool(self::hasFlag($input, 'help', 'h')),
            infoSection: Display::fromBool(self::hasFlag($input, 'info', 'i')),
            versionSection: Display::fromBool(self::hasFlag($input, 'version', 'V')),
            format: self::normalizeFormat(self::getFirstValue($input, 'format', 'f') ?? self::TEXT_FORMAT),
            output: self::getFirstValue($input, 'output', 'o'),
            jsonPrettyPrint: Feature::fromBool(self::hasFlag($input, 'pretty', 'P')),
            inIsolation: Feature::fromBool(self::hasFlag($input, 'isolation', 'x')),
            dryRun: Feature::fromBool(self::hasFlag($input, 'dry-run')),
            depth: self::resolveDepth($input),
            tags: self::getNonEmptyStringList($input, 'tags', 't'),
            memoryLimit: self::getFirstValue($input, 'memory-limit'),
            logFile: self::getFirstValue($input, 'memory-limit'),
            fileSuffixes: self::getNonEmptyStringList($input, 'file-suffix'),
            methodVisibilityList: self::resolveMethodVisibility($input),
            progressBar: Display::fromBool(! self::hasFlag($input, 'no-progress')),
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

    /**
     * @param array<string, string|false>|InputInterface $input
     *
     * @return list<non-empty-string>
     */
    private static function resolveMethodVisibility(array|InputInterface $input): array
    {
        $list = self::getNonEmptyStringList($input, 'method-visibility');
        $list = array_filter($list, fn (string $value): bool => in_array($value, ['public', 'protected', 'private'], true));

        return array_values($list);
    }

    /**
     * @param array<string, string|false>|InputInterface $input
     *
     * @return list<non-empty-string>
     */
    private static function getNonEmptyStringList(array|InputInterface $input, string ...$optionNames): array
    {
        $list = [];
        $inputString = self::getFirstValue($input, ...$optionNames);
        if (null === $inputString) {
            return $list;
        }

        $list = explode(',', strtolower(trim($inputString)));
        if ([] === $list) {
            return $list;
        }

        $foundNonEmptyString = [];
        foreach ($list as $item) {
            $item = trim($item);
            if ('' === $item) {
                continue;
            }

            $foundNonEmptyString[$item] = 1;
        }

        return array_keys($foundNonEmptyString);
    }

    public static function fromCli(): self
    {
        /** @var OptionMap $options */
        $options = getopt(
            'p:f:o:d:t:ihVPxn',
            [
                'path:',
                'format:',
                'output:',
                'depth:',
                'tags:',
                'info',
                'help',
                'version',
                'pretty',
                'isolation',
                'no-recursion',
                'dry-run',
                'memory-limit:',
                'log:',
                'file-suffix:',
                'method-visibility:',
                'no-progress',
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
            self::TEXT_FORMAT => self::TEXT_FORMAT,
            default => throw new InvalidArgument("Unsupported format: $format"),
        };
    }

    public static function usage(): string
    {
        return '--path=PATH [options]';
    }

    public static function consoleDescription(): string
    {
        return <<<HELP
<fg=green>  -p, --path=PATH</>                Path to scan for PHP files to profile <fg=yellow>(required)</>
<fg=green>  -d, --depth=DEPTH</>              Recursion depth <fg=yellow>(0 = current dir only, default: unlimited)</>
<fg=green>  -f, --format=FORMAT</>            Output format: 'text' or 'json' <fg=yellow>(default: 'text')</>
<fg=green>  -o, --output=OUTPUT</>            Path to store the profiling output
<fg=green>  --memory-limit=MEMORY-LIMIT</>    Memory limit for analysis
<fg=green>  --log=FILE</>                     Writes log in the specified file
<fg=green>  -n, --no-recursion</>             Disable directory recursion
<fg=green>  -x, --isolation</>                Profile by isolation each file
<fg=green>  --dry-run</>                      List profiling targets without performing the profile
<fg=green>  -P, --pretty</>                   Pretty-print the JSON/NDJSON output <fg=yellow>(json only)</>
<fg=green>  -i, --info</>                     Show additional system/environment information
<fg=green>  -h, --help</>                     Display this help message
<fg=green>  -V, --version</>                  Display the version and exit
<fg=green>  -t, --tags=TAGS</>                Only run the profiles for the listed tag(s)
<fg=green>  --file-suffix=SUFFIX</>           Only run the profiles for the listed file enditng with the listed suffxies
<fg=green>  --method-visibility=VISIBILITY</> Only run the profiles for the method with the listed visibility (public, protected, private), (default: all)
<fg=green>  --no-progress</>                  Disable output of profiling execution progress

HELP;
    }
}
