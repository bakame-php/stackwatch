<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Closure;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use SplFileInfo;
use Throwable;

/**
 * @phpstan-import-type TargetList from PathProfiler
 */
final class JsonProcessor implements Processor
{
    public function __construct(public readonly JsonExporter $exporter)
    {
    }

    /**
     * @param SplFileInfo|resource|string $path
     * @param ?resource $context
     */
    public static function fromStream(mixed $path, int $jsonOptions, $context = null): self
    {
        return new self(new JsonExporter($path, $jsonOptions, $context));
    }

    /**
     * @param TargetList $targetList
     *
     * @throws Throwable
     */
    public function process(iterable $targetList): void
    {
        $json = [];
        $path = null;
        foreach ($targetList as $target) {
            /**
             * @var Closure $closure
             * @var Profile $profile
             * @var ReflectionFunctionAbstract $method
             */
            ['closure' => $closure, 'profile' => $profile, 'method' => $method] = $target;
            $path ??= $method->getFileName();
            $data = [
                'type' => $profile->type,
                'iterations' => $profile->iterations,
                'warmup' => $profile->warmup,
            ];

            if ($method instanceof ReflectionMethod) {
                $data['class'] = $method->class;
                $data['method'] = $method->getName();
            } else {
                $data['function'] = $method->getName();
            }
            $data['attributes'] = Profile::DETAILED === $profile->type ? Profiler::report($closure, $profile->iterations, $profile->warmup) : Profiler::metrics($closure, $profile->iterations, $profile->warmup);
            $json[] = $data;
        }

        $this->exporter->writeln(null === $path ? [] : ['path' => $path, 'data' => $json]);
    }
}
