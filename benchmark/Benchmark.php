<?php

declare(strict_types=1);

use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\Environment;
use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Statistics;
use Bakame\Stackwatch\Timeline;
use Bakame\Stackwatch\Unit;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-type bench array{
 *     iterations: int<1, max>,
 *     warmup: int<0, max>,
 *     micro: Statistics,
 *     hrtime: Statistics,
 *     profiler: Statistics,
 *     marker: Statistics,
 *}
 */
final class Benchmark
{
    /**
     * @param int<1, max> $iterations
     */
    private static function microtime(callable $callback, int $iterations): Statistics
    {
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $callback();
            $times[] = microtime(true) - $start;
        }

        return Statistics::fromValues(Unit::Nanoseconds, array_map(DurationUnit::Second->convertToNano(...), $times));
    }

    /**
     * @param int<1, max> $iterations
     */
    private static function hrtime(callable $callback, int $iterations): Statistics
    {
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $callback();
            $times[] = hrtime(true) - $start;
        }

        return Statistics::fromValues(Unit::Nanoseconds, $times);
    }

    /**
     * @param int<1, max> $iterations
     */
    private static function marker(callable $callback, int $iterations): Statistics
    {
        $times = [];
        $timeline = new Timeline('test');
        for ($i = 0; $i < $iterations; $i++) {
            $timeline->capture('start');
            $callback();
            $times[] = $timeline->take('end')->metrics->executionTime;
            $timeline->reset();
        }

        return Statistics::fromValues(Unit::Nanoseconds, $times);
    }

    /**
     * @param int<1, max> $iterations
     */
    private static function profiler(callable $callback, int $iterations): Statistics
    {
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $times[] = Profiler::metrics($callback)->executionTime;
        }

        return Statistics::fromValues(Unit::Nanoseconds, $times);
    }

    /**
     * @param int<1, max> $iterations
     * @param int<0, max> $warmup
     *
     * @throws Throwable
     * @return bench
     */
    public static function run(callable $callback, int $iterations = 100, int $warmup = 0): array
    {
        1 <= $iterations || throw new ValueError('The iterations argument must be a positive integer greater than or equal to 1.');
        0 <= $warmup || throw new ValueError('The warmup must be zero or a positive integer.');

        if (0 < $warmup) {
            for ($i = 0; $i < $warmup; $i++) {
                $callback();
            }
        }

        return [
            'iterations' => $iterations,
            'warmup' => $warmup,
            'micro' => self::microtime($callback, $iterations),
            'hrtime' => self::hrtime($callback, $iterations),
            'profiler' => self::profiler($callback, $iterations),
            'marker' => self::marker($callback, $iterations),
        ];
    }

    /**
     * @param bench $benchmark
     *
     * @throws Throwable
     */
    public static function toConsole(array $benchmark, OutputInterface $output = new ConsoleOutput()): void
    {
        extract($benchmark);

        (new ConsoleExporter($output))->exportEnvironment(Environment::current());

        (new Table($output))
            ->setHeaderTitle("Execution time on $iterations iterations (warmup $warmup)")
            ->setHeaders([
                '',
                'Microtime',
                'Hrtime',
                'Profiler',
                'Timeline',
            ])
            ->addRow([
                'Average',
                $micro->human('average'),
                $hrtime->human('average'),
                $profiler->human('average'),
                $marker->human('average'),
            ])
            ->addRow([
                'Min',
                $micro->human('minimum'),
                $hrtime->human('minimum'),
                $profiler->human('minimum'),
                $marker->human('minimum'),
            ])
            ->addRow([
                'Max',
                $micro->human('maximum'),
                $hrtime->human('maximum'),
                $profiler->human('maximum'),
                $marker->human('maximum'),
            ])
            ->addRow([
                'Median',
                $micro->human('median'),
                $hrtime->human('median'),
                $profiler->human('median'),
                $marker->human('median'),
            ])
            ->addRow([
                'Standard Deviation',
                $micro->human('std_dev'),
                $hrtime->human('std_dev'),
                $profiler->human('std_dev'),
                $marker->human('std_dev'),
            ])
            ->addRow([
                'Coeff Variation',
                $micro->human('coef_var'),
                $hrtime->human('coef_var'),
                $profiler->human('coef_var'),
                $marker->human('coef_var'),
            ])
            ->render();

        (new Table($output))
            ->setHeaderTitle("Observed overhead on $iterations iterations")
            ->setHeaders([
                '',
                'Profiler vs microtime',
                'Profiler vs hrtime',
                'Timeline vs microtime',
                'Timeline vs hrtime',
            ])
            ->addRow([
                'Overhead',
                DurationUnit::format($profiler->average - $micro->average, 2),
                DurationUnit::format($profiler->average - $hrtime->average, 2),
                DurationUnit::format($marker->average - $micro->average, 2),
                DurationUnit::format($marker->average - $hrtime->average, 2),
            ])
            ->addRow([
                'Relative Error',
                number_format(($profiler->average - $micro->average) / $micro->average * 100, 4).' %',
                number_format(($profiler->average - $hrtime->average) / $hrtime->average * 100, 4).' %',
                number_format(($marker->average - $micro->average) / $micro->average * 100, 4).' %',
                number_format(($marker->average - $hrtime->average) / $hrtime->average * 100, 4).' %',
            ])
            ->render();
    }
}
