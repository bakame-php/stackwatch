<?php

declare(strict_types=1);

namespace Bakame\Stackwatch;

use Bakame\Stackwatch\Exporter\CallbackDumper;
use Bakame\Stackwatch\Exporter\Translator;
use Bakame\Stackwatch\Exporter\ViewExporter;
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
 *     type: string,
 *     iterations:int,
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
 *      type: string,
 *      iterations:string,
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
        public readonly MetricType $type,
        public readonly int $iterations,
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
    public static function fromValues(MetricType $type, array $values): self
    {
        $iterations = count($values);
        if (0 === $iterations) {
            return self::none($type);
        }

        $sum = array_sum($values);
        sort($values);
        $average = $sum / $iterations;
        $median = self::medianOf($values, $iterations);
        $variance = self::varianceOf($values, $average, $iterations);
        $stddev = sqrt($variance);
        $min = $values[0];
        $max = $values[$iterations - 1];

        return new self(
            type: $type,
            iterations: $iterations,
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

    public static function fromOne(MetricType $type, float $value): self
    {
        return new self(
            type: $type,
            iterations: 1,
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

    public static function none(MetricType $type): self
    {
        return new self(
            type: $type,
            iterations: 0,
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
     * @throws Throwable If the instance cannot be generated
     *
     */
    public static function fromArray(array $data): self
    {
        $missingKeys = array_diff_key([
            'type' => 1,
            'iterations' => 1,
            AggregationType::Minimum->value => 1,
            AggregationType::Maximum->value => 1,
            AggregationType::Range->value => 1,
            AggregationType::Sum->value => 1,
            AggregationType::Average->value => 1,
            AggregationType::Median->value => 1,
            AggregationType::Variance->value => 1,
            AggregationType::StdDev->value => 1,
            AggregationType::CoefVar->value => 1,
        ], $data);

        [] === $missingKeys || throw new InvalidArgument('The payload is missing the following keys: '.implode(', ', array_keys($missingKeys)));

        $type = MetricType::from($data['type']);

        try {
            return new self(
                type: $type,
                iterations: $data['iterations'],
                minimum: $data['minimum'],
                maximum:  $data['maximum'],
                range:  $data['range'],
                sum:  $data['sum'],
                average:  $data['average'],
                median:  $data['median'],
                variance:  $data['variance'],
                stdDev:  $data['std_dev'],
                coefVar:  $data['coef_var'],
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
            'type' => $this->type->value,
            'iterations' => $this->iterations,
            AggregationType::Minimum->value => $this->minimum,
            AggregationType::Maximum->value => $this->maximum,
            AggregationType::Range->value => $this->range,
            AggregationType::Sum->value => $this->sum,
            AggregationType::Average->value => $this->average,
            AggregationType::Median->value => $this->median,
            AggregationType::Variance->value => $this->variance,
            AggregationType::StdDev->value => $this->stdDev,
            AggregationType::CoefVar->value => $this->coefVar,
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
     * @return StatsHumanReadable
     */
    public function toHuman(): array
    {
        $unit = $this->type->unit();
        $humanType = (new Translator())->translate($this->type->value);

        return [
            'type' => $humanType,
            'iterations' => (string) $this->iterations,
            AggregationType::Minimum->value => $unit->format($this->minimum, 3),
            AggregationType::Maximum->value => $unit->format($this->maximum, 3),
            AggregationType::Range->value => $unit->format($this->range, 3),
            AggregationType::Sum->value => $unit->format($this->sum, 3),
            AggregationType::Average->value => $unit->format($this->average, 3),
            AggregationType::Median->value => $unit->format($this->median, 3),
            AggregationType::Variance->value => $unit->formatSquared($this->variance, 3),
            AggregationType::StdDev->value => $unit->format($this->stdDev, 3),
            AggregationType::CoefVar->value => number_format($this->coefVar * 100, 4).' %',
        ];
    }

    /**
     * Returns a human-readable version of a property.
     *
     * @throws InvalidArgument if the property is unknown
     */
    public function human(string $property): string
    {
        $humans = $this->toHuman();
        $propertyNormalized = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
        return $humans[$propertyNormalized] ?? throw new InvalidArgument('Unknown statistics name: "'.$property.'"; expected one of "'.implode('", "', array_keys($humans)).'"');
    }

    public function dump(): self
    {
        (new ViewExporter())->exportStatistics($this);

        return $this;
    }

    public function dd(): never
    {
        CallbackDumper::dd(fn () => $this->dump());
    }
}
