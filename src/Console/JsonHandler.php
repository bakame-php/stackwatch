<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Environment;
use Psr\Log\LoggerInterface;

use const JSON_BIGINT_AS_STRING;
use const JSON_PRETTY_PRINT;
use const STDOUT;

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
        $stream = $input->output ?? STDOUT;
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
