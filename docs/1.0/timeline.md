---
layout: default
title: Timeline-Based Profiling
---

# Timeline

In situation where you can't work with callbacks you can alternatively use the `Timeline` class.

The `Timeline` class profiles across labeled checkpoints ("snapshots") in your
code. A `Timeline` class is a sequence of snapshots taken over your codebase.
You can start a new `Timeline` using the static method `start`:

The `Timeline` class lets you profile your code by capturing labeled checkpoints, called “snapshots.”
Each `Timeline` is an ordered sequence of snapshots, and you can start a new one using the
static method `Timeline::start`:

```php
use App\Profiler\Timeline;

$timeline = Timeline::start('boot');
```

When starting a timeline with the `start` method, you initiate a new `Timeline` class; and you
also immediately capture a significant point in your code also known as a snapshot.

## Taking Snapshots

Use `capture()` to mark important points in your code. Each point needs a unique label,
which is automatically normalized. Labels can include lowercase letters, digits, dots, underscores,
and hyphens—but cannot start or end with a symbol, nor have consecutive symbols.
This ensures all labels are **safe, consistent, and easy to reference** throughout your timeline.

```php
$timeline->capture('init');
// some code
$timeline->capture('load');
// some code
$timeline->capture('render');
```

## Getting profiling results

To get a high-level profile between the **first and lastest** snapshot use the `summarize` method.

```php
$span = $timeline->summarize();     // Returns a Span instance
echo $span->metrics->executionTime; // Access execution time, CPU time, memory, etc.
```
You can provide a custom label for the span:

```php
$span = $timeline->summarize('full_request'); // Returns a Span instance
```

If needed, you can measure the profiling data (`Span`) between two specific labels:

```php
$delta = $timeline->delta('init', 'render'); // Returns Span
$executionTime = $timeline->metrics('init', 'render'); // Returns a Metrics object
```

If you do not specify the second label, the method will default to using the next snapshot
to the one specified as the first argument.

```php
$timeline->metrics('init', 'load');
//is equivalent to
$timeline->metrics('init');
```

You can iterate over each successive pair of snapshots to return the consecutive deltas:

```php
foreach ($timeline->deltas() as $span) {
    echo $span->label . ': ' . $span->metrics->forHuman('execution_time') . PHP_EOL;
}
```

You can also take a snapshot and directly return the calculated `Span` between the `Timeline`
first snapshot and the one you just take using the `take` method

```php
$span = $timeline->take('done'); // takes a snapshot labeled 'done' and returns a Span instance
```

Just like with the `summarize` method you can provide an optional custom label for the `Span` instance:

```php
$span = $timeline->take(label: 'done', spanLabel: 'total');
```

## Finalizing the Timeline

While not mandatory or required, The `complete` method finalizes the profiling timeline, marking it
as complete and preventing any further snapshots or operations that modify the state.

```php
$timeline->complete();
```

Before calling `complete`, the timeline is **open** and can accept snapshots via `capture`
or `take` methods. Once `complete` is called:

- The timeline becomes **complete and is closed to further modifications.**
- Further calls to `capture` or `take` will throw an `UnableToProfile` exception.
- Calling `complete` multiple times has no effects - it is **idempotent**.
- The result of `summarize` remains unchanged after completion and can be safely called multiple times.

At any given time you can check your `Timeline` completion status using the `Timeline::isComplete`
method which returns `true` when it is complete; false otherwise.

## Timeline utility methods

The `Timeline` instance also gives you access to other utility methods:

```php
$timeline->labels();             // returns all the snapshot labels (in order)
$timeline->hasLabel($label);     // tells whether the label is used
$timeline->first();              // returns the first snapshot taken
$timeline->latest();             // returns the most recent snapshot
$timeline->hasNoSnapshot();      // returns true when no snapshot has been taken
$timeline->hasSnapshots();       // returns true when snapshots are available
$timeline->hasEnoughSnapshots(); // returns true if the timeline can safely generate a report/span
$timeline->toArray();            // returns all snapshots as structured arrays
$timeline->isComplete();         // tells whether the timeline is complete
$timeline->reset();              // Reset the timeline to its initial state open and with no snapshot
```

<div class="message-warning">
The <code>reset()</code> method reopens the timeline and clears all recorded snapshots, enabling it to be reused for a new profiling session.
</div>

As an example, you can do the following:

```php
$timeline = Timeline::start('request');

doSomething();
$timeline->capture('step1');

sleep(1);
$timeline->capture('step2');

$result = $timeline->take('response');
$timeline->complete();

// Printing full report
foreach ($timeline->deltas() as $span) {
    echo "{$span->label}: {$span->metrics->forHuman('execution_time')}";
}
```

Traditionally, profiling a section of code quickly looks like this:

```php
$start = microtime(true);
$service->calculateHeavyStuff();
echo microtime(true) - $start; // the execution time of your code
```

