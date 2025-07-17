<?php

declare(strict_types=1);

/**
 * @phpstan-type StatsMap array{
 *     count:int,
 *     min:float,
 *     max:float,
 *     average:float,
 *     median: float,
 *     variance:float,
 *     coefficient_variation:float,
 *     standard_deviation:float,
 * }
 */
final class Statistics implements JsonSerializable
{
    public function __construct(
        public readonly int $count,
        public readonly float $min,
        public readonly float $max,
        public readonly float $average,
        public readonly float $median,
        public readonly float $variance,
        public readonly float $standardDeviation,
        public readonly float $coefficientVariation,
    ) {
    }

    /**
     * @param list<float|int> $values
     *
     * @throws ValueError if the list of values is empty
     */
    public static function fromValues(array $values): self
    {
        $count = count($values);
        0 !== $count || throw new ValueError('Cannot compute statistics from an empty list of values.');

        sort($values);
        $average = array_sum($values) / $count;
        $middle = (int) ($count / 2);
        $median = 0 === $count % 2 ? ($values[$middle - 1] + $values[$middle]) / 2 : $values[$middle];
        $variance = array_sum(array_map(fn ($x) => ($x - $average) ** 2, $values)) / $count;
        $stddev = sqrt($variance);

        return new self(
            count: $count,
            min: $values[0],
            max: $values[$count - 1],
            average: $average,
            median: $median,
            variance: $variance,
            standardDeviation: $stddev,
            coefficientVariation: $average > 0.0 ? $stddev / $average : 0.0,
        );
    }

    /**
     * @return StatsMap
     */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'min' => $this->min,
            'max' => $this->max,
            'average' => $this->average,
            'median' => $this->median,
            'variance' => $this->variance,
            'standard_deviation' => $this->standardDeviation,
            'coefficient_variation' => $this->coefficientVariation,
        ];
    }

    /**
     * @return StatsMap
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
