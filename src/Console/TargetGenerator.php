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
use UnitEnum;

use function array_merge;
use function array_reduce;
use function class_exists;
use function count;
use function function_exists;

final class TargetGenerator
{
    public function __construct(public readonly LoggerInterface $logger)
    {
    }

    /**
     * @param list<array{0:string, 1:string}> $tuples
     *
     * @throws ReflectionException|LogicException
     *
     * @return iterable<Target>
     */
    public function generate(string $realPath, array $tuples): iterable
    {
        if ([] === $tuples) {
            return [];
        }

        require_once $realPath;

        /** @var array<Target> $targets */
        $targets = array_reduce($tuples, function (array $targets, array $tuple) use ($realPath): array {
            $target = match ($tuple[0]) {
                'function' => $this->prepareFunctionProcess($tuple[1]),
                'class' => $this->prepareMethodsProcess($tuple[1]),
                default => throw new LogicException("Unable to prepare process for target type $tuple[1] in $realPath"),
            };

            if ([] === $target || null === $target) {
                return $targets;
            }

            if ($target instanceof Target) {
                $targets[] = $target;

                return $targets;
            }

            return array_merge($targets, $target);
        }, []);

        return $targets;
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
     * @throws ReflectionException
     *
     * @return list<Target>
     */
    private function prepareMethodsProcess(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $refClass = enum_exists($className) ? new ReflectionEnum($className) : new ReflectionClass($className);
        $targetRequiresConstructorArgs = !$refClass instanceof ReflectionEnum && (($refClass->getConstructor()?->getNumberOfRequiredParameters() ?? 0) !== 0);
        $isTargetInstantiated = false;
        $instance = null;
        $parentProfile = $this->findProfile($refClass);

        $results = [];
        foreach ($refClass->getMethods() as $method) {
            $profile = $this->findProfile($method) ?? $parentProfile;
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

            $results[] = new Target(
                callback: fn () => $method->invoke($method->isStatic() ? null : $instance),
                profile: $profile,
                source: $method,
            );
        }

        return $results;
    }

    private function prepareFunctionProcess(string $functionName): ?Target
    {
        if (!function_exists($functionName)) {
            return null;
        }

        $method = new ReflectionFunction($functionName);
        $profile = $this->findProfile($method);
        if (null === $profile) {
            return null;
        }

        if (0 !== $method->getNumberOfParameters()) {
            $this->logger->notice('The function '.$functionName.' located in '.$method->getFileName().' can not be profiled because it has arguments.', [
                'profile' => $profile,
                'method' => $functionName,
                'path' => $method->getFileName(),
            ]);
            return null;
        }

        return new Target(callback: $method->invoke(...), profile: $profile, source: $method);
    }
}
