<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\JsonExporter;
use Throwable;

final class JsonFormatter implements Formatter
{
    public function __construct(public readonly JsonExporter $exporter)
    {
    }

    /**
     * @param iterable<UnitOfWork> $unitOfWorks
     *
     * @throws Throwable
     */
    public function format(iterable $unitOfWorks): void
    {
        $path = null;
        foreach ($unitOfWorks as $unitOfWork) {
            $path ??= $unitOfWork->path();
            break;
        }

        $this->exporter->writeln(null === $path ? [] : ['path' => $path, 'data' => $unitOfWorks]);
    }
}
