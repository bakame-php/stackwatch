<?php

declare(strict_types=1);

namespace Bakame\Aide\Profiler;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ProfilingResult
{
    public function __construct(
        public readonly Profile $profile,
        public readonly mixed $value
    ) {
    }

    /**
     * @throws \Random\RandomException
     */
    public static function profile(
        string $label,
        callable $callback,
        LoggerInterface $logger = new NullLogger(),
        mixed ...$args
    ): self {
        $logger->info('Starting profiling for label: '.$label.'.');
        $start = Snapshot::now();
        $value = ($callback)(...$args);
        $end = Snapshot::now();
        $profile = new Profile($label, $start, $end);
        $logger->info('Finished profiling for label: '.$label.'.', $profile->stats());

        return new self($profile, $value);
    }
}
