<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\JsonExporter;
use Throwable;

final class JsonProcessor implements Processor
{
    public function __construct(public readonly JsonExporter $exporter, public readonly State $dryRun = State::Disabled)
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
            if (State::Disabled === $this->dryRun) {
                $unitOfWork->run();
            }
            $path ??= $unitOfWork->path();
        }

        $this->exporter->writeln(null === $path ? [] : ['path' => $path, 'data' => $unitOfWorks]);
    }
}
