![Aide-profiler](.github/logo.png?raw=true)

# Aide Profiler

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/aide-profiler/workflows/build/badge.svg)](https://github.com/bakame-php/aide-profiler/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/aide-profiler.svg?style=flat-square)](https://github.com/bakame-php/aide-profiler/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/aide-profiler.svg?style=flat-square)](https://packagist.org/packages/bakame/aide-profiler)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

A minimalist profiler for PHP. The profiler is embeddable, multi-metric, and framework-agnostic 
It fills the gap between a basic timer and full-blown profilers like [Xdebug](https://xdebug.org/) or [Blackfire](https://www.blackfire.io/).

## Installation

~~~
composer require bakame/aide-profiler
~~~

You need **PHP >= 8.1** but the latest stable version of PHP is recommended

## Usage

Traditionally, profiling a section of code quickly looks like this:

```php
$start = microtime(true);
$service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'));
echo microtime(true) - $start; // the execution time of your code
```

The `Bakame\Aide\Profiler` package streamlines this process by
removing the need for manual timing and setup, making
profiling more convenient and consistent.

### Profiler

#### Metrics quick access

Let's adapt the first example using the `Profiler` class.

```php
use Bakame\Aide\Profiler\Profiler;

$duration = Profiler::metrics(
    $service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'))
)->executionTime;
// $duration is the execution time in nanosecond using hrtime instead of microtime
````

The method returns a `Metrics` class with readonly methods for each metric.

```php
use Bakame\Aide\Profiler\Profiler;

// you create a new Profiler by passing the callback you want to profile
$metrics = Profiler::metrics(
    $service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'))
);

$metrics->executionTime;
$metrics->cpuTime; 
$metrics->memoryUsage;
$metrics->peakMemoryUsage;
$metrics->realMemoryUsage;
$metrics->realPeakMemoryUsage;
````

All duration values are expressed in nanoseconds, while memory-related metrics are measured in bytes.

You can retrieve the `Metrics` statistics in a human-readable format using the `Metrics::forHuman()` method.

You can either:

- Call the method without arguments to retrieve **all metrics** as formatted strings in an associative `array`.
- Or pass the name of a specific metric to retrieve **only that value**, formatted for human readability.

```php
use Bakame\Aide\Profiler\Profiler;

// you create a new Profiler by passing the callback you want to profile
$metrics = Profiler::metrics(
    $service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'))
);

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
use Bakame\Aide\Profiler\Profiler;

$cpuTime = Profiler::metrics(
    $service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24')),
    5
)->cpuTime;
// the average CPU Time used when executing 5 times the code.
````
The `$iterations` argument is available for all metrics.

#### Full report

If you need access to the complete set of statistical data rather than just average values, use the `Profiler::report` method.
This method returns a `Report` instance instead of a `Metrics` object. The `Report` aggregates detailed `Statistics` for each metric,
offering a full performance profile.

```php
use Bakame\Aide\Profiler\Profiler;

// you create a new Profiler by passing the callback you want to profile
$report = Profiler::report(
    $service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'))
);

$report->executionTime->forHuman('average');
$report->cpuTime; 
$report->memoryUsage;
$report->peakMemoryUsage;
$report->realMemoryUsage;
$report->realPeakMemoryUsage;
````

#### Accessing the result

Finally, the static method `Profiler::execute` allows you to retrieve both the result of a callback
execution and its profiling data. It returns a `ProfiledResult` instance, where the `result`
property contains the callback’s return value, and the `summary` property holds the
profiling metrics collected during the call.

```php
use Bakame\Aide\Profiler\Profiler;

$result = Profiler::execute($service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24')));
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
use Bakame\Aide\Profiler\Profiler;

// you create a new Profiler by passing the callback you want to profile
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

To add a custom label to each run, use `Profiler::profile`. This method works like the 
`run` method but allows you to assign a custom label to the returned `Summary` object
via its first argument.

```php
use Bakame\Aide\Profiler\Profiler;

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
use Bakame\Aide\Profiler\Profiler;

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

### Marker

In situation where you can't work with callbacks you can alternatively use the `Marker` class.

The `Marker` class profiles across labeled checkpoints ("snapshots") in your
code. You can start a new `Marker` using the static method:

```php
use App\Profiler\Marker;

$marker = Marker::start('boot');
```

#### Taking Snapshots

Use `mark()` to mark significant points in your code:

```php
$marker->mark('init');
// some code
$marker->mark('load');
// some code
$marker->mark('render');
```

Each label must be unique. Labels are automatically normalized (e.g., trimmed, validated).

#### Getting profiling results

To get a high-level profile between the **first and lastest** snapshot use the `summarize` method.

```php
$summary = $marker->summary();         // Returns a Summary instance
echo $summary->metrics->executionTime; // Access execution time, CPU time, memory, etc.
```
You can provide a custom label for the summary:

```php
$summary = $marker->summary('full_request'); // Returns a Summary instance
```

If needed, you can measure the profiling data between two specific labels:

```php
$delta = $marker->delta('init', 'render'); // Returns Summary
$executionTime = $marker->executionTime('init', 'render'); // Returns a float in nanoseconds
```
There are as many methods as there are metrics:

```php
$marker->executionTime('init', 'render');
$marker->cpuTime('init', 'render');
$marker->memoryUsage('init', 'render');
$marker->realMemoryUsage('init', 'render');
$marker->peakMemoryUsage('init', 'render');
$marker->realPeakMemoryUsage('init', 'render');
$marker->metrics('init', 'render'); //returns a Metrics instance
```
If you do not specify the second label, the method will default to using the latest snapshot
as the second argument.

```php
$marker->executionTime('init', 'render');
//is equivalent to
$marker->executionTime('init');
```

You can iterate over each successive pair of snapshots to return the consecutive deltas:

```php
foreach ($marker->deltas() as $summary) {
    echo $summary->label . ': ' . $summary->metrics->forHuman('execution_time') . PHP_EOL;
}
```

You can also take a snapshot and directly return the calculated summary between the `Marker`
first snapshot and the one you just take using the `take` method

```php
$summary = $marker->take('done'); // takes a snapshot labeled 'done' and returns a Summary instance
```

Just like with the `summary` method you can provide an optional custom label for the summary report:

```php
$summary = $marker->take(label: 'done', summaryLabel: 'total');
```

#### Finalizing the marker

```php
$marker->complete();
```

The `complete` method finalizes the profiling marker, marking it as complete and preventing any
further snapshots or operations that modify the state. 

Before calling `complete`, the marker is **open** and can accept snapshots via `mark`
or `take` methods. Once `complete` is called:

- The marker becomes **complete and is closed to further modifications.**
- Further calls to `mark` or `take` will throw an `UnableToProfile` exception.
- Calling `complete` multiple times has no effects - it is **idempotent**.
- The result of `summary` remains unchanged after completion and can be safely called multiple times.

At any given time you can check your `Marker` completion status using the `Marker::isComplete`
method which returns `true` when it is complete; false otherwise.

#### Marker utility methods

The `Marker` instance also gives you access to other utility methods:

```php
$marker->labels();             // returns all the snapshot labels (in order)
$marker->hasLabel($label);     // tells whether the label is used
$marker->first();              // returns the first snapshot taken
$marker->latest();             // returns the most recent snapshot
$marker->isEmpty();            // returns true when no snapshot has been taken
$marker->hasSnapshots();       // returns true when snapshots are available
$marker->hasEnoughSnapshots(); // returns true if the marker can safely generate a report/summary
$marker->toArray();            // returns all snapshots as structured arrays
$marker->isComplete();         // tells whether the marker is complete
$marker->reset();              // Reset the marker to its initial state open and with no snapshot
```

> [!IMPORTANT]  
> The `reset()` method reopens the marker and clears all recorded snapshots,
> enabling it to be reused for a new profiling session.

As an example, you can do the following:

```php
$marker = Marker::start('request');

doSomething();
$marker->mark('step1');

sleep(1);
$marker->mark('step2');

$result = $marker->take('response');
$marker->complete();

// Printing full report
foreach ($marker->deltas() as $summary) {
    echo "{$summary->label}: {$summary->metrics->forHuman('execution_time')}";
}
```

And we can adapt the first example using the `Marker` class this time.

```php
use Bakame\Aide\Profiler\Marker;

$marker = Marker::start('start');
$service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'));
$marker->mark('end');
$duration = $marker->executionTime('start', 'end');
// $duration is expressed in nanoseconds
````

### Identifier

Every `Marker` and `Profiler` instance has a unique identifier accessible via the `identifier` method.

```php
use Bakame\Aide\Profiler\Marker;
use Bakame\Aide\Profiler\Profiler;

$marker = Marker::start(label: 'start', identifier: 'user_import');
// or 
$marker = new Marker(identifier: 'user_import');
$marker->mark(label: 'start');

echo $marker->identifier(); // 'user_import'

$profiler = new Profiler(function (): string {
    usleep(1_000);
    
    return 'done';
}, 'user_export');
echo $profiler->identifier(); // 'user_export
```

If not provided, an internal label generator will assign a unique name to the property.
The identifier can be used for logging, debugging or for correlation when
multiple profilers and/or markers are running in parallel.

### Logging

The `Profiler` and `Marker` classes can optionally log profiling activity using any logger that
implements `Psr\Log\LoggerInterface`.

To enable this feature, you must install and configure a `PSR-3`-compatible logger. Common
implementations include `Monolog`, `Laminas\Log`, `Symfony’s or Laravel logger` component, and others.

```php
use Bakame\Aide\Profiler\Marker;
use Bakame\Aide\Profiler\Profiler;
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

//logging the marker process 

$marker = Marker::start('init', logger: $logger);
usleep(1_000);;
$marker->take('render', 'server_cycle');
```

> [!TIP]  
> Logging can be done also on the `Profiler` static methods, they all optionally accept a `LoggerInterface` argument.
> When logging marker or profiler instance their respective identifier is added to the log to ease identifying
> which instance is generating the log entries.

### Exporters

The package can help with exporting its metrics using different mechanisms.

#### JSON

Both the `Profiler` and `Marker` classes support JSON export via PHP's `json_encode` function.
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

Calling `json_encode($marker)` will produce a JSON object containing:

- `identifier`: the marker's unique identifier
- `snapshots`: an array of snapshot entries, **ordered from oldest to latest**

```php
echo json_encode($marker), PHP_EOL;
```
See a [sample marker JSON output](./examples/marker-sample.json) for a complete structure.

#### CLI

If you have the `symfony\console` package installed in your application, you can export
the `Profiler` or the `Marker` using a table showing all the data recorded by 
each instance using the `ConsoleTableExporter` class.

```php
use Bakame\Aide\Profiler\Profiler;
use Bakame\Aide\Profiler\ConsoleTableExporter;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler->profile('first_run', 1, 2);
$profiler->profile('last_run', 1, 2);
$profiler(1, 2);

$renderer = new ConsoleTableExporter();
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

The `Profiler` and the `Marker` results can be exported to an Open telemetry compatible
server using the `open-telemetry/exporter-otlp` package.

To do so, first install the package if it is not yet the case, then do the following:

```php
use Bakame\Aide\Profiler\OpenTelemetryExporter;
use Bakame\Aide\Profiler\Profiler;
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
use Bakame\Aide\Profiler\Environment;;

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
use Bakame\Aide\Profiler\Environment;

$system = Environment::current();
$system->is32Bit();         // returns true on a 32-bit architecture
$system->is64Bit();         // returns true on a 64-bit architecture
$system->unlimitedMemory(); // returns true if there is no memory limit
$system->isWindows();       // returns true if the OS is a Windows
$system->isMac();           // returns true if the OS is a Mac
$system->isUnixLike();      // returns true if the OS is a Unix like
````

The `ConsoleTableExporter` also provides an exporter for the class:

```php
use Bakame\Aide\Profiler\ConsoleTableExporter;
use Bakame\Aide\Profiler\Environment;;

(new ConsoleTableExporter())->exportEnvironment($system);
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

#### MemoryUnit and DurationUnit

To correctly show the memory and duration unit, the package comes with 2 helper Enum:

- `MemoryUnit` to help formatting and converting to and from bytes.
- `DurationUnit` to help formatting and converting to and from nanoseconds.

```php
use Bakame\Aide\Profiler\MemoryUnit;
use Bakame\Aide\Profiler\DurationUnit;

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
