<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function strtoupper;

final class Logger extends AbstractLogger
{
    public function __construct(private readonly OutputInterface $output = new ConsoleOutput())
    {
    }

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if (is_string($level)) {
            $this->output->writeln('['.strtoupper($level).'] '.$this->interpolate($message, $context));
        }
    }

    /**
     * @param array<array-key, mixed> $context
     */
    private function interpolate(string|Stringable $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{'.$key.'}'] = $value;
        }

        return strtr((string) $message, $replacements);
    }
}
