---
layout: default
title: Scoped Execution Profiling
---

# Profiler

Traditionally, profiling a section of code quickly looks like this:

```php
$start = microtime(true);
$service->calculateHeavyStuff();
echo microtime(true) - $start; // the execution time of your code
```

## Metrics quick access

Let's adapt the first example using the <code>Profiler</code> class.

```php
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\DurationUnit;

// $duration is the execution time in nanosecond using hrtime instead of microtime
$duration = Profiler::metrics(
    $service->calculateHeavyStuff(...)
)->executionTime;

// Convert the value to a more readable form using DurationUnit
echo DurationUnit::format($duration); //returns "1.271 ms"
````

The method returns a `Metrics` class with readonly properties for each metric.

```php
use Bakame\Stackwatch\Profiler;

// you create a new Profiler by passing the callback you want to profile
$metrics = Profiler::metrics($service->calculateHeavyStuff(...));

$metrics->executionTime;
$metrics->cpuTime; 
$metrics->memoryUsage;
$metrics->memoryUsageGrowth;
$metrics->peakMemoryUsage;
$metrics->peakMemoryUsageGrowth;
$metrics->realMemoryUsage;
$metrics->realMemoryUsageGrowth;
$metrics->realPeakMemoryUsage;
$metrics->realPeakMemoryUsageGrowth;
````

All duration values are expressed in nanoseconds, while memory-related metrics are measured in bytes. You can retrieve
the `Metrics` statistics in a human-readable format using the instance `toHuman()` and `human` methods.
You can either:

- Call the `toHuman()` to retrieve **all metrics** as formatted strings in an associative `array`.
- Or pass the name of a specific metric to `human()` to retrieve **only that value**, formatted for human readability.

```php
use Bakame\Stackwatch\Profiler;

// you create a new Profiler by passing the callback you want to profile
$metrics = Profiler::metrics($service->calculateHeavyStuff(...));

$metrics->toHuman();
// returns 
// [
//   "cpu_time" => "30.000 µs"
//   "execution_time" => "1.271 ms"
//   "memory_usage" => "2.5 KB"
//   "real_memory_usage" => "0.0 B"
//   "peak_memory_usage" => "0.0 B"
//   "real_peak_memory_usage" => "0.0 B"
// ]

$metrics->human('memory_usage'); //returns "2.5 KB"
```

## Iterations

To calculate the average usage of a specific metric, specify the number of iterations as the second
argument. The callback will be executed accordingly, and the method will return the average
value over all iterations:

```php
use Bakame\Stackwatch\Profiler;

$cpuTime = Profiler::metrics($service->calculateHeavyStuff(...), 5)->cpuTime;
// the average CPU Time used when executing 5 times the code.
````

## Warm-up Iterations

In some cases, your code may require a few "warm-up" runs before profiling is meaningful—for example, to resolve
dependencies, initialize caches, or handle complex setup.
To account for this, you can specify the number of warm-up iterations as the **third argument** to `Profiler::metrics()`.
These warm-up runs will be executed but **not recorded** in the profiling results:

```php
use Bakame\Stackwatch\Profiler;

$cpuTime = Profiler::metrics($service->calculateHeavyStuff(...), 5, 2)->cpuTime;
// the average CPU Time used when executing 5 times the code.
// after two iterations of warmup which are not recorded
````

## Aggregation Type

By default, the metrics method returns the **average** value across all recorded iterations.
In some scenarios, however, a different aggregation may provide better insight—for example, using the **median** to reduce the effect of outliers.

You can control this behavior by passing an `AggregatorType` enum as the **fourth argument**:

