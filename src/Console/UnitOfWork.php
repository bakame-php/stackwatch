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
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;

use function file_exists;
use function function_exists;
use function is_readable;
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
 *      tags: list<non-empty-string>,
 *      path: string,
 *      class?: class-string|null,
 *      method?: non-empty-string|null,
 *      function?: non-empty-string|null,
 *      run_at: ?string,
 *      attributes: MetricsStat|ReportMap|array{}
 *  }
 */
final class UnitOfWork implements JsonSerializable
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    private Profile $profile;
    private string $path;
    /** @var ?class-string  */
    private ?string $class;
    /** @var ?non-empty-string */
    private ?string $method;
    /** @var ?non-empty-string */
    private ?string $function;
    private ?string $name = null;
    private ?string $template = null;
    private Report|Metrics|null $result = null;
    private ?DateTimeImmutable $runAt = null;

    public function __construct(Profile $profile, ReflectionFunctionAbstract $target)
    {
        $path = $target->getFileName();
        false !== $path || throw new InvalidArgument('Target profile must not be an internal function or method.');

        $this->profile = $profile;
        $this->path = $path;
        [
            'class' => $this->class,
            'method' => $this->method,
            'function' => $this->function,
        ] = $this->extractInfo($target);
    }

    /**
     *
     * @return array{class: ?class-string, method: ?non-empty-string, function: ?non-empty-string}
     */
    private function extractInfo(ReflectionFunctionAbstract $target): array
    {
        $class = null;
        $method = null;
        $function = $target->getName();
        if ($target instanceof ReflectionMethod) {
            $class = $target->getDeclaringClass()->getName();
            $method = $target->getName();
            $function = null;
        }

        return [
            'class' => $class,
            'method' => $method,
            'function' => $function,
        ];
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
        isset($data['path']) || throw new InvalidArgument('The data is missing "path" key');
        (file_exists($data['path']) && is_readable($data['path'])) || throw new InvalidArgument('The path '.$data['path'].' does not exist or is not readable.');

        try {
            $profile = Profile::fromArray($data);
            if ([] !== $data['attributes'] && isset($data['run_at'])) {
                $runAt = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $data['run_at']);
                false !== $runAt || throw new InvalidArgument('The run_at must be a valid date.');
                $reflection = new ReflectionClass(UnitOfWork::class);
                $unitOfWork = $reflection->newInstanceWithoutConstructor();
                $unitOfWork->runAt = $runAt;
                $unitOfWork->result = match ($profile->type) {
                    Profile::DETAILED => Report::fromArray($data['attributes']), /* @phpstan-ignore-line */
                    Profile::SUMMARY => Metrics::fromArray($data['attributes']), /* @phpstan-ignore-line */
                    default => throw new InvalidArgument('The profile type '.$profile->type.' is not valid.'),
                };

                $unitOfWork->path = $data['path'];
                $unitOfWork->profile = $profile;
                $unitOfWork->function = $data['function'] ?? null;
                $unitOfWork->method = $data['method'] ?? null;
                $unitOfWork->class = $data['class'] ?? null;

                return $unitOfWork;
            }

            require_once $data['path'];

            if (isset($data['function'])) {
                $source = new ReflectionFunction($data['function']);
                0 === $source->getNumberOfRequiredParameters() || throw new InvalidArgument('The '.$source->getName().' function cannot be profiled because it has arguments.');

                return new self(profile: $profile, target: $source);
            }

            isset($data['class']) || throw new InvalidArgument('The data is missing the "class" key');
            $refClass = enum_exists($data['class']) ? new ReflectionEnum($data['class']) : new ReflectionClass($data['class']);

            isset($data['method']) || throw new InvalidArgument('The data is missing the "method" key');
            $method = $refClass->getMethod($data['method']);

            ! $method->isAbstract() || throw new InvalidArgument('The '.$refClass->getName().'::'.$method->getName().' method cannot be profiled because it is abstract.');
            0 === $method->getNumberOfParameters() || throw new InvalidArgument('The '.$refClass->getName().'::'.$method->getName().' method cannot be profiled because it has arguments.');

            if (!$method->isStatic()) {
                $refClass instanceof ReflectionEnum
                || (0 === ($refClass->getConstructor()?->getNumberOfRequiredParameters() ?? 0))
                || throw new InvalidArgument('The non-static method '.$refClass->getName().'::'.$method->getName().' located in '.$method->getFileName().' can not be profiled because the class requires constructor arguments.');

                if ($refClass instanceof ReflectionEnum) {
                    $cases = $refClass->getCases();
                    [] !== $cases || throw new InvalidArgument('Enum '.$data['class'].' has no cases');
                } elseif (($refClass->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
                    throw new InvalidArgument('The non-static method '.$refClass->getName().'::'.$method->getName().' located in '.$method->getFileName().' cannot be profiled because the class requires constructor arguments.');
                }
            }

            $unitOfWork = new self(profile: $profile, target: $method);
            if ([] === $data['attributes'] && null === $data['run_at']) {
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

    private function callback(): Closure
    {
        (file_exists($this->path) && is_readable($this->path)) || throw new UnableToProfile('The path '.$this->path.' does not exist or is not readable.');

        require_once $this->path;

        if (null !== $this->function) {
            function_exists($this->function) || throw new UnableToProfile('The function '.$this->function.' is not available.');
            $ref = new ReflectionFunction($this->function);
            0 === $ref->getNumberOfRequiredParameters() || throw new UnableToProfile('The '.$this->function.' function cannot be profiled because it has required parameters.');

            return fn () => $ref->invoke();
        }

        (null !== $this->class && null !== $this->method) || throw new UnableToProfile('The '.$this->class.'::'.$this->function.' method cannot be profiled because it was not found.');
        $refClass = enum_exists($this->class) ? new ReflectionEnum($this->class) : new ReflectionClass($this->class);
        try {
            $refMethod = $refClass->getMethod($this->method);
        } catch (ReflectionException $exception) {
            throw new UnableToProfile('The method '.$this->class.'::'.$this->method.' was not found.', previous: $exception);
        }

        !$refMethod->isAbstract() || throw new UnableToProfile('The method '.$this->class.'::'.$this->method.' is abstract.');
        0 === $refMethod->getNumberOfRequiredParameters() || throw new UnableToProfile('The method '.$this->class.'::'.$this->method.' cannot be profiled because it has required parameters.');

        if ($refMethod->isStatic()) {
            return fn () => $refMethod->invoke(null);
        }

        $instance = $refClass instanceof ReflectionEnum ? $refClass->getCases()[0]->getValue() : $refClass->newInstance();

        return fn () => $refMethod->invoke($instance);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function profile(): Profile
    {
        return $this->profile;
    }

    /**
     * @return ?class-string
     */
    public function class(): ?string
    {
        return $this->class;
    }

    /**
     * @return ?non-empty-string
     */
    public function method(): ?string
    {
        return $this->method;
    }

    /**
     * @return ?non-empty-string
     */
    public function function(): ?string
    {
        return $this->function;
    }

    public function run(): void
    {
        if (!$this->hasRun()) {
            $callback = $this->callback();
            $this->runAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $this->result = Profile::DETAILED === $this->profile->type
                ? Profiler::report($callback, $this->profile->iterations, $this->profile->warmup)
                : Profiler::metrics($callback, $this->profile->iterations, $this->profile->warmup);
        }
    }

    public function hasRun(): bool
    {
        return null !== $this->result
            && null !== $this->runAt;
    }

    public function result(): Report|Metrics
    {
        null !== $this->result || throw new UnableToProfile('The Unit of Work '.$this->toPlainString().' has not run yet.');

        return $this->result;
    }

    public function runAt(): DateTimeImmutable
    {
        null !== $this->runAt || throw new UnableToProfile('The Unit of Work '.$this->toPlainString().' has not run yet.');

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
        $data['path'] = $this->path;
        if (null !== $this->class && null !== $this->method) {
            $data['class'] = $this->class;
            $data['method'] = $this->method;
        } else {
            $data['function'] = $this->function;
        }

        $data['run_at'] = $this->runAt?->format(self::DATE_FORMAT);
        $data['attributes'] = null !== $this->result ? $this->result->toArray() : [];

        return $data;
    }

    public function toConsoleString(): string
    {
        return strtr($this->template(), [
            '{name}' => '<fg=green>'.$this->name().'</>',
            '{file}' => '<fg=green>'.$this->path.'</>',
            '{iterations}' => '<fg=yellow>'.$this->profile->iterations.'</>',
            '{warmup}' => '<fg=yellow>'.$this->profile->warmup.'</>',
        ]);
    }

    public function toPlainString(): string
    {
        return strtr($this->template(), [
            '{name}' => $this->name(),
            '{file}' => $this->path,
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
        $this->name ??= $this->function ?? $this->class.'::'.$this->method;

        return $this->name;
    }
}
