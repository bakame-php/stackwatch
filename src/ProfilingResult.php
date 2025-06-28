<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class ProfilingResult
{
    public function __construct(public readonly Profile $profile, public readonly mixed $value)
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
        $label = $label ?? Profile::randomLabel();
        try {
            $logger->info('Starting profiling for label: '.$label.'.');
            $start = Snapshot::now();
            $value = ($callback)(...$args);
            $end = Snapshot::now();
            $profile = new Profile($label, $start, $end);
            $logger->info('Finished profiling for label: '.$label.'.', $profile->stats());

            return new self($profile, $value);
        } catch (Throwable $exception) {
            $logger->error('Profiling aborted for label: {label} due to an error in the executed code.', ['label' => $label, 'exception' => $exception]);

            throw $exception;
        }
    }
}
