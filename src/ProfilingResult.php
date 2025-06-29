<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use JsonSerializable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function gc_collect_cycles;

final class ProfilingResult implements JsonSerializable
{
    public function __construct(public readonly mixed $result, public readonly ProfilingData $profilingData)
    {
    }

    /**
     * @throws Throwable
     */
    public static function profile(
        ?string $label,
        callable $callback,
        LoggerInterface $logger = new NullLogger(),
        mixed ...$args
    ): self {
        gc_collect_cycles();

        $label = $label ?? ProfilingData::randomLabel();
        try {
            $logger->info('Starting profiling for label: '.$label.'.', ['label' => $label]);
            $start = Snapshot::now();
            $result = ($callback)(...$args);
            $end = Snapshot::now();
            $profilingData = new ProfilingData($label, $start, $end);
            $logger->info('Finished profiling for label: '.$label.'.', $profilingData->stats());

            return new self($result, $profilingData);
        } catch (Throwable $exception) {
            $logger->error('Profiling aborted for label: '.$label.' due to an error in the executed code.', ['label' => $label, 'exception' => $exception]);

            throw $exception;
        }
    }

    public function jsonSerialize(): ProfilingData
    {
        return $this->profilingData;
    }
}
