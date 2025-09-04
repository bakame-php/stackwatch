---
layout: default
title: Scoped Execution Profiling
---

# Span Aggregator

## Metrics recording

If you want to record multiple individual calls you will need to create a new `SpanAggregator` instance by passing in the callback you wish to profile.

```php
use Bakame\Stackwatch\SpanAggregator;

// Create a new SpanAggregator by passing the callback to profile
$spanAggregator = new SpanAggregator($service->calculateHeavyStuff(...));

//we invoke the `run` method of the SpanAggregator which will execute the callback
//$result is the result of executing the calculateHeavyStuff method
$result = $spanAggregator->run(new DateTimeImmutable('2024-12-24'));

$span = $spanAggregator->latest(); // returns the Span from the last call
// the $span->metrics property returns a Metrics instance
$metrics = $span->metrics;

$metrics->executionTime;
//and all the other metrics properties
````

You can execute the `SpanAggregator` instance as many times as needed — it will record all
execution metrics each time.

```php
$result1 = $spanAggregator->run(new DateTimeImmutable('2024-12-24'));
$result2 = $spanAggregator->run(new DateTimeImmutable('2025-03-02'));
$result3 = $spanAggregator->run(new DateTimeImmutable('2024-05-11'));

count($spanAggregator);          // the number of Span instances already recorded
$spanAggregator->latest();       // returns the Span from the last call
$spanAggregator->nth(-1);        // returns the same Span as Profile::last
$spanAggregator->first();        // returns the first Span ever generated
$spanAggregator->isEmpty();      // returns true when the profiler contains no span
$spanAggregator->hasSummaries(); // returns true when at least on Span is present
$spanAggregator->average();      // returns the average Metrics of all the calls
```

You can access any `Span` by index using the `nth` method, or use the `first` and `latest` methods
to quickly retrieve the first and last recorded `Span`. The `nth` method also accepts negative
integers to simplify access from the end of the list.

## Using labels

To add a custom label to each run, use the `profile` method. This method works like the
`run` method but allows you to assign a custom label to the returned `Span` object
via its first argument.

```php
use Bakame\Stackwatch\SpanAggregator;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$spanAggregator = new SpanAggregator($callback);
$spanAggregator(1, 2, 3); // returns 6
$span = $spanAggregator->latest();            // returns the last Span object from the last call
$spanAggregator->profile('my_test', 7, 8, 9);    // returns 24
$namedSpan = $spanAggregator->get('my_test'); // returns the associated Span

$spanAggregator->get('foobar');      // returns null because the `foobar` label does not exist
$spanAggregator->has('foobar');      // returns false because the label does not exist
$spanAggregator->labels();           // returns all the labels attached to the SpanAggregator
$spanAggregator->average('my_test'); // returns the Metrics average for all the calls whose label is `my_test`
````

You can reuse the same label multiple times. The `SpanAggregator::get()` method returns the most recent
entry associated with the specified label. In contrast, `SpanAggregator::getAll()` returns an `array`
of all entries recorded under that label, ordered from oldest to newest.

If the label is invalid or has never been used, `SpanAggregator::getAll()` returns an empty `array`
while `SpanAggregator::get()` returns `null`. To determine whether a label exists, use `SpanAggregator::has()`,
which returns `true` if the label has been recorded, or `false` otherwise.

## Resetting the SpanAggregator

At any given time you can reset the `SpanAggregator` by clearing all the `Span` already recorded.

```php
use Bakame\Stackwatch\SpanAggregator;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$spanAggregator = new SpanAggregator($callback);
$spanAggregator(1, 2, 3);
$spanAggregator->profile('my_test', 4, 5, 6);
$spanAggregator->run(7, 8, 9);

count($spanAggregator); // returns 3
$spanAggregator->isEmpty(); // return false

$spanAggregator->reset();

count($spanAggregator); // returns 0
$spanAggregator->isEmpty(); // return true
```

## Identifier

Every `SpanAggregator` instance has a unique identifier accessible via the `identifier` method.

```php
use Bakame\Stackwatch\SpanAggregator;

$spanAggregator = new SpanAggregator(function (): string {
    usleep(1_000);
    
    return 'done';
}, 'user_export');
echo $spanAggregator->identifier(); // 'user_export
```

If not provided, an internal generated unique identifier will be assigned to the property.
The identifier can be used for logging, debugging or for correlation when
multiple profilers and/or timelines are running in parallel.

## Logging

You can optionally log profiling activity using any logger that implements `Psr\Log\LoggerInterface`.

To enable this feature, you need to provide your instance to the `SpanAggregator` constructor. Below
an example using `Monolog`.

```php
use Bakame\Stackwatch\SpanAggregator;
use Bakame\Stackwatch\Timeline;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('profiler');
$logger->pushHandler(new StreamHandler(STDOUT, Level::Debug));

//logging with the SpanAggregator instance

$spanAggregator = new SpanAggregator(function () {
    usleep(1_000);

    return 'end';
}, logger: $logger);

$spanAggregator->profile('toto');
$spanAggregator->profile('tata');
```

<div class="message-info">
<p>Logging can be done also on the <code>SpanAggregator</code> static methods, they all optionally accept a <code>LoggerInterface</code> argument.</p>
</div>
<div class="message-info">
<p>When logging, the <code>SpanAggregator</code> identifier is added to the log to ease identifying which instance is generating the log entries.</p>
</div>

## Dumping Aggregates

The `SpanAggregator` provides `dump()` and `dd()` methods to inspect its aggregated `Span` objects. These methods work
similarly to the ones in `Profiler` but focus on aggregated `Span`s instead of raw profiling metrics.

- `dump()`: Outputs the aggregated `Span` but allows the program to continue running.
- `dd()`: Outputs the aggregated `Span` and immediately stops execution.

Both methods automatically detect the environment:

- In the **console**, aggregates are printed in a human-readable text format.
- In the **browser**, aggregates are displayed as an HTML table or list.

### Basic Usage

```php
use Bakame\Stackwatch\SpanAggregator;

$aggregator = new SpanAggregator($spans);

// Dump aggregated spans without stopping execution
$aggregator->dump();

// Dump aggregated spans and halt execution
$aggregator->dd();
```

### Filtering Aggregates

You can optionally filter which Span objects to inspect using a **label** or a **callback**:

```php
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\SpanAggregator;
use Bakame\Stackwatch\Span;

$aggregator = new SpanAggregator($spans);

$aggregator->profile('now');
$aggregator->profile('now');
$aggregator->profile('later');
 // you will only inspect the Span labelled `now`
$aggregator->dump('now');
 // you will only see the Span where the memoryUsage is higher than 3.5MB
$aggregator->dd(fn (Span $span) => $span->metrics->memoryUsage > MemoryUnit::Megabyte->convertToBytes(3.5));
```

<p class="message-info">
Using <code>labels</code> or <code>callbacks</code> allows you to focus on specific
subsets of <strong>Spans</strong> when dumping or debugging.
</p>
