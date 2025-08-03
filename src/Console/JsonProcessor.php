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
     * @param iterable<UnitOfWork> $unitOfWorks
     *
     * @throws Throwable
     */
    public function process(iterable $unitOfWorks): void
    {
        $path = null;
        foreach ($unitOfWorks as $unitOfWork) {
            $unitOfWork->run();
            $path ??= $unitOfWork->source->getFileName();
        }

        $this->exporter->writeln(null === $path ? [] : ['path' => $path, 'data' => $unitOfWorks]);
    }
}