Using the `Timeline` class, the same example can be written as:

```php
use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\Timeline;

//Start a new Timeline
$timeline = Timeline::start('start');

// Code to profile
$service->calculateHeavyStuff();

// Take a snapshot at the end and get execution time
$duration = $timeline->take('end')->metrics->executionTime; // returns 1271000

// $duration is expressed in nanoseconds
// // Convert the value to a more readable form using DurationUnit
echo DurationUnit::format($duration); //returns "1.271 ms"
````

## Identifier

Every `Timeline` instance has a unique identifier accessible via the `identifier` method.

```php
use Bakame\Stackwatch\Timeline;

$timeline = Timeline::start(label: 'start', identifier: 'user_import');
// or 
$timeline = new Timeline(identifier: 'user_import');
$timeline->capture(label: 'start');

echo $timeline->identifier(); // 'user_import'
```

If not provided, a generated unique name will be assigned to the instance.

The identifier can be used for logging, debugging or for correlation when
multiple profilers and/or timelines are running in parallel.

## Filtering

The `Timeline` class provides a `filter()` method to extract a subset of its `Snapshot` objects.

- The method expects a **callback** that receives a `Snapshot` instance.
- The callback must return `true` for a Snapshot to be included in the results.
- The method returns a list of matching Snapshots.

### Example

```php
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\Timeline;
use Bakame\Stackwatch\Snapshot;

$timeline = Timeline::start('init', 'timeline');
dump(fopen('php://stdout', 'wb'));
$timeline->capture('after_dump');
$result = $db->query("SELECT ...");
$timeline->capture('after_query');
// another part of the codebase
$timeline->capture('the_end');
$timeline->complete();

// Filter only snapshots taken before line 23 of /path/to/my/script.php
// (specifying the label is optional)
$snapshots = $timeline->filter(
    fn (Snapshot $snapshot): bool => 
        $snapshot->originPath = '/path/to/my/script/php' &&
        $snapshot->originLine < 23);
```

<p class="message-info">Using <code>filter()</code> lets you narrow down the <code>Timeline</code> to the <code>Snapshots</code> that are most relevant to your analysis.</p>

## Logging

You can optionally log profiling activity using any logger that
implements `Psr\Log\LoggerInterface`.

To enable this feature, you must install and configure a `PSR-3`-compatible logger. Common
implementations include `Monolog`, `Laminas\Log`, `Symfony’s or Laravel logger` component, and others.

```php
use Bakame\Stackwatch\Timeline;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('profiler');
$logger->pushHandler(new StreamHandler(STDOUT, Level::Debug));

$timeline = Timeline::start('init', logger: $logger);
usleep(1_000);;
$timeline->take('render', 'server_cycle');
```

<div class="message-info">
When logging the `Timeline` identifier is added to the log to ease identifying which instance is generating the log entries.
</div>

## Dumping Aggregates

The `Timeline` provides `dump()` and `dd()` methods to inspect its aggregated `Snapshots` objects. These methods work
similarly to the ones in `Profiler` but focus on aggregated `Span`s instead of raw profiling metrics.

- `dump()`: Outputs the aggregated `Snapshots` but allows the program to continue running.
- `dd()`: Outputs the aggregated `Snapshots` and immediately stops execution.

Both methods automatically detect the environment:

- In the **console**, aggregates are printed in a human-readable text format.
- In the **browser**, aggregates are displayed as an HTML table or list.

### Basic Usage

```php
use Bakame\Stackwatch\Timeline;

$timeline = Timeline::start('init', 'timeline');
dump(fopen('php://stdout', 'wb'));
$timeline->capture('after_dump');
// another part of the codebase
$timeline->capture('after_pf_dump');
// another part of the codebase
$timeline->capture('the_end');
$timeline->complete();

// Dump aggregated snapshots without stopping execution
$timeline->dump();

// Dump aggregated snapshots and halt execution
$timeline->dd();
```

### Filtering Aggregates

You can optionally filter which `Snapshots` objects to inspect using a **callback**:

```php
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\Timeline;
use Bakame\Stackwatch\Snapshot;

$timeline = Timeline::start('init', 'timeline');
dump(fopen('php://stdout', 'wb'));
$timeline->capture('after_dump');
$result = $db->query("SELECT ...");
$timeline->capture('after_query');
// another part of the codebase
$timeline->capture('the_end');
$timeline->complete();

 // you will only see the Snapshot located before the 23rd line of the `/path/to/my/script/php`
 // if it is where the snapshot was taken
 // specifying the label is not mandatory
$timeline->dd(fn (Snapshot $snapshot, string $label) => $snapshot->originPath = '/path/to/my/script/php' && $snapshot->originLine < 23);
```

<p class="message-info">Using <code>callbacks</code> allows you to focus on specific subsets of <strong>Snapshot</strong> when dumping or debugging.</p>
