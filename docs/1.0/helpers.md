---
layout: default
title: Profiling Helpers
---

# Helpers

## Snapshot

The `Snapshot` class represents an **immutable profiling snapshot** of the PHP process at a specific moment in time.
It captures **CPU usage, memory usage**, and the call location from which it was created.

It is the **base construct** used to measure resource consumption across different points in code execution.

`Snapshots` are **read-only** after creation.

### Create a Snapshot

You can create a snapshot using the `now()` static method:

```php
use Bakame\Stackwatch\Snapshot;

$snapshot = Snapshot::now('database_query'); // with custom label
$snapshot = Snapshot::now(); // with auto generated label

$snapshot->label; // 'database_query' or a generated one

// When the snapshot was taken
$snapshot->timestamp;

// Where the snapshot was taken
$snapshot->originPath;
$snapshot->originLine;

//Metrics collected at the creation time
$snapshot->memoryUsage;
$snapshot->realMemoryUsage;
$snapshot->peakMemoryUsage;
$snapshot->realPeakMemoryUsage;
$snapshot->cpuUserTime;
$snapshot->cpuSystemTime;
$snapshot->hrtime;
```

### Export a Snapshot

A `Snapshot` can be:

- Converted to an `array` via `toArray` method, 
- JSON-encoded via `json_encode` (implementing the `JsonSerializable` interface)
- Returned in a **human-readable** format via `toHuman()`.

#### Full human-readable export

The human-readable export automatically converts **nanoseconds** and **bytes** into more human-friendly
formats depending on the recorded values.

```php
$snapshot->toHuman(); 
// returns an array
// [
//   "label" => "database_query"
//   "timestamp" => "2025-08-15T16:47:39.025356+00:00"
//   "memory_usage" => "2.181 MB"
//   "real_memory_usage" => "4.000 MB"
//   "peak_memory_usage" => "2.678 MB"
//   "real_peak_memory_usage" => "4.000 MB"
//   "cpu_user_time" => "36.923 ms"
//   "cpu_system_time" => "9.039 ms"
//   "cpu_total_time" => "45.962 ms"
//   "origin_path" => "/path/to/the/profiled/code.php"
//   "origin_line" => "11"
// ]
```

#### Accessing individual properties

You can retrieve a single value by providing a friendly key.
Keys are case-insensitive and may contain spaces, underscores, or dashes — all forms are accepted.

```php
$snapshot->human('cpuUserTime'); // returns "36.923 ms"
$snapshot->human('cpu_user_time'); // returns "36.923 ms"
```

### Comparing Snapshots

Two `Snapsnot` instances can be compared using the `equals()` method.

```php
use Bakame\Stackwatch\Snapshot;

$snapshot = Snapshot::now('test');
usleep();
Snapshot::now('test')->equals($snapshot); // return false
```

