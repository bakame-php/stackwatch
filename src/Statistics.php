<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use JsonSerializable;
use Throwable;
use ValueError;

use function array_diff_key;
use function array_keys;
use function array_sum;
use function implode;
use function number_format;
use function preg_replace;
use function strtolower;

/**
 * Represents a detailed statistical summary of a numeric dataset.
 *
 * This class captures core statistical metrics like min, max, average, standard deviation,
 * coefficient of variation, etc., and formats them using the associated `Unit`.
 *
 * Use `Statistics::fromValues()` to compute the statistics from a dataset.
 * Use `Statistics::none()`to generate an empty/default profile.
 *
 * @phpstan-type StatsMap array{
 *     unit: string,
 *     count:int,
 *     minimum:float|int,
 *     maximum:float|int,
 *     range:float|int,
 *     sum:float|int,
 *     average:float,
 *     median: float,
 *     variance:float,
 *     coef_var:float,
 *     std_dev:float,
 * }
 *
 * @phpstan-type StatsHumanReadable array{
 *      count:string,
 *      minimum:string,
 *      maximum:string,
 *      range:string,
 *      sum:string,
 *      average:string,
 *      median: string,
 *      variance:string,
 *      coef_var:string,
 *      std_dev:string,
 * }
 *
 */
final class Statistics implements JsonSerializable
{
    public function __construct(
        public readonly Unit $unit,
        public readonly int $count,
        public readonly float|int $minimum,
        public readonly float|int $maximum,
        public readonly float|int $range,
        public readonly float|int $sum,
        public readonly float $average,
        public readonly float $median,
        public readonly float $variance,
        public readonly float $stdDev,
        public readonly float $coefVar,
    ) {
    }

    /**
     * @param list<float|int> $values
     *
     * @throws ValueError if the list of values is empty
     */
    public static function fromValues(Unit $unit, array $values): self
    {
        $count = count($values);
        if (0 === $count) {
            return self::none($unit);
        }

        $sum = array_sum($values);
        sort($values);
        $average = $sum / $count;
        $median = self::medianOf($values, $count);
        $variance = self::varianceOf($values, $average, $count);
        $stddev = sqrt($variance);
        $min = $values[0];
        $max = $values[$count - 1];

        return new self(
            unit: $unit,
            count: $count,
            minimum: $min,
            maximum: $max,
            range: $max - $min,
            sum: $sum,
            average: $average,
            median: $median,
            variance: $variance,
            stdDev: $stddev,
            coefVar: $average > 0.0 ? $stddev / $average : 0.0,
        );
    }

    public static function fromOne(Unit $unit, float $value): self
    {
        return new self(
            unit: $unit,
            count: 1,
            minimum: $value,
            maximum: $value,
            range: 0,
            sum: $value,
            average: $value,
            median: $value,
            variance: 0.0,
            stdDev: 0.0,
            coefVar: 0.0,
        );
    }

    public static function none(Unit $unit): self
    {
        return new self(
            unit: $unit,
            count: 0,
            minimum: 0,
            maximum: 0,
            range: 0,
            sum: 0,
            average: 0,
            median: 0,
            variance: 0.0,
            stdDev: 0.0,
            coefVar: 0.0,
        );
    }

    /**
     * @param StatsMap $data
     *
     * @throws Throwable If the instance can not be generated
     *
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key([
            'unit' => 1,
            'count' => 1,
            'minimum' => 1,
            'maximum' => 1,
            'range' => 1,
            'sum' => 1,
            'average' => 1,
            'median' => 1,
            'variance' => 1,
            'std_dev' => 1,
            'coef_var' => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        try {
            return new self(
                Unit::from($data['unit']),
                $data['count'],
                $data['minimum'],
                $data['maximum'],
                $data['range'],
                $data['sum'],
                $data['average'],
                $data['median'],
                $data['variance'],
                $data['std_dev'],
                $data['coef_var'],
            );
        } catch (Throwable $exception) {
            throw new InvalidArgument('Unable to create a '.self::class.' instance from the payload', previous: $exception);
        }
    }

    /**
     * @param list<float|int> $values
     */
    private static function medianOf(array $values, int $count): float
    {
        $middle = (int)($count / 2);

        return 0 === $count % 2
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }

    /**
     * @param list<float|int> $values
     */
    private static function varianceOf(array $values, float $average, int $count): float
    {
        return array_sum(array_map(
            fn ($x) => ($x - $average) ** 2,
            $values
        )) / $count;
    }

    /**
     * @return StatsMap
     */
    public function toArray(): array
    {
        return [
            'unit' => $this->unit->value,
            'count' => $this->count,
            'minimum' => $this->minimum,
            'maximum' => $this->maximum,
            'range' => $this->range,
            'sum' => $this->sum,
            'average' => $this->average,
            'median' => $this->median,
            'variance' => $this->variance,
            'std_dev' => $this->stdDev,
            'coef_var' => $this->coefVar,
        ];
    }

    /**
     * @return StatsMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns a human-readable version of the statistics.
     *
     * If a `$property` is specified, returns only the formatted value of that metric.
     *
     * @param 'count'|'minimum'|'maximum'|'range'|'sum'|'average'|'median'|'variance'|'std_dev'|'coef_var'|null $property
     *
     * @return StatsHumanReadable|string
     */
    public function forHuman(?string $property = null): array|string
    {
        $humans = [
             'count' => (string) $this->count,
             'minimum' => $this->unit->format($this->minimum, 3),
             'maximum' => $this->unit->format($this->maximum, 3),
             'range' => $this->unit->format($this->range, 3),
             'sum' => $this->unit->format($this->sum, 3),
             'average' => $this->unit->format($this->average, 3),
             'median' => $this->unit->format($this->median, 3),
             'variance' => $this->unit->formatSquared($this->variance, 3),
             'std_dev' => $this->unit->format($this->stdDev, 3),
             'coef_var' => number_format($this->coefVar * 100, 4).' %',
        ];

        if (null === $property) {
            return $humans;
        }

        $propertyNormalized = strtolower((string) preg_replace('/[\s_\-]+/', '_', $property));

        return $humans[$propertyNormalized] ?? throw new InvalidArgument('Unknown statistics name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
    }
}
