<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Exporter\JsonExporter;
use Bakame\Stackwatch\Profile;
use Bakame\Stackwatch\UnableToProfile;
use Bakame\Stackwatch\Unit;
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
use function dd;
use function dump;
use function in_array;
use function is_array;
use function strtolower;

/**
 * Scans and Profile functions and methods defined in a specific path using the Profile attribute.
 *
 * @phpstan-import-type UnitOfWorkMap from UnitOfWork
 */
final class PathProfiler
{
    private bool $recursive = true;
    private bool $isolated = false;

    public function __construct(
        public readonly UnitOfWorkGenerator $unitOfWorkGenerator,
        public readonly Processor $processor,
        public readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public static function forConsole(OutputInterface $output = new ConsoleOutput(), LoggerInterface $logger = new NullLogger()): self
    {
        return new self(
            new UnitOfWorkGenerator(new PathInspector(Profile::class), $logger),
            new ConsoleProcessor(new ConsoleExporter($output)),
            $logger,
        );
    }

    public function disableRecursive(): void
    {
        $this->recursive = false;
    }

    public function enableRecursive(): void
    {
        $this->recursive = true;
    }

    public function isRecursive(): bool
    {
        return $this->recursive;
    }

    public function enableIsolation(): void
    {
        $this->isolated = true;
    }

    public function disableIsolation(): void
    {
        $this->isolated = false;
    }

    public function isInIsolation(): bool
    {
        return $this->isolated;
    }

    /**
     * @param SplFileInfo|resource|string $path
     */
    public static function forJson(mixed $path, int $jsonOptions = 0, LoggerInterface $logger = new NullLogger()): self
    {
        return new self(
            new UnitOfWorkGenerator(new PathInspector(Profile::class), $logger),
            new JsonProcessor(new JsonExporter($path, $jsonOptions)),
            $logger,
        );
    }

    public function handle(string $path): void
    {
        $filePath = new SplFileInfo($path);
        $filePath->isFile() || $filePath->isDir() || throw new RuntimeException("Unable to locate the path $path");
        $filePath->isReadable() || throw new RuntimeException("Unable to access for read the path $path");

        $files = [$filePath];
        if ($filePath->isDir()) {
            /** @var iterable<SplFileInfo> $files */
            $files = match (!$this->recursive) {
                false => new CallbackFilterIterator(
                    new FilesystemIterator($filePath->getPathname()),
                    fn (SplFileInfo $file) => $file->isFile() && $file->isReadable() && 'php' === strtolower($file->getExtension())
                ),
                true => new CallbackFilterIterator(
                    new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filePath->getPathname(), FilesystemIterator::SKIP_DOTS)),
                    fn (SplFileInfo $file) => $file->isFile() && $file->isReadable() && 'php' === strtolower($file->getExtension())
                ),
            };
        }

        foreach ($files as $file) {
            if (!$file->isFile() || !$file->isReadable()) {
                continue;
            }

            $filesize = $file->getSize();
            if (in_array($filesize, [false, 0], true)) {
                $realPath = $file->getRealPath();
                $this->logger->notice('The file '.$realPath.' can not be profiled because it is empty.', ['path' => $realPath]);

                continue;
            }

            $this->handleFile($file);
        }
    }

    /**
     * @throws Throwable
     */
    public function handleFile(SplFileInfo $path): void
    {
        $units = match ($this->isolated) {
            true => $this->createUnitOfWorksInIsolation($path),
            false => $this->createUnitOfWorks($path),
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
        $process = new Process([PHP_BINARY, $stackwatchPath, '-p', $realPath, '-f', 'json', '-n']);

        $process->run();
        $process->isSuccessful() || throw new UnableToProfile($process->getErrorOutput());
        $json = $process->getOutput();
        $data = json_decode($json, true);
        if (
            isset($data['path'], $data['data']) &&
            $data['path'] === $realPath &&
            is_array($data['data']) &&
            [] !== $data['data']
        ) {
            return array_map(UnitOfWork::fromArray(...), $data['data']); /* @phpstan-ignore-line */
        }

        return [];
    }


    /**
     * @throws RuntimeException
     *
     * @return iterable<UnitOfWork>
     */
    public function createUnitOfWorks(SplFileInfo $path): iterable
    {
        $realPath = $path->getRealPath();
        try {
            return $this->unitOfWorkGenerator->generate($realPath);
        } catch (Throwable $exception) {
            $this->logger->notice('The file '.$realPath.' can not be profiled.', ['path' => $realPath, 'exception' => $exception]);

            return [];
        }
    }
}
