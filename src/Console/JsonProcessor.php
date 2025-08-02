<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\JsonExporter;
use Throwable;

final class JsonProcessor implements Processor
{
    public function __construct(public readonly JsonExporter $exporter)
    {
    }

    /**
     * @param iterable<Target> $targets
     *
     * @throws Throwable
     */
    public function process(iterable $targets): void
    {
        $json = [];
        $path = null;
        foreach ($targets as $target) {
            $path ??= $target->source->getFileName();
            $json[] = array_merge($target->toArray(), ['attributes' => $target->generate()]);
        }

        $this->exporter->writeln(null === $path ? [] : ['path' => $path, 'data' => $json]);
    }
}
