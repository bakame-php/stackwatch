---
layout: default
title: Timeline-Based Profiling
---

# Timeline

In situation where you can't work with callbacks you can alternatively use the `Timeline` class.

The `Timeline` class profiles across labeled checkpoints ("snapshots") in your
code. A `Timeline` class is a sequence of snapshots of your codebase.
You can start a new `Timeline` using the static method `start`:

```php
use App\Profiler\Timeline;

$timeline = Timeline::start('boot');
```

When starting a timeline with the `start` method, you initiate a new `Timeline` class but you
also immediately capture a significant point in your code also known as a snapshot.

## Taking Snapshots

Use `capture()` to mark significant points in your code. Those points must each have a unique identifier
called `label`. Labels are automatically normalized (e.g., trimmed, validated).

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

If needed, you can measure the profiling data between two specific labels:

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

We can adapt this example using the `Timeline` class this time.

```php
use Bakame\Stackwatch\Timeline;

$timeline = Timeline::start('start');
$service->calculateHeavyStuff();
$duration = $timeline->take('end')->metrics->executionTime;
// $duration is expressed in nanoseconds
````

#### Identifier

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

#### Logging

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
