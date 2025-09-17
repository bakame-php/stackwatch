---
layout: default
title: Performance Helpers for PHPUnit
---

# PHPUnit Metrics Assertions Helper

A **fluent, unit-aware PHPUnit helper** for asserting profiler metrics from `stack_bench()`.

This helper provides:

 - Fluent API for metric assertions (executionTime, memoryUsage, etc.)
 - Unit conversions using MemoryUnit and DurationUnit enums
 - Optional configuration for **iterations and warmup**
 - Project-wide default configuration support
 - Relative assertions between metrics
 - Semantic consistency checks

## Installation

Include the `PerformanceAssertions` trait in your base test class:

```php
use Bakame\Stackwatch\Test\PerformanceAssertions;

abstract class PerformanceTestCase extends TestCase
{
    use PerformanceAssertions;

    protected function defaultMetricsConfig(): array
    {
        return [
            'iterations' => 5,
            'warmup'=> 1,
        ];
    }
}
```
Your `ReportAssert` and  `MetricsAssert` class must also be loaded.

## Usage

### Basic metric assertion

```php
$metrics = $this->assertAverage($service->calculateHeavyStuff(...));

$metrics->executionTime()->lessThan(200, 'ms');
$metrics->memoryUsage()->greaterThan(10, 'mb');
```

### Fluent configuration

```php
$this->iterations(5)
     ->warmup(2)
     ->assertMedian($service->calculateHeavyStuff(...))
     ->executionTime()->lessThan(200, 'ms')
     ->memoryUsage()->greaterThan(10, 'mb');
```
### Aggregation presets

- `->assertAverage()`
- `->assertMedian()`
- `->assertMin()`
- `->assertMax()`
- `->assertSum()`
- `->assertRange()`
- `->assertStdDev()`
- `->assertVariance()`
- `->assertCoefVar()`

Example:

```php
$this->iterations(10)
     ->warmup(2)
     ->assertAverage($service->calculateHeavyStuff(...))
     ->executionTime()->lessThan(150, 'ms');
```

### Asserting multiple aggregations

In some cases, you may want to evaluate several performance aggregations
(e.g., average, median) for the same callback.

Instead of running the profiler multiple times, you can use the `assertPerformance`
method.

This method collects multiple runs of the callback, then exposes helpers to
retrieve aggregated metrics:

```php
$performance = $this->iterations(5)
     ->warmup(2)
     ->assertPerformance($service->calculateHeavyStuff(...));
 
$average = $performance->average();
$average->executionTime()->lessThan(200, 'ms');

$median = $performance->median();
$median->memoryUsage()->greaterThan(10, 'mb');
```

In the example above:

`assertPerformance(...)` returns a `PerformanceResult` object, allowing you to 
query multiple aggregations (average, median, etc.).

### Asserting a single call

If you only need to validate a single execution of a callback, use the `assertOnce` method.
This skips aggregation and directly returns an `AssertMetrics` instance:

```php
$performance = $this->assertOnce($service->calculateHeavyStuff(...));
$performance->executionTime()->lessThan(200, 'ms');
$performance->memoryUsage()->greaterThan(10, 'mb');
```
This is useful when you only care about one measurement rather than aggregated statistics.

### Relative assertions

```php
$this->assertRange($service->calculateHeavyStuff(...))
     ->executionTime()->greaterThanMetric('cpuTime')
     ->peakMemoryUsage()->lessThanMetric('memoryUsage');
```

### Semantic consistency checks

```php
$this->assertVariance($service->calculateHeavyStuff(...))
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

| Method                | Description            |
|-----------------------|------------------------|
| `iterations(int)`     | Number of iterations   |
| `warmup(int)`         | Number of warmup runs  | 

### Assertion Helpers API

| Method                | Description                             |
|-----------------------|-----------------------------------------|
| `assertAverage()`     | Assert average metrics                  |
| `assertMedian()`      | Assert median metrics                   | 
| `assertMin()`         | Assert minimum metrics                  | 
| `assertMax()`         | Assert maximum metrics                  | 
| `assertSum()`         | Assert sum metrics                      |
| `assertRange()`       | Assert range metrics                    |
| `assertVariance()`    | Assert variance metrics                 |
| `assertStdDev()`      | Assert standard deviation metrics       |
| `assertCoefVar()`     | Assert coefficient of variation metrics |
| `assertPerformance()` | Give access to mutliple metrics         |
| `assertOnce()`        | Assert metrics for a single call        |


### Of Note

**Unit-aware:** Memory metrics default to **bytes**, time metrics default to **nanoseconds**. You can pass a
`MemoryUnit` or `DurationUnit` enums for conversions or a known string abbreviation for a duration or a memory
unit.

**Project-wide defaults:** Override `defaultMetricsConfig()` in your base test class to define default
iterations, warmup, and aggregation type.

**Fluent and safe:** Config resets automatically after each `assert*` call to prevent leaks between tests.
