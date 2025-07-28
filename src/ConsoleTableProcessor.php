<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use ReflectionMethod;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @phpstan-import-type TargetList from PathProfiler
 */
final class ConsoleTableProcessor implements Processor
{
    public function __construct(public readonly ConsoleTableExporter $exporter)
    {
    }

    public static function fromOutput(OutputInterface $stdout = new ConsoleOutput()): self
    {
        return new self(new ConsoleTableExporter($stdout));
    }

    /**
     * @param TargetList $targetList
     *
     * @throws Throwable
     */
    public function process(iterable $targetList): void
    {
        foreach ($targetList as $target) {
            ['closure' => $closure, 'profile' => $profile, 'method' => $method] = $target;
            if (Profile::REPORT === $profile->type) {
                $text = match (true) {
                    $method instanceof ReflectionMethod => 'Report for the method <fg=green>'.$method->class.'::'.$method->getName().'</> located in <fg=green>'.$method->getFileName().'</> called <fg=yellow>'.$profile->iterations.'</> times',
                    default => 'Report for the function <fg=green>'.$method->getName().'</> located in <fg=green>'.$method->getFileName().'</> called <fg=yellow>'.$profile->iterations.'</> times',
                };

                $this->exporter->output->writeln($text);
                $this->exporter->exportReport(Profiler::report($closure, $profile->iterations, $profile->warmup));

                continue;
            }

            $text = match (true) {
                $method instanceof ReflectionMethod => 'Average metrics for the method <fg=green>'.$method->class.'::'.$method->getName().'</> located in <fg=green>'.$method->getFileName().'</> called <fg=yellow>'.$profile->iterations.'</> times',
                default => 'Average metrics for the function <fg=green>'.$method->getName().'</> located in <fg=green>'.$method->getFileName().'</> called <fg=yellow>'.$profile->iterations.'</> times',
            };

            $this->exporter->output->writeln($text);
            $this->exporter->exportMetrics(Profiler::metrics($closure, $profile->iterations, $profile->warmup));
        }
    }
}
