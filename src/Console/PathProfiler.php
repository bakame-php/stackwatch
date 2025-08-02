<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\ConsoleTableExporter;
use Bakame\Stackwatch\Exporter\JsonExporter;
use Bakame\Stackwatch\Profile;
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
use Throwable;

use function in_array;
use function strtolower;

/**
 * Scans and Profile functions and methods defined in a specific path using the Profile attribute.
 */
final class PathProfiler
{
    public function __construct(
        public readonly PathInspector $pathInspector,
        public readonly TargetGenerator $targetGenerator,
        public readonly Processor $processor,
        public readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public static function forConsole(OutputInterface $output = new ConsoleOutput(), LoggerInterface $logger = new NullLogger()): self
    {
        return new self(
            new PathInspector(Profile::class),
            new TargetGenerator($logger),
            new ConsoleTableProcessor(new ConsoleTableExporter($output)),
            $logger,
        );
    }

    /**
     * @param SplFileInfo|resource|string $path
     */
    public static function forJson(mixed $path, int $jsonOptions = 0, LoggerInterface $logger = new NullLogger()): self
    {
        return new self(
            new PathInspector(Profile::class),
            new TargetGenerator($logger),
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
            $files = new CallbackFilterIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filePath->getPathname(), FilesystemIterator::SKIP_DOTS)),
                fn (SplFileInfo $file) => $file->isFile() && $file->isReadable() && 'php' === strtolower($file->getExtension())
            );
        }

        foreach ($files as $file) {
            $this->handleFile($file);
        }
    }

    /**
     * @throws Throwable
     */
    private function handleFile(SplFileInfo $path): void
    {
        $realPath = $path->getRealPath();
        $path->isFile() ||  throw new RuntimeException("The path $realPath can not be profiled because it not a file.");
        $path->isReadable() || throw new RuntimeException("Unable to access for read the path $realPath");

        $filesize = $path->getSize();
        if (in_array($filesize, [false, 0], true)) {
            $this->logger->notice('The file '.$realPath.' can not be profiled because it is empty.', ['path' => $realPath]);

            return;
        }

        $code = $path->openFile()->fread($filesize);
        if (false === $code) {
            $this->logger->notice('The file '.$realPath.' can not be profiled because it is not readable.', ['path' => $realPath]);

            return;
        }

        $tuples = $this->pathInspector->extract($code);
        $targets = $this->targetGenerator->generate($realPath, $tuples);
        $this->processor->process($targets);
    }
}
