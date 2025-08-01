<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Closure;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * @phpstan-type TargetMap array{
 *      type: string,
 *      iterations: int,
 *      warmup: int,
 *      class?: class-string,
 *      method?: non-empty-string,
 *      function?: non-empty-string,
 *  }
 */
final class Target
{
    public function __construct(
        public readonly Closure $callback,
        public readonly Profile $profile,
        public readonly ReflectionFunctionAbstract $source,
    ) {
    }

    public function generate(): Report|Metrics
    {
        return Profile::DETAILED === $this->profile->type
            ? Profiler::report($this->callback, $this->profile->iterations, $this->profile->warmup)
            : Profiler::metrics($this->callback, $this->profile->iterations, $this->profile->warmup);
    }

    /**
     * @return TargetMap
     */
    public function toArray(): array
    {
        $data = $this->profile->toArray();
        if ($this->source instanceof ReflectionMethod) {
            $data['class'] = $this->source->class;
            $data['method'] = $this->source->getName();

            return $data;
        }

        $data['function'] = $this->source->getName();

        return $data;
    }

    public function banner(): string
    {
        if (Profile::DETAILED === $this->profile->type) {
            return match (true) {
                $this->source instanceof ReflectionMethod => 'Detailed metrics for the method <fg=green>'.$this->source->class.'::'.$this->source->getName().'</> located in <fg=green>'.$this->source->getFileName().'</> called <fg=yellow>'.$this->profile->iterations.'</> times',
                default => 'Detailed metrics for the function <fg=green>'.$this->source->getName().'</> located in <fg=green>'.$this->source->getFileName().'</> called <fg=yellow>'.$this->profile->iterations.'</> times',
            };
        }

        return match (true) {
            $this->source instanceof ReflectionMethod => 'Average metrics for the method <fg=green>'.$this->source->class.'::'.$this->source->getName().'</> located in <fg=green>'.$this->source->getFileName().'</> called <fg=yellow>'.$this->profile->iterations.'</> times',
            default => 'Average metrics for the function <fg=green>'.$this->source->getName().'</> located in <fg=green>'.$this->source->getFileName().'</> called <fg=yellow>'.$this->profile->iterations.'</> times',
        };
    }
}
