<?php

declare(strict_types=1);

namespace Bakame\Stackwatch\Console;

use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Exporter\LeaderPrinter;
use Bakame\Stackwatch\Metrics;
use Bakame\Stackwatch\Report;
use Throwable;

/**
 * @phpstan-import-type MetricsHumanReadable from Metrics
 */
final class ConsoleProcessor implements Processor
{
    public function __construct(public readonly ConsoleExporter $exporter)
    {
    }

    /**
     * @param iterable<UnitOfWork> $unitOfWorks
     *
     * @throws Throwable
     */
    public function process(iterable $unitOfWorks): void
    {
        $leaderPrinter = new LeaderPrinter(filler: '.', padExtra: 1);
        foreach ($unitOfWorks as $unitOfWork) {
            $unitOfWork->run();

            $stats = $unitOfWork->result();
            $this->exporter->output->writeln($unitOfWork->toConsoleString());
            $this->exporter->output->writeln('');
            if ($stats instanceof Report) {
                $this->exporter->exportReport($stats);
                $this->exporter->output->writeln('');
                continue;
            }

            /** @var MetricsHumanReadable $data */
            $data = $stats->forHuman();
            $this->exporter->output->writeln($leaderPrinter->render(self::exchangeKeys($data)));
            $this->exporter->output->writeln('');
        }
    }

    /**
     * @param MetricsHumanReadable $metrics
     *
     * @return array<string, string>
     */
    private static function exchangeKeys(array $metrics): array
    {
        static $humanKeys = [
            'cpu_time' => 'CPU Time',
            'memory_usage' => 'Memory Usage',
            'real_memory_usage' => 'Real Memory Usage',
            'peak_memory_usage' => 'Peak Memory Usage',
            'real_peak_memory_usage' => 'Real Peak Memory Usage',
            'execution_time' => 'Execution Time',
        ];

        $humans = [];
        foreach ($metrics as $key => $metric) {
            /** @var string $humanKey */
            $humanKey = $humanKeys[$key] ?? $key;
            $humans[ $humanKey] = $metric;
        }

        return $humans;
    }
}
