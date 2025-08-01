<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

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
     * @param iterable<Target> $targetList
     *
     * @throws Throwable
     */
    public function process(iterable $targetList): void
    {
        $json = [];
        $path = null;
        foreach ($targetList as $target) {
            $path ??= $target->source->getFileName();
            $json[] = array_merge($target->toArray(), ['attributes' => $target->generate()]);
        }

        $this->exporter->writeln(null === $path ? [] : ['path' => $path, 'data' => $json]);
    }
}
