<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\InvalidArgument;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Profile;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Report;
use Bakame\Stackwatch\UnableToProfile;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use JsonSerializable;
use ReflectionClass;
use ReflectionEnum;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;

use function json_decode;
use function strtr;

use const JSON_THROW_ON_ERROR;

/**
 * @phpstan-import-type MetricsStat from Metrics
 * @phpstan-import-type ReportMap from Report
 * @phpstan-type UnitOfWorkMap array{
 *      type: 'detailed'|'summary',
 *      iterations: int<1, max>,
 *      warmup: int<0, max>,
 *      class?: class-string,
 *      method?: non-empty-string,
 *      function?: non-empty-string,
 *      run_at: ?string,
 *      attributes: MetricsStat|ReportMap|array{}
 *  }
 */
final class UnitOfWork implements JsonSerializable
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    private ?string $name = null;
    private ?string $template = null;
    private Report|Metrics|null $result = null;
    private ?DateTimeImmutable $runAt = null;

    public function __construct(
        public readonly Closure $callback,
        public readonly Profile $profile,
        public readonly ReflectionFunctionAbstract $source,
    ) {
    }

    /**
     * @param non-empty-string $json
     *
     * @throws InvalidArgument
     */
    public static function fromJson(string $json): self
    {
        try {
            /** @var UnitOfWorkMap $data */
            $data = json_decode(json: $json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgument('Unable to parse the given JSON: '.$exception->getMessage(), previous: $exception);
        }

        return self::fromArray($data);
    }

    /**
     * @param UnitOfWorkMap $data
     *
     * @throws InvalidArgument
     */
    public static function fromArray(array $data): self
    {
        isset($data['class']) || isset($data['function']) || throw new InvalidArgument('The data is missing "class" or "function" key');
        if (
            isset($data['class']) && !isset($data['method']) ||
            isset($data['metjod']) && !isset($data['class'])
        ) {
            throw new InvalidArgument('The data is missing "method" key');
        }

        try {
            $profile = Profile::fromArray($data);
            if (isset($data['function'])) {
                $source = new ReflectionFunction($data['function']);
                0 === $source->getNumberOfParameters() || throw new InvalidArgument('The '.$source->getName().' function cannot be profiled because it has arguments.');

                return new self(callback: $source->invoke(...), profile: $profile, source: $source);
            }

            $refClass = enum_exists($data['class']) ? new ReflectionEnum($data['class']) : new ReflectionClass($data['class']);
            $method = $refClass->getMethod($data['method']);
            ! $method->isAbstract() || throw new InvalidArgument('The '.$refClass->getName().'::'.$method->getName().' method cannot be profiled because it is abstract.');
            0 === $method->getNumberOfParameters() || throw new InvalidArgument('The '.$refClass->getName().'::'.$method->getName().' method cannot be profiled because it has arguments.');

            $instance = null;
            if (!$method->isStatic()) {
                $refClass instanceof ReflectionEnum
                || (0 === ($refClass->getConstructor()?->getNumberOfRequiredParameters() ?? 0))
                || throw new InvalidArgument('The non-static method '.$refClass->getName().'::'.$method->getName().' located in '.$method->getFileName().' can not be profiled because the class requires constructor arguments.');

                if ($refClass instanceof ReflectionEnum) {
                    $cases = $refClass->getCases();
                    [] !== $cases || throw new InvalidArgument('Enum '.$data['class'].' has no cases');
                    $instance = $cases[0]->getValue();
                } else {
                    if (($refClass->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
                        throw new InvalidArgument('The non-static method '.$refClass->getName().'::'.$method->getName().' located in '.$method->getFileName().' cannot be profiled because the class requires constructor arguments.');
                    }
                    $instance = $refClass->newInstance();
                }
            }

            $unitOfWork = new self(callback: fn () => $method->invoke($instance), profile: $profile, source: $method);
            if ([] === $data['attributes'] && null === $data['run_at']) {
                return $unitOfWork;
            }

            if ([] !== $data['attributes'] && isset($data['run_at'])) {
                $runAt = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $data['run_at']);
                false !== $runAt || throw new InvalidArgument('The run_at must be a valid date.');
                $unitOfWork->runAt = $runAt;
                $unitOfWork->result = match ($unitOfWork->profile->type) {
                    Profile::DETAILED => Report::fromArray($data['attributes']), /* @phpstan-ignore-line */
                    Profile::SUMMARY => Metrics::fromArray($data['attributes']), /* @phpstan-ignore-line */
                    default => throw new InvalidArgument('The profile type `'.$unitOfWork->profile->type.'` is not valid.'),
                };

                return $unitOfWork;
            }

            throw new InvalidArgument('The data is missing "attributes" or "run_at" key');

        } catch (Throwable $exception) {
            if ($exception instanceof InvalidArgument) {
                throw $exception;
            }

            throw new InvalidArgument('Unable to generate target for the submitted data', previous: $exception);
        }
    }

    public function run(): void
    {
        if (!$this->hasRun()) {
            $this->runAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $this->result = Profile::DETAILED === $this->profile->type
                ? Profiler::report($this->callback, $this->profile->iterations, $this->profile->warmup)
                : Profiler::metrics($this->callback, $this->profile->iterations, $this->profile->warmup);
        }
    }

    public function hasRun(): bool
    {
        return null !== $this->result
            && null !== $this->runAt;
    }

    public function result(): Report|Metrics
    {
        null !== $this->result || throw new UnableToProfile('The Unit of Work `'.$this->toPlainString().'` has not run yet.');

        return $this->result;
    }

    public function runAt(): DateTimeImmutable
    {
        null !== $this->runAt || throw new UnableToProfile('The Unit of Work `'.$this->toPlainString().'` has not run yet.');

        return $this->runAt;
    }

    /**
     * @return UnitOfWorkMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return UnitOfWorkMap
     */
    public function toArray(): array
    {
        $data = $this->profile->toArray();
        if ($this->source instanceof ReflectionMethod) {
            $data['class'] = $this->source->class;
            $data['method'] = $this->source->getName();
        } else {
            $data['function'] = $this->source->getName();
        }

        $data['run_at'] = $this->runAt?->format(self::DATE_FORMAT);
        $data['attributes'] = null !== $this->result ? $this->result->toArray() : [];

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
