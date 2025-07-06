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

Traditionally, you will use `microtime` to quickly profile your snippet.

```php
$start = microtime(true);
$service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'));
echo microtime(true) - $start; // the execution time of your code
```

The `Bakame\Aide\Profiler` package is a utility that simplifies profiling by eliminating the need to manually calculate and configure your code.

### Basic usage

Let's adapt the first example using the `Profiler` class.

```php
use Bakame\Aide\Profiler\Profiler;

$duration = Profiler::executionTime(
    $service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24'))
);
// $duration is the execution time in nanosecond using hrtime instead of microtime
````
They are as many methods as they are metrics:

- `Profiler::executionTime()`;
- `Profiler::cpuTime()`;
- `Profiler::memoryUsage()`;
- `Profiler::peakMemoryUsage()`;
- `Profiler::realMemoryUsage()`;
- `Profiler::realPeakMemoryUsage()`;

If you want to access all the metrics at once, you can use the following method

- `Profiler::metrics()`;

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

### Iterations

If you need to access the average usage for a specific metric, you can use the second argument.
When executing a callback more than once, the average value for that specific metric across all iterations
will be returned:

```php
use Bakame\Aide\Profiler\Profiler;

$cpuTime = Profiler::cpuTime(
    $service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24')),
    5
);
// the average CPU Time used when executing 5 times the code.
````
The `$iterations` argument is available for all metrics.

### Returning the result

Last but not least, it is possible to access the result from executing a call as well as its associated profile
using the static method `Profiler::execute`. The method returns a `ProfilingResult`
instance where the `result` property represents the returned value of the callback execution while its `profilingData`
property contains all the data related to profiling the call.

```php
use Bakame\Aide\Profiler\Profiler;

$profiling = Profiler::execute($service->calculateHeavyStuff(new DateTimeImmutable('2024-12-24')));
$profiling->result; // the result of executing the `calculateHeavyStuff` method
$profiling->profilingData; // the profiling data associated with the call.
$profiling->profilingData->metrics; // returns a Metrics instance
$profiling->profilingData->start;   // returns a Snapshot instance
$profiling->profilingData->end;     // returns a Snapshot instance
$profiling->profilingData->label;   // returns an identifier as a string
````

### Metrics recording

Apart from these static methods the `Profiler` can record each of the calls you made. For that, you
will need to instantiate a new `Profiler` instance with the call you want to profile.

```php
use Bakame\Aide\Profiler\Profiler;

// you create a new Profiler by passing the callback you want to profile
$profiler = new Profiler($service->calculateHeavyStuff(...));

//we invoke the __invoke method of the profile which will execute the callback
//$result is the result of executing the calculateHeavyStuff method
$result = $profiler(new DateTimeImmutable('2024-12-24'));

$profilingData = $profiler->last(); // returns the ProfilingData from the last call
// the $profilingData->metrics property returns a Metrics instance
$metrics = $profilingData->metrics;

$metrics->executionTime;
$metrics->cpuTime; 
$metrics->memoryUsage;
$metrics->peakMemoryUsage;
$metrics->realMemoryUsage;
$metrics->realPeakMemoryUsage;
````

You can execute the `Profiler` instance as many times as needed — it will record all execution metrics each time.

```php
$result1 = $profiler(new DateTimeImmutable('2024-12-24'));
$result2 = $profiler(new DateTimeImmutable('2025-03-02'));
$result3 = $profiler(new DateTimeImmutable('2024-05-11'));

count($profiler);     // the number of ProfilingData already recorded
$profiler->last();    // returns the ProfilingData from the last call
$profiler->nth(-1);   // returns the same ProfilingData as Profile::last
$profiler->first();   // returns the first ProfilingData ever generated
$profiler->isEmpty(); // returns false because the profiler already contains recorded ProfilingData
$profiler->average(); // returns a Metrics instance representing the average metrics of all the calls performed by the profiler instance
```

You can access any `ProfilingData` by index using the `nth` method, or use the `first` and `last` methods
to quickly retrieve the first and last recorded `ProfilingData`. The `nth` method also accepts negative
integers to simplify access from the end of the list.

### Using labels

To add a custom label to each run, use `Profiler::runWithLabel`. This method works like the `__invoke`
method but allows you to assign a custom label to the returned `ProfilingData` object via its first argument.

```php
use Bakame\Aide\Profiler\Profiler;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler(1, 2, 3); // returns 6
$profilingData = $profiler->last();              // returns the last ProfilingData object from the last call
$profiler->runWithLabel('my_test', 7, 8, 9);     // returns 24
$namedProfilingData = $profiler->get('my_test'); // returns the associated ProfilingData

$profiler->get('foobar'); // returns null because the `foobar` label does not exist
$profiler->has('foobar'); // returns false because the label does not exist
$profiler->labels();      // returns all the labels attached to the Profiler
$profiler->average('my_test'); // returns the Metrics average for all the calls whose label is `my_test`
````

You can reuse the same label multiple times. Calling `Profiler::get()` returns the most recent
entry associated with the given label. In contrast, `Profiler::getAll()` returns an `array` of
all entries recorded under that label, ordered from the oldest to the newest. If the label
is invalid or has never been used, an empty `array` will be returned. To check whether
a label has been used, you can call `Profiler::has()`, which returns `true`
if the label exists, or `false` otherwise.

### Resetting the Profiler

At any given time you can reset the `Profiler` by clearing all the `ProfilingData` already recorded.

```php
use Bakame\Aide\Profiler\Profiler;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler(1, 2, 3);
$profiler->runWithLabel('my_test', 4, 5, 6);
$profiler(7, 8, 9);

