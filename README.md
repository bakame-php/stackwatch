# Stackwatch

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://phpc.social/@nyamsprodd)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/stackwatch/workflows/build/badge.svg)](https://github.com/bakame-php/stackwatch/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/stackwatch.svg?style=flat-square)](https://github.com/bakame-php/stackwatch/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/stackwatch.svg?style=flat-square)](https://packagist.org/packages/bakame/stackwatch)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

**Stackwatch** is a lightweight profiler for PHP 8.1+.  It helps you measure performance with precision—without
unnecessary complexity. 

**Stackwatch**  bridges the gap between basic timers and heavy profiling tools like [PHPBench](https://phpbench.readthedocs.io/en/latest/), [Xdebug](https://xdebug.org/) or [Blackfire](https://www.blackfire.io/).
It is perfect for:

- Isolated performance testing
- Annotated profiling of large codebases
- Lightweight integration into dev workflows

> Zero-dependency core. Optional CLI with familiar Symfony Console integration.

## Installation

~~~
composer require bakame/stackwatch
~~~

You need:

- **PHP >= 8.1** but the latest stable version of PHP is recommended
- `symfony/console` and `symfony/process` if you are going to use the CLI command
- `psr/log` is optional

## Usage

Traditionally, profiling a section of code quickly looks like this:

```php
$start = microtime(true);
$service->calculateHeavyStuff();
echo microtime(true) - $start; // the execution time of your code
```

`Stackwatch` streamlines this process by removing the need for manual
timing and setup, making profiling more convenient and consistent.

The package offers three (3) complementary ways to profile your code, it features:

- [Scoped Execution Profiling](#profiler) – profile any block using closures
- [Timeline-Based Profiling](#timeline) – segment code with named checkpoints
- [Attribute-Driven CLI Profiling](#cli-command) – run code marked with #[Profile] via a CLI command

### Profiler

#### Metrics quick access

Let's adapt the first example using the `Profiler` class.

```php
use Bakame\Stackwatch\Profiler;

$duration = Profiler::metrics($service->calculateHeavyStuff(...))->executionTime;
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

All duration values are expressed in nanoseconds, while memory-related metrics are measured in bytes.

You can retrieve the `Metrics` statistics in a human-readable format using the instance `forHuman()` method.

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

#### Iterations

To calculate the average usage of a specific metric, specify the number of iterations as the second
argument. The callback will be executed accordingly, and the method will return the average
value over all iterations:

```php
use Bakame\Stackwatch\Profiler;

$cpuTime = Profiler::metrics($service->calculateHeavyStuff(...), 5)->cpuTime;
// the average CPU Time used when executing 5 times the code.
````

#### Full report

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

#### Accessing the result

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

#### Metrics recording

Beyond its static methods, the `Profiler` also supports recording multiple individual calls.
To enable this, create a new `Profiler` instance by passing in the callback you wish to profile.

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

You can execute the `Profiler` instance as many times as needed — it will record all
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

#### Using labels

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

#### Resetting the Profiler

At any given time you can reset the `Profiler` by clearing all the `Summary` already recorded.

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

### Timeline

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

#### Taking Snapshots

Use `capture()` to mark significant points in your code. Those points must each have a unique identifier
called `label`. Labels are automatically normalized (e.g., trimmed, validated).

```php
$timeline->capture('init');
// some code
$timeline->capture('load');
// some code
$timeline->capture('render');
```

#### Getting profiling results

To get a high-level profile between the **first and lastest** snapshot use the `summarize` method.

```php
$summary = $timeline->summarize();     // Returns a Summary instance
echo $summary->metrics->executionTime; // Access execution time, CPU time, memory, etc.
```
You can provide a custom label for the summary:

```php
$summary = $timeline->summarize('full_request'); // Returns a Summary instance
```

If needed, you can measure the profiling data between two specific labels:

```php
$delta = $timeline->delta('init', 'render'); // Returns Summary
$executionTime = $timeline->metrics('init', 'render'); // Returns a Metrics object
```

If you do not specify the second label, the method will default to using the next snapshot
to the one specified as the second argument.

```php
$timeline->metrics('init', 'load');
//is equivalent to
$timeline->metrics('init');
```

You can iterate over each successive pair of snapshots to return the consecutive deltas:

```php
foreach ($timeline->deltas() as $summary) {
    echo $summary->label . ': ' . $summary->metrics->forHuman('execution_time') . PHP_EOL;
}
```

You can also take a snapshot and directly return the calculated summary between the `Timeline`
first snapshot and the one you just take using the `take` method

```php
$summary = $timeline->take('done'); // takes a snapshot labeled 'done' and returns a Summary instance
```

Just like with the `summary` method you can provide an optional custom label for the summary report:

```php
$summary = $timeline->take(label: 'done', summaryLabel: 'total');
```

#### Finalizing the Timeline

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

#### Timeline utility methods

The `Timeline` instance also gives you access to other utility methods:

```php
$timeline->labels();             // returns all the snapshot labels (in order)
$timeline->hasLabel($label);     // tells whether the label is used
$timeline->first();              // returns the first snapshot taken
$timeline->latest();             // returns the most recent snapshot
$timeline->hasNoSnapshot();      // returns true when no snapshot has been taken
$timeline->hasSnapshots();       // returns true when snapshots are available
$timeline->hasEnoughSnapshots(); // returns true if the timeline can safely generate a report/summary
$timeline->toArray();            // returns all snapshots as structured arrays
$timeline->isComplete();         // tells whether the timeline is complete
$timeline->reset();              // Reset the timeline to its initial state open and with no snapshot
```

> [!IMPORTANT]  
> The `reset()` method reopens the timeline and clears all recorded snapshots,
> enabling it to be reused for a new profiling session.

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
foreach ($timeline->deltas() as $summary) {
    echo "{$summary->label}: {$summary->metrics->forHuman('execution_time')}";
}
```

And we can adapt the first example using the `Timeline` class this time.

```php
use Bakame\Stackwatch\Timeline;

$timeline = Timeline::start('start');
$service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'));
$timeline->capture('end');
$duration = $timeline->executionTime('start', 'end');
// $duration is expressed in nanoseconds
````

### Identifier

Every `Timeline` and `Profiler` instance has a unique identifier accessible via the `identifier` method.

```php
use Bakame\Stackwatch\Timeline;
use Bakame\Stackwatch\Profiler;

$timeline = Timeline::start(label: 'start', identifier: 'user_import');
// or 
$timeline = new Timeline(identifier: 'user_import');
$timeline->capture(label: 'start');

echo $timeline->identifier(); // 'user_import'

$profiler = new Profiler(function (): string {
    usleep(1_000);
    
    return 'done';
}, 'user_export');
echo $profiler->identifier(); // 'user_export
```

If not provided, an internal label generator will assign a unique name to the property.
The identifier can be used for logging, debugging or for correlation when
multiple profilers and/or timelines are running in parallel.

### Logging

The `Profiler` and `Timeline` classes can optionally log profiling activity using any logger that
implements `Psr\Log\LoggerInterface`.

To enable this feature, you must install and configure a `PSR-3`-compatible logger. Common
implementations include `Monolog`, `Laminas\Log`, `Symfony’s or Laravel logger` component, and others.

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

//logging the timeline process 

$timeline = Timeline::start('init', logger: $logger);
usleep(1_000);;
$timeline->take('render', 'server_cycle');
```

> [!TIP]  
> Logging can be done also on the `Profiler` static methods, they all optionally accept a `LoggerInterface` argument.
> When logging timeline or profiler instances their respective identifier is added to the log to ease identifying
> which instance is generating the log entries.

Outside the `Profiler` and the `Timeline` you can use the package features through a CLI command.

### CLI command

A CLI Command is available to allow you to benchmark PHP **functions and methods** located in a
specific file or directory using the custom `#[Bakame\Stackwatch\Profile]` attribute.

This is especially useful for:

- Automating performance regressions in CI pipelines
- Profiling code outside the context of an application

#### Usage

```bash
php bin/stackwatch --path=PATH [--output=OUTPUT] [--format=FORMAT] [--depth=DEPTH] [--no-recursion] [--isolation] [--pretty] [--info] [--help]
```

| Option                | Description                                                           |
|-----------------------|-----------------------------------------------------------------------|
| `-p, --path=PATH`     | Path to scan for PHP files to profile (required)                      |
| `-o, --output=OUTPUT` | Path to store the profiling output (optional)                         |
| `-f, --format=FORMAT` | Output format: 'table' or 'json' (default: 'table')                   |
| `-d, --depth=DEPTH`   | Recursion depth (0 = current dir only, default: unlimited) (optional) |
| `-n, --no-recursion`  | Disable directory recursion (optional)                                |
| `-x, --isolation`     | To profile by isolation each file                                     |
| `-P, --pretty`        | Pretty-print the JSON/NDJSON output (json only)                       |
| `-i, --info`          | Show additional system/environment information                        |
| `-h, --help`          | Display the help message                                              |
| `-V, --version`       | Display the version and exit                                          |

#### Example

let's assume you have the following file located in `/path/profiler/test.php`.

```php
<?php

declare(strict_types=1);

namespace Foobar\Baz;

use Bakame\Stackwatch\Profile;
use function random_int;
use function usleep;

require 'vendor/autoload.php';

trait TimerTrait {
    #[Profile(type: Profile::SUMMARY, iterations: 10)]
    private function test() : int {
        usleep(100);

        return random_int(1, 100);
    }
}

enum Foobar
{
    use TimerTrait;

    case Foobar;
}

#[Profile(type: Profile::DETAILED, iterations: 20, warmup: 2)]
function test() : int {
    usleep(100);

    return random_int(1, 100);
}
```
If you run the following command:

```bash
php bin/stackwatch --path=/path/profiler/test.php
```
It will output the following:

```bash
stackwatch v0.11.0 (Kampala) by Ignace Nyamagana Butera and contributors.

Runtime: PHP 8.3.23 OS: Linux Memory Limit: 128M

Average metrics for the method Foobar\Baz\Foobar::test located in /path/to/test.php called 10 times
+------------------------------------+
|         Execution Time: 148.508 µs |
|               CPU Time: 15.500 µs  |
|           Memory Usage: 1.0 KB     |
|      Real Memory Usage: 0.0 B      |
|      Peak Memory Usage: 0.0 B      |
| Real Peak Memory Usage: 0.0 B      |
+------------------------------------+
Report for the function Foobar\Baz\test located in /path/to/test.php called 20 times
+------------------------+---------------+------------+------------+--------------+------------+-----------+------------+------------+----------+-----------+
| Metric                 | Nb Iterations | Min Value  | Max Value  | Median Value | Sum        | Range     | Average    | Variance   | Std Dev  | Coef Var  |
+------------------------+---------------+------------+------------+--------------+------------+-----------+------------+------------+----------+-----------+
| CPU Time               | 20            | 9.000 µs   | 20.000 µs  | 12.000 µs    | 240.000 µs | 11.000 µs | 12.000 µs  | 6.300 μs²  | 2.510 µs | 20.9165 % |
| Execution Time         | 20            | 135.166 µs | 169.833 µs | 149.979 µs   | 2.980 ms   | 34.667 µs | 149.021 µs | 65.499 μs² | 8.093 µs | 5.4309 %  |
| Memory Usage           | 20            | 1.031 KB   | 1.031 KB   | 1.031 KB     | 20.625 KB  | 0.000 B   | 1.031 KB   | 0.000 B²   | 0.000 B  | 0.0000 %  |
| Peak Memory Usage      | 20            | 0.000 B    | 0.000 B    | 0.000 B      | 0.000 B    | 0.000 B   | 0.000 B    | 0.000 B²   | 0.000 B  | 0.0000 %  |
| Real Memory Usage      | 20            | 0.000 B    | 0.000 B    | 0.000 B      | 0.000 B    | 0.000 B   | 0.000 B    | 0.000 B²   | 0.000 B  | 0.0000 %  |
| Real Peak Memory Usage | 20            | 0.000 B    | 0.000 B    | 0.000 B      | 0.000 B    | 0.000 B   | 0.000 B    | 0.000 B²   | 0.000 B  | 0.0000 %  |
+------------------------+---------------+------------+------------+--------------+------------+-----------+------------+------------+----------+-----------+
```

- the first table shows the average metrics for the `Foobar::test` method.
- the second table shows the fully detailed report on the function `test`.

The `#[Profile]` attribute marks a function or method for performance profiling, with the following options:

- `iterations`: (int, required) – Number of times to execute the target code for statistically meaningful results.
- `warmup`: (int, optional) – Number of warmup iterations to perform before measurement begins. These iterations are excluded from the final statistics.
- `type`: (`Profile::SUMMARY` or `Profile::DETAILED`, required) – Determines the level of detail in the output:
    - `Profile::SUMMARY`: Outputs only core metrics (e.g., average execution time).
    - `Profile::DETAILED`: Produces full statistics (min, max, average, standard deviation, etc.).

#### Notes

The command line supports **function-level** and **method-level** profiling, including methods defined
via traits, even inside Enums.

- Functions or methods without a `#[Profile]` attribute will be ignored.
- Functions or methods with arguments will also be ignored.

All required dependencies should be loaded in the target file (use `require`, `include` or Composer autoload).

#### Integration into CI

You can run the profiling command in your CI pipelines to detect regressions or performance anomalies.

```yaml
- name: Run Profiler
  run: php bin/stackwatch --path=/path/profiler/test.php --format=json
```

> [!IMPORTANT]  
> The command line requires `symfony\console` and the `psr\log` interfaces to work.

> [!CAUTION]  
> The command line can scan your full codebase if you specify a directory instead of a path. The
> json output is a NDJSON each line representing the result of a successful file scan.

### Exporters

The package can help with exporting its metrics using different mechanisms.

#### JSON

Both the `Profiler` and `Timeline` classes support JSON export via PHP's `json_encode` function.
This allows you to serialize profiling data for inspection, storage, or transmission.

Calling `json_encode($profiler)` will produce a JSON object containing:

- `identifier`: the profiler's unique identifier 
- `summaries`: an array of summary entries, **ordered from oldest to latest**

Each summary entry includes:

 - `label`: the associated label or name of the profiling block
 - `snapshots`: an array of two snapshots (start and end), ordered chronologically
 - `metrics`: computed performance metrics between the two snapshots

```php
echo json_encode($profiler), PHP_EOL;
```

See a [sample profiler JSON output](./examples/profiler-sample.json) for a complete structure.

Calling `json_encode($timeline)` will produce a JSON object containing:

- `identifier`: the timeline's unique identifier
- `snapshots`: an array of snapshot entries, **ordered from oldest to latest**

```php
echo json_encode($timeline), PHP_EOL;
```
See a [sample timeline JSON output](./examples/timeline-sample.json) for a complete structure.

In order to facilitate JSON export, the package has a dedicated `JsonExporter` class
which will be able to store the generated json in the specified location. It supports
streams, string path and `SplFileInfo` objects.

```php
use Bakame\Stackwatch\Exporter\JsonExporter;
use Bakame\Stackwatch\Profiler;

$report = Profiler::report($service->calculateHeavyStuff(...), 500);
$exporter = new JsonExporter('path/to/store/the/profile.json', JSON_PRETTY_PRINT|JSON_BIGINT_AS_STRING);
$exporter->exportReport($report);
```
The report will be stored in the designated location.

> [!IMPORTANT]  
> If you try to store multiple export in the same file (specified by a string)
> They will get overwritten and only the last export will be stored.
> To get the data appended provide an already open `resource` or `SplFileObject`.

#### CLI

If you have the `symfony\console` package installed in your application, you can display
the `Profiler` or the `Timeline` recorded data recorded using the `ConsoleExporter` class.

```php
use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Profiler;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler->profile('first_run', 1, 2);
$profiler->profile('last_run', 1, 2);
$profiler(1, 2);

$renderer = new ConsoleExporter();
$renderer->exportProfiler($profiler);
```
the following table will be outputted in your terminal.

```bash
+--------------+-----------+-----------+--------+----------+----------+-----------+
| Label        | CPU Time  | Exec Time | Memory | Real Mem | Peak Mem | Real Peak |
+--------------+-----------+-----------+--------+----------+----------+-----------+
| first_run    | 10.000 ms | 1.264 ms  | 2.5 KB | 0.0 B    | 0.0 B    | 0.0 B     |
| last_run     | 8.000 ms  | 1.263 ms  | 2.5 KB | 0.0 B    | 0.0 B    | 0.0 B     |
| dd612a368fe5 | 11.000 ms | 1.270 ms  | 2.5 KB | 0.0 B    | 0.0 B    | 0.0 B     |
+--------------+-----------+-----------+--------+----------+----------+-----------+
| Average      | 9.667 ms  | 1.266 ms  | 2.5 KB | 0.0 B    | 0.0 B    | 0.0 B     |
+--------------+-----------+-----------+--------+----------+----------+-----------+
```

#### Open Telemetry

The `Profiler` and the `Timeline` results can be exported to an Open telemetry compatible
server using the `open-telemetry/exporter-otlp` package.

To do so, first install the package if it is not yet the case, then do the following:

```php
use Bakame\Stackwatch\Expoter\OpenTelemetryExporter;
use Bakame\Stackwatch\Profiler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

// adding a logger is optional.
$logger = new Logger('profiler');
$logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));
$tracerProvider = new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter()));
$exporter = new OpenTelemetryExporter($tracerProvider, $logger);

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler->profile('first_run', 1, 2);
$profiler->profile('last_run', 1, 2);
$profiler(1, 2);

$exporter->exportProfilter($profiler); 
// the Profiler content is exported to the Open Telemetry Server.
```

Remember to change the `$tracerProvider` to connect to your own environment and server.

### Helpers

#### Environment

The package includes an `Environment` class that collects information about the current system for profiling purposes.

```php
use Bakame\Stackwatch\Environment;;

$system = Environment::current();
$system->os; // the Operating System
$system->osFamily; // OS Family
$system->hostname; // the hostname
$system->machine; // the Architecture
$system->phpIntSize; // PHP Integer Size
$system->phpArchitecture; //returns 64-bits
$system->sapi; // SAPI
$system->memoryLimit; // Memory Limit
$system->cpuCores; // CPU Cores
$system->totalDisk; // the total available disk space in bytes
$system->freeDisk; // the remaining free disk space in bytes
```

Apart from returning raw information about your system, the instance can be used to detect
the PHP architecture used or if the memory is unlimited using boolean returning methods:

```php
use Bakame\Stackwatch\Environment;

$system = Environment::current();
$system->is32Bit();         // returns true on a 32-bit architecture
$system->is64Bit();         // returns true on a 64-bit architecture
$system->unlimitedMemory(); // returns true if there is no memory limit
$system->isWindows();       // returns true if the OS is a Windows
$system->isMac();           // returns true if the OS is a Mac
$system->isUnixLike();      // returns true if the OS is a Unix like
````

The `ConsoleExporter` also provides an exporter for the class:

```php
use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Environment;

(new ConsoleExporter())->exportEnvironment($system);
```

Will return

```bash
+--------------------------------+
| Operating System: Linux        |
|        OS Family: Linux        |
|         Hostname: example.org  |
|     Architecture: x86_64       |
| PHP Integer Size: 8            |
| PHP Architecture: 64-bit       |
|             SAPI: cli          |
|      PHP Version: 8.3.7        |
|     Memory Limit: 64 MB        |
| Raw Memory Limit: 64M          |
|        CPU Cores: 1            |
|        Disk Size: 0            |
|  Free Disk Space: 0            |
+--------------------------------+
```

#### Unit of Measurement

To correctly show the memory and duration unit, the package comes with 2 helper Enum:

- `MemoryUnit` to help formatting and converting to and from bytes.
- `DurationUnit` to help formatting and converting to and from nanoseconds.

```php
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\DurationUnit;

MemoryUnit::format(1_024 ** 2); // returns '1 MB'
MemoryUnit::parse('1 kb'); // returns 1000 in bytes

DurationUnit::Second->convertToNano(1); // returns 1_000_000_000
DurationUnit::format('23_000'); // returns 23 µs
DurationUnit::tryParse('28 kb'); // returns null
DurationUnit::Second->convertTo(DurationUnit::Hour, 9_000) // returns 2.5 (the duration expressed in hour)
```

## Testing

The library has:

- a [PHPUnit](https://phpunit.de) test suite.
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

```bash
composer test
```

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/bakame-php/aide/graphs/contributors)
