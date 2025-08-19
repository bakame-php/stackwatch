<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use ArrayIterator;
use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Exporter\JsonExporter;
use Bakame\Stackwatch\Profile;
use Bakame\Stackwatch\UnableToProfile;
use CallbackFilterIterator;
use FilesystemIterator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;

use function array_map;
use function in_array;
use function is_array;
use function strtolower;

use const PHP_BINARY;

/**
 * Scans and Profile functions and methods defined in a specific path using the Profile attribute.
 *
 * @phpstan-import-type UnitOfWorkMap from UnitOfWork
 */
final class PathProfiler
{
    public function __construct(
        public readonly UnitOfWorkGenerator $unitOfWorkGenerator,
        public readonly Processor $processor,
        public readonly Input $input,
        public readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public static function forConsole(
        Input $input,
        OutputInterface $output = new ConsoleOutput(),
        LoggerInterface $logger = new NullLogger(),
    ): self {
        return new self(
            new UnitOfWorkGenerator(new PathInspector(Profile::class), $logger),
            new ConsoleProcessor(exporter: new ConsoleExporter($output), dryRun: $input->dryRun),
            $input,
            $logger,
        );
    }

    /**
     * @param SplFileInfo|resource|string $path
     */
    public static function forJson(
        Input $input,
        mixed $path,
        int $jsonOptions = 0,
        LoggerInterface $logger = new NullLogger()
    ): self {
        return new self(
            new UnitOfWorkGenerator(new PathInspector(Profile::class), $logger),
            new JsonProcessor(new JsonExporter($path, $jsonOptions), $input->dryRun),
            $input,
            $logger,
        );
    }

    public function handle(string $path): void
    {
        $filePath = new SplFileInfo($path);
        $filePath->isFile() || $filePath->isDir() || throw new RuntimeException("Unable to locate the path $path");
        $filePath->isReadable() || throw new RuntimeException("Unable to access for read the path $path");

        $files = new ArrayIterator([$filePath]);
        if ($filePath->isDir()) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filePath->getPathname(), FilesystemIterator::SKIP_DOTS));
            $files->setMaxDepth($this->input->depth);
        }

        /** @var iterable<SplFileInfo> $phpFiles */
        $phpFiles = new CallbackFilterIterator(
            $files,
            function (SplFileInfo $file) {
                if (!$file->isFile() || !$file->isReadable()) {
                    return false;
                }

                if (in_array($file->getSize(), [false, 0], true)) {
                    return false;
                }

                if ([] === $this->input->fileSuffixes) {
                    return 'php' === strtolower($file->getExtension());
                }

                foreach ($this->input->fileSuffixes as $suffix) {
                    if (str_ends_with($file->getPathname(), $suffix)) {
                        return true;
                    }
                }

                return false;
            }
        );

        foreach ($phpFiles as $phpFile) {
            $this->handleFile($phpFile);
        }
    }

    /**
     * @throws Throwable
     */
    public function handleFile(SplFileInfo $path): void
    {
        $units = match ($this->input->inIsolation) {
            State::Enabled => $this->createUnitOfWorksInIsolation($path),
            State::Disabled => $this->createUnitOfWorks($path),
        };

        if ([] !== $units) {
            $this->processor->process($units);
        }
    }

    /**
     * @throws RuntimeException
     *
     * @return iterable<UnitOfWork>
     */
    private function createUnitOfWorksInIsolation(SplFileInfo $path): iterable
    {
        $realPath = $path->getRealPath();
        $stackwatchPath = realpath(__DIR__.'/../../bin/stackwatch');
        false !== $stackwatchPath || throw new RuntimeException('Could not resolve stackwatch path.');

        $input = $this->input
            ->withFormat(Input::JSON_FORMAT)
            ->withPath($realPath)
            ->withDepth(0)
            ->withInIsolation(State::Disabled);
        ;

        $arguments = array_merge([PHP_BINARY, $stackwatchPath], $input->toArguments());
        $process = new Process($arguments);
        $process->run();
        $process->isSuccessful() || throw new UnableToProfile($process->getErrorOutput());
        $json = $process->getOutput();
        /** @var array{path: string, data: array<UnitOfWorkMap>} $data */
        $data = json_decode($json, true);
        if (
            isset($data['path'], $data['data']) &&
            $data['path'] === $realPath &&
            is_array($data['data']) &&
            [] !== $data['data']
        ) {
            return array_map(UnitOfWork::fromArray(...), $data['data']);
        }

        return [];
    }

    /**
     * @throws RuntimeException
     *
     * @return iterable<UnitOfWork>
     */
    private function createUnitOfWorks(SplFileInfo $path): iterable
    {
        $realPath = $path->getRealPath();
        try {
            return $this->unitOfWorkGenerator->generate($realPath, $this->input);
        } catch (Throwable $exception) {
            $this->logger->notice('The file '.$realPath.' can not be profiled.', ['path' => $realPath, 'exception' => $exception]);

            return [];
        }
    }
}