count($profiler); // returns 3
$profiler->isEmpty(); // return false

$profiler->reset();

count($profiler); // returns 0
$profiler->isEmpty(); // return true
```

> [!NOTE]  
> PHP provides a `reset_peak_memory_usage` that will globally reset all peak memory usage data.

### Logging

The `Profiler` can optionally take a `Psr\LoggerInterface` implementing object to log the profiling process.
First you need to install and configure a `PSR-3` compliant package as shown below:

```php
use Bakame\Aide\Profiler\Profiler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('profiler');
$logger->pushHandler(new StreamHandler(STDOUT, Level::Debug));

$profiler = new Profiler(function () {
    usleep(1000);

    return 'end';
}, $logger);

$profiler->runWithLabel('toto');
$profiler->runWithLabel('tata');
```

You will see in your terminal the following output since we used Monolog `StreamHandler`.

```bash 
[2025-06-26T16:26:54.935597+00:00] profiler.INFO: Starting profiling for label: toto [] []
[2025-06-26T16:26:54.937517+00:00] profiler.INFO: Finished profiling for label: toto {"cpu_time":2.1e-5,"memory_usage":2536.0,"real_memory_usage":0.0,"peak_memory_usage":0.0,"real_peak_memory_usage":0.0} []
[2025-06-26T16:26:54.937570+00:00] profiler.INFO: Starting profiling for label: tata [] []
[2025-06-26T16:26:54.938688+00:00] profiler.INFO: Finished profiling for label: tata {"cpu_time":1.3000000000000001e-5,"memory_usage":2536.0,"real_memory_usage":0.0,"peak_memory_usage":0.0,"real_peak_memory
```
> [!TIP]  
> Logging can be done also on the static methods, they all optionally accept a `LoggerInterface` argument.

### Exporters

The package can help with exporting its `ProfilingData` using different mechanisms.

#### JSON Exporter

You can export the `Profiler` as a JSON string using the `json_encode` method.
The JSON representation will return the timestamp, the snapshots as well as the metrics
associated to all the `ProfilingData` instances attached to the object.

```php
echo json_encode($profiler), PHP_EOL;
```

#### CLI Exporter

If you have the `symfony\console` package installed in your application, you can export
the `Profiler` using a table showing all the profiles recorded by the profiler using
the `CliTableRenderer` class.

```php
use Bakame\Aide\Profiler\Profiler;
use Bakame\Aide\Profiler\ConsoleTableExporter;

$callback = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callback);
$profiler->runWithLabel('first_run', 1, 2);
$profiler->runWithLabel('last_run', 1, 2);
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

#### Open Telemetry Exporter

The `Profiler` results can be exported to a Open telemetry compatible server using the `open-telemetry/exporter-otlp` package.

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
$profiler->runWithLabel('first_run', 1, 2);
$profiler->runWithLabel('last_run', 1, 2);
$profiler(1, 2);

$exporter->exportProfilter($profiler); 
// the Profiler content is exported to the Open Telemetry Server.
```

Remember to change the `$tracerProvider` to connect to your own environment and server.

### Helpers

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
```

The package also includes an `Environment class that collects information about the current system for profiling purposes.

```php
use Bakame\Aide\Profiler\Environment;Environment;

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

var_dump($system->toArray()); // returns the values as an associative array
array(13) {
  ["os"]=>
  string(5) "Linux"
  ["osFamily"]=>
  string(5) "Linux"
  ["hostname"]=>
  string(8) "example.org"
  ["machine"]=>
  string(6) "x86_64"
  ["phpIntSize"]=>
  int(8)
  ["phpArchitecture"]=>
  string(6) "64-bit"
  ["phpVersion"]=>
  string(6) "8.3.18"
  ["sapi"]=>
  string(3) "cli"
  ["memoryLimit"]=> 
  int(67108864)
  ["rawMemoryLimit"]=>
  string(3) "64M"
  ["cpuCores"]=>
  int(1)
  ["totalDisk"]=>
  float(0)
  ["freeDisk"]=>
  float(0)
}
```

Appart from returnong raw information about your system, the instance can be used to detect the PHP architecture
used or if the memory is unlimited using boolean returning methods:

```php
use Bakame\Aide\Profiler\Environment;Environment;

$system = Environment::current();
$system->is32Bit(); // returns true on a 32-bit architecture
$system->is64Bit(); // returns true on a 64-bit architecture
$system->unlimitedMemory(); // returns true if there is no memory limit
````

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
