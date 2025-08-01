<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Throwable;

/**
 * @phpstan-import-type TargetList from PathProfiler
 */
final class ConsoleTableProcessor implements Processor
{
    public function __construct(public readonly ConsoleTableExporter $exporter)
    {
    }

    /**
     * @param iterable<Target> $targetList
     *
     * @throws Throwable
     */
    public function process(iterable $targetList): void
    {
        foreach ($targetList as $target) {
            $this->exporter->output->writeln($target->banner());
            $stats = $target->generate();
            $stats instanceof Metrics ? $this->exporter->exportMetrics($stats) : $this->exporter->exportReport($stats);
        }
    }
}