The built-in exporters (`JsonExporter`, `ViewExporter` can export a snapshot either internally
or through a dedicated `exportSnapshot()` method. The exported format depends on the selected exporter.

## Environment

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
$system->isCli()            // returns true if the environment is in a CLI context
````

The `ViewExporter` also provides an exporter for the class:

```php
use Bakame\Stackwatch\ViewExporter;
use Bakame\Stackwatch\Environment;

(new ViewExporter())->exportEnvironment($system);
```

Will return

```bash
Operating System ......................................................... Linux
OS Family ................................................................ Linux
Hostname ........................................................... example.org
Architecture ............................................................ x86_64
PHP Integer Size ............................................................. 8
PHP Architecture ........................................................ 64-bit
SAPI ....................................................................... cli
PHP Version ............................................................. 8.3.25
Memory Limit ............................................................. 64 MB
Raw Memory Limit ........................................................... 64M
CPU Cores .................................................................... 1
Disk Size .................................................................... 0
Free Disk Space .............................................................. 0
```

## Unit of Measurement

To correctly show the memory and duration unit, the package comes with **two helper Enum**:

- `MemoryUnit` - for formatting and converting to and from bytes.
- `DurationUnit` - for formatting and converting to and from nanoseconds.

```php
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\DurationUnit;
use Bakame\Stackwatch\UnitSpacing;

MemoryUnit::format(1_024 ** 2); // returns '1 MB'
MemoryUnit::format(1_024 ** 2, UnitSpacing::None); // returns '1MB' //no space between the value and its unit 
MemoryUnit::parse('1 kb'); // returns 1000 in bytes
MemoryUnit::convertTo(MemoryUnit::GB, 2_500_000_000); // returns 2.33 (GB)

DurationUnit::Second->convertToNano(1); // returns 1_000_000_000
DurationUnit::format('23_000'); // returns 23 µs
DurationUnit::format('23_000', UnitSpacing::Space); // returns 23 µs
DurationUnit::format('23_000', UnitSpacing::None); // returns 23µs
DurationUnit::tryParse('28 kb'); // returns null
DurationUnit::Second->convertTo(DurationUnit::Hour, 9_000) // returns 2.5 (the duration expressed in hour)
```

<div class="message-info">
<code>UnitSpacing::None</code> removes the space between the numeric value and the unit.
<code>UnitSpacing::Space</code> preserves the space between the numeric value and the unit.
By default, the method takes <code>UnitSpacing::Space</code>.
</div>

<div class="message-info">Both Enums support unit conversion and human-readable formatting.</div>

## PHPUnit Metrics Assertions Helper

A **fluent, unit-aware PHPUnit helper** for asserting profiler metrics from `stack_measure()`.

This helper provides:

 - Fluent API for metric assertions (executionTime, memoryUsage, etc.)
 - Unit conversions using MemoryUnit and DurationUnit enums
 - Optional configuration for **iterations, warmup runs, and aggregation type**
 - Project-wide default configuration support
 - Relative assertions between metrics
 - Semantic consistency checks

### Installation

Include the `MetricsAssertions` trait in your base test class:

```php
use Bakame\Stackwatch\MetricsAssertions;

abstract class PerformanceTestCase extends TestCase
{
    use MetricsAssertions;

    protected function defaultMetricsConfig(): array
    {
        return [
            'iterations' => 5,
            'warmup'=> 1,
            'type' => AggregationType::Median,
        ];
    }
}
```
Your `MetricsAssert` class must also be loaded.

### Usage

#### Basic metric assertion

```php
$metrics = $this->assertMetrics($service->calculateHeavyStuff(...));

$metrics->executionTime()->lessThan(200, 'ms');
$metrics->memoryUsage()->greaterThan(10, 'mb');
```

#### Fluent configuration

```php
$this->iter(5)
     ->warmup(2)
     ->aggMedian()
     ->assertMetrics($service->calculateHeavyStuff(...))
     ->executionTime()->lessThan(200, 'ms')
     ->memoryUsage()->greaterThan(10, 'mb');
```
#### Aggregation presets

- `->aggAverage()`
- `->aggMedian()`
- `->aggMin()`
- `->aggMax()`
- `->aggSum()`
- `->aggRange()`

Example:

```php
$this->iter(10)
     ->warmup(2)
     ->aggAverage()
     ->assertMetrics($service->calculateHeavyStuff(...))
     ->executionTime()->lessThan(150, 'ms');
```

#### Relative assertions

```php
$this->assertMetrics($service->calculateHeavyStuff(...))
     ->executionTime()->greaterThanMetric('cpuTime')
     ->peakMemoryUsage()->lessThanMetric('memoryUsage');
```

#### Semantic consistency checks

```php
$this->assertMetrics($service->calculateHeavyStuff(...))
     ->assertConsistency()
     ->allNonNegative();
```

- `executionTime >= cpuTime`
- `peakMemoryUsage >= memoryUsage`
- `realPeakMemoryUsage >= realMemoryUsage`

#### Assertions API

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

#### Fluent configuration API

| Method                                     | Alias                          | Description                     |
|--------------------------------------------|--------------------------------|---------------------------------|
| `withIterations(int)`                      | `iter(int)`                    | Number of iterations            |
| `withWarmup(int)`                          | `warmup(int)`                  | Number of warmup runs           | 
| `withAggregation(?AggregationType = null)` | `agg(?AggregationType = null)` | Aggregation type                |
| `aggAverage()`                             | —                              | AggregationType::Average preset |
| `aggMedian()`                              | —                              | AggregationType::Median preset  | 
| `aggMin()`                                 | —                              | AggregationType::Min preset     | 
| `aggMax()`                                 | —                              | AggregationType::Max preset     | 
| `aggSum()`                                 | —                              | AggregationType::Sum preset     |
| `aggRange()`                               | —                              | AggregationType::Range preset   |


#### Of Note

**Unit-aware:** Memory metrics default to **bytes**, time metrics default to **nanoseconds**. You can pass a
`MemoryUnit` or `DurationUnit` enums for conversions or a known string abbreviation for a duration or a memory
unit.

**Project-wide defaults:** Override `defaultMetricsConfig()` in your base test class to define default
iterations, warmup, and aggregation type.

**Fluent and safe:** Config resets automatically after each `assertMetrics()` call to prevent leaks between tests.
