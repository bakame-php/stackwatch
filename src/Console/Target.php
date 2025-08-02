<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\InvalidArgument;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Profile;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Report;
use Closure;
use ReflectionClass;
use ReflectionEnum;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;

use function strtr;

/**
 * @phpstan-type TargetMap array{
 *      type: 'detailed'|'summary',
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

    /**
     * @param TargetMap $data
     *
     * @throws InvalidArgument
     */
    public static function fromArray(array $data): self
    {
        isset($data['class']) || isset($data['function']) || throw new InvalidArgument('The data is missing "class" or "function" key');
        (isset($data['class']) && !isset($data['method'])) || throw new InvalidArgument('The data is missing "method" key');

        try {
            $profile = Profile::fromArray($data);
            if (isset($data['function'])) {
                $source = new ReflectionFunction($data['function']);

                return new self(callback: $source->invoke(...), profile: $profile, source: $source);
            }

            $refClass = new ReflectionClass($data['class']);
            $method = $refClass->getMethod($data['method']);
            $instance = null;
            if (!$method->isStatic()) {
                $instance = $refClass instanceof ReflectionEnum ? ($refClass->getCases()[0]->getValue() ?? throw new InvalidArgument('Enum '.$data['class'].' has no cases')) : $refClass->newInstance();
            }

            return new self(
                callback: fn () => $method->invoke($instance),
                profile: $profile,
                source: $method,
            );
        } catch (Throwable $exception) {
            if ($exception instanceof InvalidArgument) {
                throw $exception;
            }

            throw new InvalidArgument('Unable to generate target for the submitted data', previous: $exception);
        }
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
