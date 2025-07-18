<?php

declare(strict_types=1);

use Bakame\Aide\Profiler\ConsoleTableExporter;
use Bakame\Aide\Profiler\DurationUnit;
use Bakame\Aide\Profiler\Environment;
use Bakame\Aide\Profiler\Marker;
use Bakame\Aide\Profiler\Profiler;
use Bakame\Aide\Profiler\Statistics;
use Bakame\Aide\Profiler\Unit;
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
        $marker = new Marker('test');
        for ($i = 0; $i < $iterations; $i++) {
            $marker->mark('start');
            $callback();
            $times[] = $marker->take('end')->metrics->executionTime;
            $marker->reset();
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
            $times[] = Profiler::executionTime($callback);
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

        (new ConsoleTableExporter($output))->exportEnvironment(Environment::current());

        (new Table($output))
            ->setHeaderTitle("Execution time on $iterations iterations (warmup $warmup)")
            ->setHeaders([
                '',
                'Microtime',
                'Hrtime',
                'Profiler',
                'Marker',
            ])
            ->addRow([
                'Average',
                $micro->forHuman('average'),
                $hrtime->forHuman('average'),
                $profiler->forHuman('average'),
                $marker->forHuman('average'),
            ])
            ->addRow([
                'Min',
                $micro->forHuman('min'),
                $hrtime->forHuman('min'),
                $profiler->forHuman('min'),
                $marker->forHuman('min'),
            ])
            ->addRow([
                'Max',
                $micro->forHuman('max'),
                $hrtime->forHuman('max'),
                $profiler->forHuman('max'),
                $marker->forHuman('max'),
            ])
            ->addRow([
                'Median',
                $micro->forHuman('median'),
                $hrtime->forHuman('median'),
                $profiler->forHuman('median'),
                $marker->forHuman('median'),
            ])
            ->addRow([
                'Standard Deviation',
                $micro->forHuman('std_dev'),
                $hrtime->forHuman('std_dev'),
                $profiler->forHuman('std_dev'),
                $marker->forHuman('std_dev'),
            ])
            ->addRow([
                'Coeff Variation',
                $micro->forHuman('coef_var'),
                $hrtime->forHuman('coef_var'),
                $profiler->forHuman('coef_var'),
                $marker->forHuman('coef_var'),
            ])
            ->render();

        (new Table($output))
            ->setHeaderTitle("Observed overhead on $iterations iterations")
            ->setHeaders([
                '',
                'Profiler vs microtime',
                'Profiler vs hrtime',
                'Marker vs microtime',
                'Marker vs hrtime',
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
