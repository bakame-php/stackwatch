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

$duration = Profiler::metrics(
    $service->calculateHeavyStuff(...)
)->executionTime;
// $duration is the execution time in nanosecond using hrtime instead of microtime
````

The method returns a `Metrics` class with readonly properties for each metric.

```php
use Bakame\Stackwatch\Profiler;

// you create a new Profiler by passing the callback you want to profile
$metrics = Profiler::metrics($service->calculateHeavyStuff(...));

$metrics->executionTime;
$metrics->cpuTime; 
$metrics->memoryUsage;
$metrics->peakMemoryUsage;
$metrics->realMemoryUsage;
$metrics->realPeakMemoryUsage;
````

All duration values are expressed in nanoseconds, while memory-related metrics are measured in bytes. You can retrieve
the `Metrics` statistics in a human-readable format using the instance `forHuman()` method.
You can either:

- Call the method without arguments to retrieve **all metrics** as formatted strings in an associative `array`.
- Or pass the name of a specific metric to retrieve **only that value**, formatted for human readability.

```php
use Bakame\Stackwatch\Profiler;

// you create a new Profiler by passing the callback you want to profile
$metrics = Profiler::metrics($service->calculateHeavyStuff(...));

$metrics->forHuman();
// returns 
// [
//   "cpu_time" => "30.000 µs"
//   "execution_time" => "1.271 ms"
//   "memory_usage" => "2.5 KB"
//   "real_memory_usage" => "0.0 B"
//   "peak_memory_usage" => "0.0 B"
//   "real_peak_memory_usage" => "0.0 B"
// ]

$metrics->forHuman('memory_usage'); //returns "2.5 KB"
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
$report->executionTime->forHuman('minimum'); // e.g., "42.318 μs"
$report->executionTime->forHuman();          // array of all formatted metrics

// The same applies to other profiling metrics:
$report->cpuTime;
$report->memoryUsage;
$report->peakMemoryUsage;
$report->realMemoryUsage;
$report->realPeakMemoryUsage;
````
Each `Statistics` instance provides:

- `toArray` – for machine-readable data
- `forHuman` – for formatted, human-friendly output
- implements the `JsonSerializable` interface to enable easy JSON export

Use this structure to analyze performance in depth, log profiles, or visualize trends over time.

## Accessing the result

Finally, the static method `Profiler::execute` allows you to retrieve both the result of a callback
execution and its profiling data. It returns a `ProfiledResult` instance, where the `result`
property contains the callback’s return value, and the `summary` property holds the
profiling metrics collected during the call.

```php
use Bakame\Stackwatch\Profiler;

$result = Profiler::execute($service->calculateHeavyStuff(...));
$result->returnValue;      // the result of executing the `calculateHeavyStuff` method
$result->summary;          // the profiling data associated with the call.
$result->summary->metrics; // returns a Metrics instance
$result->summary->start;   // returns a Snapshot instance
$result->summary->end;     // returns a Snapshot instance
$result->summary->label;   // returns an identifier as a string
````

## Metrics recording

Beyond its static methods, the <code>Profiler</code> also supports recording multiple individual calls.
To enable this, create a new <code>Profiler</code> instance by passing in the callback you wish to profile.

```php
use Bakame\Stackwatch\Profiler;

// Create a new Profiler by passing the callback to profile
$profiler = new Profiler($service->calculateHeavyStuff(...));

//we invoke the `run` method of the Profiler which will execute the callback
//$result is the result of executing the calculateHeavyStuff method
$result = $profiler->run(new DateTimeImmutable('2024-12-24'));
// you can use `__invoke` as a syntactic sugar method.

$summary = $profiler->latest(); // returns the Summary from the last call
// the $summary->metrics property returns a Metrics instance
$metrics = $summary->metrics;

$metrics->executionTime;
$metrics->cpuTime; 
$metrics->memoryUsage;
$metrics->peakMemoryUsage;
$metrics->realMemoryUsage;
$metrics->realPeakMemoryUsage;
````

