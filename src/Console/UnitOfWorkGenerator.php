<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Profile;
use LogicException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use SplFileInfo;
use UnitEnum;

use function array_intersect;
use function array_merge;
use function array_reduce;
use function class_exists;
use function count;
use function function_exists;

final class UnitOfWorkGenerator
{
    public function __construct(
        public readonly PathInspector $pathInspector,
        public readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param list<non-empty-string> $tags
     *
     * @throws ReflectionException|LogicException
     *
     * @return iterable<UnitOfWork>
     */
    public function generate(string|SplFileInfo $realPath, array $tags = []): iterable
    {
        $splFileInfo = $this->filterPath($realPath);
        $realPath = $splFileInfo->getRealPath();
        $tuples = $this->pathInspector->inspect($splFileInfo);
        if ([] === $tuples) {
            return [];
        }
        require_once $realPath;

        /** @var array<UnitOfWork> $unitOfWorks */
        $unitOfWorks = array_reduce($tuples, function (array $unitOfWorks, array $tuple) use ($realPath, $tags): array {
            $unitOfWork = match ($tuple[0]) {
                'function' => $this->prepareFunctionProcess($tuple[1], $tags),
                'class' => $this->prepareMethodsProcess($tuple[1], $tags),
                default => throw new LogicException("Unable to prepare process for target type $tuple[1] in $realPath"),
            };

            if ([] === $unitOfWork || null === $unitOfWork) {
                return $unitOfWorks;
            }

            if ($unitOfWork instanceof UnitOfWork) {
                $unitOfWorks[] = $unitOfWork;

                return $unitOfWorks;
            }

            return array_merge($unitOfWorks, $unitOfWork);
        }, []);

        return $unitOfWorks;
    }

    /**
     * @param ReflectionClass<object|UnitEnum>|ReflectionFunctionAbstract $ref
     */
    private function findProfile(ReflectionClass|ReflectionFunctionAbstract $ref): ?Profile
    {
        $attributes = $ref->getAttributes(Profile::class);

        return 1 === count($attributes) ? $attributes[0]->newInstance() : null;
    }

    /**
     * @param list<non-empty-string> $tags
     *
     * @throws ReflectionException
     *
     * @return list<UnitOfWork>
     */
    private function prepareMethodsProcess(string $className, array $tags = []): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $refClass = enum_exists($className) ? new ReflectionEnum($className) : new ReflectionClass($className);
        $targetRequiresConstructorArgs = !$refClass instanceof ReflectionEnum && (($refClass->getConstructor()?->getNumberOfRequiredParameters() ?? 0) !== 0);
        $parentProfile = $this->findProfile($refClass);

        $results = [];
        foreach ($refClass->getMethods() as $method) {

            $profile = $this->findProfile($method) ?? $parentProfile;
            if (null === $profile) {
                continue;
            }

            if ([] !== $tags) {
                if ([] === $profile->tags) {
                    continue;
                }

                $intersection = array_intersect($tags, $profile->tags);
                if ([] === $intersection) {
                    continue;
                }
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
            }

            $results[] = new UnitOfWork(profile: $profile, target: $method);
        }

        return $results;
    }

    /**
     * @param list<non-empty-string> $tags
     */
    private function prepareFunctionProcess(string $functionName, array $tags = []): ?UnitOfWork
    {
        if (!function_exists($functionName)) {
            return null;
        }

        $method = new ReflectionFunction($functionName);
        if (false === $method->getFileName()) {
            $this->logger->notice('The function '.$functionName.' is an internal function which can not be profiled.', [
                'method' => $functionName,
            ]);

            return null;
        }

        $profile = $this->findProfile($method);
        if (null === $profile) {
            return null;
        }

        if ([] !== $tags) {
            if ([] === $profile->tags) {
                return null;
            }

            $intersection = array_intersect($tags, $profile->tags);
            if ([] === $intersection) {
                return null;
            }
        }

        if (0 !== $method->getNumberOfParameters()) {
            $this->logger->notice('The function '.$functionName.' located in '.$method->getFileName().' can not be profiled because it has arguments.', [
                'profile' => $profile,
                'method' => $functionName,
                'class' => null,
                'path' => $method->getFileName(),
            ]);
            return null;
        }

        return new UnitOfWork(profile: $profile, target: $method);
    }

    private function filterPath(SplFileInfo|string $path): SplFileInfo
    {
        return $path instanceof SplFileInfo ? $path : new SplFileInfo($path);
    }
}
