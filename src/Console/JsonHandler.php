<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Environment;
use Psr\Log\LoggerInterface;

use function fopen;

use const JSON_BIGINT_AS_STRING;
use const JSON_PRETTY_PRINT;

final class JsonHandler implements Handler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Environment $environment,
    ) {
    }

    public function handle(Input $input): void
    {
        $jsonOptions = $input->jsonPrettyPrint->isEnabled()
            ? JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING
            : JSON_BIGINT_AS_STRING;
        /** @var resource $stream */
        $stream = $input->output ?? fopen('php://stdout', 'wb');
        $profiler = PathProfiler::forJson($input, $stream, $jsonOptions, $this->logger);
        if ($input->infoSection->isVisible()) {
            $formatter = $profiler->formatter;
            if ($formatter instanceof JsonFormatter) {
                $formatter->exporter->writeln($this->environment);
            }
        }

        $profiler->handle($input->path);
    }
}