You can execute the <code>Profiler</code> instance as many times as needed — it will record all
execution metrics each time.

```php
$result1 = $profiler(new DateTimeImmutable('2024-12-24'));
$result2 = $profiler(new DateTimeImmutable('2025-03-02'));
$result3 = $profiler(new DateTimeImmutable('2024-05-11'));

count($profiler);          // the number of summaries already recorded
$profiler->latest();       // returns the Summary from the last call
$profiler->nth(-1);        // returns the same Summary as Profile::last
$profiler->first();        // returns the first Summary ever generated
$profiler->isEmpty();      // returns true when the profiler contains no summary
$profiler->hasSummaries(); // returns true when at least on Summary is present
$profiler->average();      // returns the average Metrics of all the calls
```

You can access any `Summary` by index using the `nth` method, or use the `first` and `latest` methods
to quickly retrieve the first and last recorded `Summary`. The `nth` method also accepts negative
integers to simplify access from the end of the list.

## Using labels

To add a custom label to each run, use the `profile` method. This method works like the
`run` method but allows you to assign a custom label to the returned `Summary` object
via its first argument.

```php
use Bakame\Stackwatch\Profiler;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler(1, 2, 3); // returns 6
$summary = $profiler->latest();            // returns the last Summary object from the last call
$profiler->profile('my_test', 7, 8, 9);    // returns 24
$namedSummary = $profiler->get('my_test'); // returns the associated Summary

$profiler->get('foobar');      // returns null because the `foobar` label does not exist
$profiler->has('foobar');      // returns false because the label does not exist
$profiler->labels();           // returns all the labels attached to the Profiler
$profiler->average('my_test'); // returns the Metrics average for all the calls whose label is `my_test`
````

You can reuse the same label multiple times. The `Profiler::get()` method returns the most recent
entry associated with the specified label. In contrast, `Profiler::getAll()` returns an `array`
of all entries recorded under that label, ordered from oldest to newest.

If the label is invalid or has never been used, `Profiler::getAll()` returns an empty `array`
while `Profiler::get()` returns `null`. To determine whether a label exists, use `Profiler::has()`,
which returns `true` if the label has been recorded, or `false` otherwise.

## Resetting the Profiler

At any given time you can reset the <code>Profiler</code> by clearing all the `Summary` already recorded.

```php
use Bakame\Stackwatch\Profiler;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler(1, 2, 3);
$profiler->profile('my_test', 4, 5, 6);
$profiler->run(7, 8, 9);

count($profiler); // returns 3
$profiler->isEmpty(); // return false

$profiler->reset();

count($profiler); // returns 0
$profiler->isEmpty(); // return true
```

## Identifier

Every <code>Profiler</code> instance has a unique identifier accessible via the `identifier` method.

```php
use Bakame\Stackwatch\Profiler;

$profiler = new Profiler(function (): string {
    usleep(1_000);
    
    return 'done';
}, 'user_export');
echo $profiler->identifier(); // 'user_export
```

If not provided, an internal generated unique identifer will be assigned to the property.
The identifier can be used for logging, debugging or for correlation when
multiple profilers and/or timelines are running in parallel.

## Logging

You can optionally log profiling activity using any logger that implements `Psr\Log\LoggerInterface`.

To enable this feature, you need to provide your instance to the <code>Profiler</code> constructor

```php
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Timeline;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('profiler');
$logger->pushHandler(new StreamHandler(STDOUT, Level::Debug));

//logging with the Profiler instance

$profiler = new Profiler(function () {
    usleep(1_000);

    return 'end';
}, logger: $logger);

$profiler->profile('toto');
$profiler->profile('tata');
```

<div class="message-info">
<p>Logging can be done also on the <code>Profiler</code> static methods, they all optionally accept a <code>LoggerInterface</code> argument.</p>
</div>
<div class="message-info">
<p>When logging, the <code>Profiler</code> identifier is added to the log to ease identifying which instance is generating the log entries.</p>
</div>
