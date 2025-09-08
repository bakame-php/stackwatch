---
layout: default
title: Performance Helpers for PHPUnit
---

# PHPUnit Metrics Assertions Helper

A **fluent, unit-aware PHPUnit helper** for asserting profiler metrics from `stack_bench()`.

This helper provides:

 - Fluent API for metric assertions (executionTime, memoryUsage, etc.)
 - Unit conversions using MemoryUnit and DurationUnit enums
 - Optional configuration for **iterations, warmup runs, and aggregation type**
 - Project-wide default configuration support
 - Relative assertions between metrics
 - Semantic consistency checks

## Installation

Include the `MetricsAssertions` trait in your base test class:

```php
use Bakame\Stackwatch\Test\MetricsAssertions;

abstract class PerformanceTestCase extends TestCase
{
    use MetricsAssertions;

    protected function defaultMetricsConfig(): array
    {
        return [
            'iterations' => 5,
            'warmup'=> 1,
            'type' => AggregationType::Median,
        ];
    }
}
```
Your `MetricsAssert` class must also be loaded.

## Usage

### Basic metric assertion

```php
$metrics = $this->assertMetrics($service->calculateHeavyStuff(...));

$metrics->executionTime()->lessThan(200, 'ms');
$metrics->memoryUsage()->greaterThan(10, 'mb');
```

### Fluent configuration

```php
$this->iter(5)
     ->warmup(2)
     ->aggMedian()
     ->assertMetrics($service->calculateHeavyStuff(...))
     ->executionTime()->lessThan(200, 'ms')
     ->memoryUsage()->greaterThan(10, 'mb');
```
### Aggregation presets

- `->aggAverage()`
- `->aggMedian()`
- `->aggMin()`
- `->aggMax()`
- `->aggSum()`
- `->aggRange()`
- `->aggStdDev()`
- `->aggVariance()`
- `->aggCoefVar()`

Example:

```php
$this->iter(10)
     ->warmup(2)
     ->aggAverage()
     ->assertMetrics($service->calculateHeavyStuff(...))
     ->executionTime()->lessThan(150, 'ms');
```

### Relative assertions

```php
$this->assertMetrics($service->calculateHeavyStuff(...))
     ->executionTime()->greaterThanMetric('cpuTime')
     ->peakMemoryUsage()->lessThanMetric('memoryUsage');
```

### Semantic consistency checks

```php
$this->assertMetrics($service->calculateHeavyStuff(...))
     ->assertConsistency()
     ->allNonNegative();
```

- `executionTime >= cpuTime`
- `peakMemoryUsage >= memoryUsage`
- `realPeakMemoryUsage >= realMemoryUsage`

### Assertions API

| Method                              | Description                                        |
|-------------------------------------|----------------------------------------------------|
| `executionTime()`                   | Selects the execution time metric                  |
| `cpuTime()`                         | Selects CPU time metric                            |
| `memoryUsage()`                     | Selects memory usage metric                        |
| `peakMemoryUsage()`                 | Selects peak memory usage metric                   |
| `realMemoryUsage()`                 | Selects real memory usage metric                   |
| `greaterThan(value, unit, message)` | Asserts metric > value, optionally converting unit |
| `lessThan(value, unit, message)`    | Asserts metric < value, optionally converting unit |
| `between(min, max, unit, message)`  | Asserts metric within min/max                      |
| `greaterThanMetric(otherMetric)`    | Asserts current metric > another metric            |
| `lessThanMetric(otherMetric)`       | Asserts current metric < another metric            |
| `assertConsistency()`               | Checks semantic relationships between metrics      |
| `allNonNegative()`                  | Ensures all metrics ≥ 0                            |

### Fluent configuration API

| Method                                     | Alias                          | Description                      |
|--------------------------------------------|--------------------------------|----------------------------------|
| `withIterations(int)`                      | `iter(int)`                    | Number of iterations             |
| `withWarmup(int)`                          | `warmup(int)`                  | Number of warmup runs            | 
| `withAggregation(?AggregationType = null)` | `agg(?AggregationType = null)` | Aggregation type                 |
| `aggAverage()`                             | —                              | AggregationType::Average preset  |
| `aggMedian()`                              | —                              | AggregationType::Median preset   | 
| `aggMin()`                                 | —                              | AggregationType::Min preset      | 
| `aggMax()`                                 | —                              | AggregationType::Max preset      | 
| `aggSum()`                                 | —                              | AggregationType::Sum preset      |
| `aggRange()`                               | —                              | AggregationType::Range preset    |
| `aggVariance()`                            | —                              | AggregationType::Variance preset |
| `aggStdDev()`                              | —                              | AggregationType::StdDev preset   |
| `aggCoefVar()`                             | —                              | AggregationType::CoefVar preset  |


### Of Note

**Unit-aware:** Memory metrics default to **bytes**, time metrics default to **nanoseconds**. You can pass a
`MemoryUnit` or `DurationUnit` enums for conversions or a known string abbreviation for a duration or a memory
unit.

**Project-wide defaults:** Override `defaultMetricsConfig()` in your base test class to define default
iterations, warmup, and aggregation type.

**Fluent and safe:** Config resets automatically after each `assertMetrics()` call to prevent leaks between tests.