```php
use Bakame\Stackwatch\AggregatorType;
use Bakame\Stackwatch\Profiler;

$cpuTime = Profiler::metrics(
  callback: $service->calculateHeavyStuff(...), 
  iterations: 5, 
  type: AggregatorType::Median
)->cpuTime;
// the median CPU Time used when executing 5 times the code is now returned
````

### Available Aggregation Types

The `AggregatorType` enum supports the following strategies:

```php
enum AggregatorType: string
{
    case Average = 'average'; // Default: arithmetic mean
    case Median = 'median';   // Middle value, resistant to outliers
    case Minimum = 'minimum'; // Lowest observed value
    case Maximum = 'maximum'; // Highest observed value
    case Sum = 'sum';         // Total across all iterations
    case Range = 'range';     // Difference between min and max
}
```

## Full report

If you need access to the complete set of statistical data rather than just average values, use the `Profiler::report` method.
This method returns a `Report` instance instead of a `Metrics` object. The `Report` aggregates detailed statistics for each metric,
offering a full performance profile. The `Report` class exposes the same properties as the `Metrics` class but the type differs.
Each property of the `Report` is a `Statistics` instance.

The `Statistics` class represents a full statistical summary computed from a set of numeric values. It provides key metrics
such as minimum, maximum, sum, average, median, variance, standard deviation, and coefficient of variation.
Each instance is associated with a Unit (e.g., bytes, nanoseconds) to ensure values are consistently interpreted
and formatted.

```php
use Bakame\Stackwatch\Profiler;

// Create a new Profiler by passing the callback to profile
$report = Profiler::report($service->calculateHeavyStuff(...), 500);

// Access the raw statistical metrics
$report->executionTime->minimum; // Minimum execution time (as float|int, in nanoseconds)
$report->executionTime->average; // Average execution time
$report->executionTime->stdDev;  // Standard deviation

// Get human-readable representations
$report->executionTime->human('minimum'); // e.g., "42.318 μs"
$report->executionTime->toHuman();        // array of all formatted metrics

// The same applies to other profiling metrics:
$report->cpuTime;
$report->memoryUsage;
$report->memoryUsageGrowth;
$report->peakMemoryUsage;
$report->peakMemoryUsageGrowth;
$report->realMemoryUsage;
$report->realMemoryUsageGrowth;
$report->realPeakMemoryUsage;
$report->realPeakMemoryUsageGrowth;
````
Each `Statistics` instance provides:

- `toArray` – for machine-readable data
- `toHuman` – for formatted, human-friendly output
- `human` – for formatted, human-friendly output for a specific property
- implements the `JsonSerializable` interface to enable easy JSON export

Use this structure to analyze performance in depth, log profiles, or visualize trends over time.

## Accessing the result

Finally, the static method `Profiler::execute` allows you to retrieve both the result of a callback
execution and its profiling data. It returns a `ProfiledResult` instance, where the `returnValue`
property contains the callback’s return value, and the `span` property holds the
profiling data collected during the call.

```php
use Bakame\Stackwatch\Profiler;

$result = Profiler::execute($service->calculateHeavyStuff(...));
$result->returnValue;   // the result of executing the `calculateHeavyStuff` method
$result->span;          // the profiling data associated with the call.
$result->span->metrics; // returns a Metrics instance
$result->span->start;   // returns a Snapshot instance
$result->span->end;     // returns a Snapshot instance
$result->span->label;   // returns an identifier as a string
$result->span->range;   // returns a CallRange instance containing information where the profiling occurs
````

## Dumping Results

If you want to quickly inspect profiling results, `Profiler` provides two utility methods: `dump()` and `dd()`.

 - `Profiler::dump()`: Outputs the profiling metrics but allows the program to continue running.
 - `Profiler::dd()`: Outputs the profiling metrics and then immediately stops execution.

Both methods automatically detect the environment:

- In the **console**, metrics are printed in a human-readable text format.
- In the **browser**, metrics are displayed as an HTML table or list.

These methods accept the same arguments as `Profiler::metrics()`:

- the **callback** to profile,
- the number of **iterations**,
- the number of **warm-up** runs to skip,
- and an optional aggregation type (`AggregatorType`).

If no aggregation type is specified, the output will include the full profiling details, similar to what `Profiler::report()` returns.

### Example

```php
use Bakame\Stackwatch\Profiler;

// Dump full detailed metrics (no aggregation type specified) after 5 iterations, skipping 2 warm-up runs
Profiler::dump(fn () => $service->calculateHeavyStuff(), 5, 2);

// Dump metrics using median aggregation (halts execution afterwards)
Profiler::dd(
    callback: $service->calculateHeavyStuff(...),
    iterations: 5,
    type: AggregatorType::Median
);
```

