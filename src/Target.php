<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Closure;
use ReflectionFunctionAbstract;
use ReflectionMethod;

use function strtr;

/**
 * @phpstan-type TargetMap array{
 *      type: string,
 *      iterations: int<1, max>,
 *      warmup: int<0, max>,
 *      class?: class-string,
 *      method?: non-empty-string,
 *      function?: non-empty-string,
 *  }
 */
final class Target
{
    private ?string $name = null;
    private ?string $template = null;

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

    public function toConsoleString(): string
    {
        return strtr($this->template(), [
            '{name}' => '<fg=green>'.$this->name().'</>',
            '{file}' => '<fg=green>'.$this->source->getFileName().'</>',
            '{iterations}' => '<fg=yellow>'.$this->profile->iterations.'</>',
            '{warmup}' => '<fg=yellow>'.$this->profile->warmup.'</>',
        ]);
    }

    public function toPlainString(): string
    {
        return strtr($this->template(), [
            '{name}' => $this->name(),
            '{file}' => $this->source->getFileName(),
            '{iterations}' => $this->profile->iterations,
            '{warmup}' => $this->profile->warmup,
        ]);
    }

    private function template(): string
    {
        $this->template ??= Profile::DETAILED === $this->profile->type
            ? 'Detailed metrics for {name} located in {file} after {iterations} iterations and {warmup} warmups'
            : 'Average metrics for {name} located in {file} after {iterations} iterations and {warmup} warmups';

        return $this->template;
    }

    private function name(): string
    {
        $this->name ??= $this->source instanceof ReflectionMethod
            ? $this->source->class.'::'.$this->source->getName()
            : $this->source->getName();

        return $this->name;
    }
}
