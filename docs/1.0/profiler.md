---
layout: default
title: Scoped Execution Profiling Aggregator
---

# Profiler

## Metrics recording

If you want to record multiple individual calls you will need to create a new `Profiler` instance by passing in the callback you wish to profile.

```php
use Bakame\Stackwatch\Profiler;

// Create a new Profiler by passing the callback to profile
$profiler = new Profiler($service->calculateHeavyStuff(...));

//we invoke the `run` method of the Profiler which will execute the callback
//$result is the result of executing the calculateHeavyStuff method
$result = $profiler->run(new DateTimeImmutable('2024-12-24'));

$span = $profiler->latest(); // returns the Span from the last call
// the $span->metrics property returns a Metrics instance
$metrics = $span->metrics;

$metrics->executionTime;
//and all the other metrics properties
````

You can execute the `Profiler` instance as many times as needed — it will record all
execution metrics each time.

```php
$result1 = $profiler->run(new DateTimeImmutable('2024-12-24'));
$result2 = $profiler->run(new DateTimeImmutable('2025-03-02'));
$result3 = $profiler->run(new DateTimeImmutable('2024-05-11'));

count($profiler);          // the number of Span instances already recorded
$profiler->latest();       // returns the Span from the last call
$profiler->nth(-1);        // returns the same Span as Profile::last
$profiler->first();        // returns the first Span ever generated
$profiler->isEmpty();      // returns true when the profiler contains no span
$profiler->hasSummaries(); // returns true when at least on Span is present
$profiler->average();      // returns the average Metrics of all the calls
$profiler->median();       // returns the median Metrics of all the calls
$profiler->min();          // returns the min Metrics of all the calls
$profiler->max();          // returns the max Metrics of all the calls
$profiler->range();        // returns the range Metrics of all the calls
```

You can access any `Span` by index using the `nth` method, or use the `first` and `latest` methods
to quickly retrieve the first and last recorded `Span`. The `nth` method also accepts negative
integers to simplify access from the end of the list.

## Using labels

To add a custom label to each run, use the `profile` method. This method works like the
`run` method but allows you to assign a custom label to the returned `Span` object
via its first argument.

```php
use Bakame\Stackwatch\Profiler;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler->run(1, 2, 3); // returns 6
$span = $profiler->latest();            // returns the last Span object from the last call
$profiler->profile('my_test', 7, 8, 9);    // returns 24
$namedSpan = $profiler->get('my_test'); // returns the associated Span

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

The metrics related methods (`average`, `median`, `min`, `max`, `range`) can take an optional `label` argument:

- if it is a string, it will expect a `Span` label used to filter the `Span` to use for the metrics aggregation.
- if it is a callable, it will expect a filtering **callable** which takes a `Span` as its unique argument and keep the instance which returns `true`.
- if not present, the metrics uses all the found `Span` instance (default)

## Resetting the Profiler

At any given time you can reset the `Profiler` by clearing all the `Span` already recorded.

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

Every `Profiler` instance has a unique identifier accessible via the `identifier` method.

```php
use Bakame\Stackwatch\Profiler;

$profiler = new Profiler(function (): string {
    usleep(1_000);
    
    return 'done';
}, 'user_export');
echo $profiler->identifier(); // 'user_export
```

If not provided, an internal generated unique identifier will be assigned to the property.
The identifier can be used for logging, debugging or for correlation when
multiple profilers and/or timelines are running in parallel.

## Logging

You can optionally log profiling activity using any logger that implements `Psr\Log\LoggerInterface`.

To enable this feature, you need to provide your instance to the `Profiler` constructor. Below
an example using `Monolog`.

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

## Dumping Aggregates

The `Profiler` provides `dump()` and `dd()` methods to inspect its aggregated `Span` objects. These methods work
similarly to the ones in `Profiler` but focus on aggregated `Span`s instead of raw profiling metrics.

- `dump()`: Outputs the aggregated `Span` but allows the program to continue running.
- `dd()`: Outputs the aggregated `Span` and immediately stops execution.

Both methods automatically detect the environment:

- In the **console**, aggregates are printed in a human-readable text format.
- In the **browser**, aggregates are displayed as an HTML table or list.

### Basic Usage

```php
use Bakame\Stackwatch\Profiler;

$profiler = new Profiler($spans);

// Dump aggregated spans without stopping execution
$profiler->dump();

// Dump aggregated spans and halt execution
$profiler->dd();
```

### Filtering Aggregates

You can optionally filter which Span objects to inspect using a **label** or a **callback**:

```php
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\Span;

$profiler = new Profiler($spans);

$profiler->profile('now');
$profiler->profile('now');
$profiler->profile('later');
 // you will only inspect the Span labelled `now`
$profiler->dump('now');
 // you will only see the Span where the memoryUsage is higher than 3.5MB
$profiler->dd(fn (Span $span) => $span->metrics->memoryUsage > MemoryUnit::Megabyte->convertToBytes(3.5));
```

<p class="message-info">
Using <code>labels</code> or <code>callbacks</code> allows you to focus on specific
subsets of <strong>Spans</strong> when dumping or debugging.
</p>
