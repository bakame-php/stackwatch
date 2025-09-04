<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use function get_resource_type;
use function is_resource;

final class StreamWriter
{
    /** @var resource */
    private $output;

    /**
     * @param resource|null $output
     */
    public function __construct(mixed $output = null)
    {
        if (null === $output) {
            /** @var resource $output */
            $output = Warning::trap(fopen(...), 'php://stdout', 'wb');
        }

        (is_resource($output) && 'stream' === get_resource_type($output)) || throw new InvalidArgument('The output must be a stream resource.');
        $this->output = $output;
    }

    public function write(string $content): void
    {
        Warning::trap(fwrite(...), $this->output, $content);
    }

    public function writeln(string $content): void
    {
        $this->write($content."\n");
    }
}
