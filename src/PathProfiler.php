<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use CallbackFilterIterator;
use FilesystemIterator;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_is_list;
use function array_merge;
use function array_reduce;
use function class_exists;
use function count;
use function enum_exists;
use function function_exists;
use function in_array;
use function strtolower;

/**
 * Scans and Profile functions and methods defined in a specific path
 * using the Profile attribute.
 *
 * @phpstan-import-type TargetList from Processor
 * @phpstan-import-type Target from Processor
 */
final class PathProfiler
{
    public function __construct(
        public readonly PathInspector $pathInspector,
        public readonly Processor $processor,
        public readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public static function forConsole(OutputInterface $output = new ConsoleOutput(), LoggerInterface $logger = new NullLogger()): self
    {
        return new self(
            new PathInspector(Profile::class),
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
    public function handleFile(SplFileInfo $path): void
    {
        $realPath = $path->getRealPath();
        if (!$path->isReadable()) {
            $this->logger->notice('The file '.$realPath.' can not be profiled because it not readable.', ['path' => $realPath]);

            return;
        };

        $filesize = $path->getSize();
        if (in_array($filesize, [false, 0], true)) {
            $this->logger->notice('The file '.$realPath.' can not be profiled because it is empty.', ['path' => $realPath]);

            return;
        }

        $code = $path->openFile('r')->fread($filesize);
        if (false === $code) {
            $this->logger->notice('The file '.$realPath.' can not be profiled because it is not readable.', ['path' => $realPath]);

            return;
        }

        $tuples = $this->pathInspector->extract($code);
        if ([] === $tuples) {
            return;
        }

        require_once $realPath;

        /** @var TargetList $targets */
        $targets = array_reduce($tuples, function (array $targets, array $tuple) use ($realPath): array {
            $target = match ($tuple[0]) {
                'function' => $this->prepareFunctionProcess($tuple[1]),
                'class' => $this->prepareMethodsProcess($tuple[1]),
                default => throw new LogicException("Unable to prepare process for target type $tuple[1] in $realPath"),
            };

            if ([] === $target) {
                return $targets;
            }

            if (array_is_list($target)) {
                return array_merge($targets, $target);
            }

            $targets[] = $target;

            return $targets;

        }, []);

        $this->processor->process($targets);
    }

    /**
     * @throws InvalidArgument
     */
    private function findProfile(ReflectionFunctionAbstract $ref): ?Profile
    {
        $attributes = $ref->getAttributes(Profile::class);

        return 1 === count($attributes) ? $attributes[0]->newInstance() : null;
    }

    /**
     * @return array{}|Target
     */
    public function prepareFunctionProcess(string $functionName): array
    {
        if (!function_exists($functionName)) {
            return [];
        }

        $method = new ReflectionFunction($functionName);
        $profile = $this->findProfile($method);
        if (null === $profile) {
            return [];
        }

        if (0 !== $method->getNumberOfParameters()) {
            $this->logger->notice('The function '.$functionName.' located in '.$method->getFileName().' can not be profiled because it has arguments.', [
                'profile' => $profile,
                'method' => $functionName,
                'path' => $method->getFileName(),
            ]);
            return [];
        }

        return [
            'closure' => $method->invoke(...),
            'profile' => $profile,
            'method' => $method,
        ];
    }

    /**
     * @throws ReflectionException
     *
     * @return array{}|TargetList
     */
    public function prepareMethodsProcess(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $refClass = enum_exists($className) ? new ReflectionEnum($className) : new ReflectionClass($className);
        $targetRequiresConstructorArgs = !$refClass instanceof ReflectionEnum && (($refClass->getConstructor()?->getNumberOfRequiredParameters() ?? 0) !== 0);
        $isTargetInstantiated = false;
        $instance = null;

        $results = [];
        foreach ($refClass->getMethods() as $method) {
            $profile = $this->findProfile($method);
            if (null === $profile) {
                continue;
            }

            if ($method->isAbstract()) {
                $this->logger->notice('The method '.$className.'::'.$method->getName().' located in <fg=green>'.$method->getFileName().'</> can not be profiled because it is abstract.', [
                    'profile' => $profile,
                    'method' => $method->getName(),
                    'class' => $className,
                    'path' => $method->getFileName(),
                ]);
                continue;
            }

            if (0 !== $method->getNumberOfParameters()) {
                $this->logger->notice('The method '.$className.'::'.$method->getName().' located in '.$method->getFileName().' can not be profiled because it has arguments.', [
                    'profile' => $profile,
                    'method' => $method->getName(),
                    'class' => $className,
                    'path' => $method->getFileName(),
                ]);
                continue;
            }

            if (!$method->isStatic()) {
                if ($targetRequiresConstructorArgs) {
                    $this->logger->notice('The non-static method '.$className.'::'.$method->getName().' located in '.$method->getFileName().' can not be profiled because the class requires constructor arguments.', [
                        'profile' => $profile,
                        'method' => $method->getName(),
                        'class' => $className,
                        'path' => $method->getFileName(),
                    ]);
                    continue;
                }

                if (!$isTargetInstantiated) {
                    $instance = $refClass instanceof ReflectionEnum ? $refClass->getCases()[0]->getValue() : $refClass->newInstance();
                    $isTargetInstantiated = true;
                }
            }

            $results[] = [
                'closure' => fn () => $method->invoke($method->isStatic() ? null : $instance),
                'profile' => $profile,
                'method' => $method,
            ];
            ;
        }

        return $results;
    }
}
