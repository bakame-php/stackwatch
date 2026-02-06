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
use function enum_exists;
use function function_exists;
use function in_array;

final class UnitOfWorkGenerator
{
    public function __construct(
        public readonly PathInspector $pathInspector,
        public readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws ReflectionException|LogicException
     *
     * @return iterable<UnitOfWork>
     */
    public function generate(string|SplFileInfo $realPath, Input $input): iterable
    {
        $splFileInfo = $this->filterPath($realPath);
        $realPath = $splFileInfo->getRealPath();
        $tuples = $this->pathInspector->inspect($splFileInfo);
        if ([] === $tuples) {
            return [];
        }
        require_once $realPath;

        /** @var array<UnitOfWork> $unitOfWorks */
        $unitOfWorks = array_reduce($tuples, function (array $unitOfWorks, array $tuple) use ($realPath, $input): array {
            $unitOfWork = match ($tuple[0]) {
                'function' => $this->prepareFunctionProcess($tuple[1], $input),
                'class' => $this->prepareMethodsProcess($tuple[1], $input),
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
     * @param ReflectionClass<object>|ReflectionEnum<UnitEnum>|ReflectionFunctionAbstract $ref
     */
    private function findProfile(ReflectionClass|ReflectionEnum|ReflectionFunctionAbstract $ref): ?Profile
    {
        $attributes = $ref->getAttributes(Profile::class);

        return 1 === count($attributes) ? $attributes[0]->newInstance() : null;
    }

    /**
     * @throws ReflectionException
     *
     * @return list<UnitOfWork>
     */
    private function prepareMethodsProcess(string $className, Input $input): array
    {
        if (!class_exists($className)) {
            return [];
        }

        if (enum_exists($className)) {
            return [];
        }

        $refClass = new ReflectionClass($className);
        $targetRequiresConstructorArgs = 0 !== ($refClass->getConstructor()?->getNumberOfRequiredParameters() ?? 0);
        $parentProfile = $this->findProfile($refClass);

        $results = [];
        foreach ($refClass->getMethods() as $method) {

            $profile = $this->findProfile($method) ?? $parentProfile;
            if (null === $profile) {
                continue;
            }

            $tags = $input->tags;
            if ([] !== $tags) {
                if ([] === $profile->tags) {
                    continue;
                }

                $intersection = array_intersect($tags, $profile->tags);
                if ([] === $intersection) {
                    continue;
                }
            }

            $methodVisibilities = $input->methodVisibilityList;
            if ([] !== $methodVisibilities) {
                $visibility = match (true) {
                    $method->isPublic() => 'public',
                    $method->isProtected() => 'protected',
                    $method->isPrivate() => 'private',
                    default => throw new LogicException("Unsupported method visibility for `$className::$method->name()`"),
                };

                if (!in_array($visibility, $methodVisibilities, true)) {
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

    private function prepareFunctionProcess(string $functionName, Input $input): ?UnitOfWork
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

        $tags = $input->tags;
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
