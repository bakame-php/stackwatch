<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

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
            ['closure' => $closure, 'profile' => $profile, 'method' => $method] = $target;
            if (null === $path) {
                $path = $method->getFileName();
            }
            $data = [
                'type' => Profile::DETAILED === $profile->type ? 'report' : 'average',
                'iterations' => $profile->iterations,
                'path' => $method->getFileName(),
            ];

            if ($method instanceof ReflectionMethod) {
                $data['class_name'] = $method->class;
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
