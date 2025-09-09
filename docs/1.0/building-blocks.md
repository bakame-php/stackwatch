---
layout: default
title: Profiling and Value Objects
---

# Building Blocks

The package relies on a number of **value objects** to compute and render the statistics.

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

### Labels

You can optionally attach a label to a `Snapshot`. If you do not assign a label to the snapshot, one will be 
automatically assigned to it. Labels can include lowercase letters, digits, dots, underscores,
and hyphens—but cannot start or end with a symbol, nor have consecutive symbols.
This ensures all labels are **safe, consistent, and easy to reference** throughout your codebase and can
interact with 3rd party packages or systems.

### Comparing Snapshots

Two `Snapsnot` instances can be compared using the following methods

```php
use Bakame\Stackwatch\Snapshot;

$snapshotStart = Snapshot::now('start');
usleep();
$snapshotEnd = Snapshot::now('end');

$snapshotStart->isBefore($snapshotEnd);             //true
$snapshotStart->isAfter($snapshotEnd);              //false
$snapshotStart->isAtSameTime($snapshotEnd);         //false
$snapshotStart->isAfterOrAtSameTime($snapshotEnd);  //false
$snapshotStart->isBeforeOrAtSameTime($snapshotEnd); //true
$snapshotStart->equals($snapshotEnd);               //true
```

<p class="message-notice">The <code>equals()</code> method takes into account all the instance properties,
whereas the other methods are based on comparing the <code>hrtime</code> property.</p>

## Span

The `Span` class represents a **single profiled block of execution**. Just like snapshots, they are **read-only** after creation.
And they are created using two `Snapshots`.

```php
use Bakame\Stackwatch\Snapshot;
use Bakame\Stackwatch\Span;

$snapshotStart = Snapshot::now('start');
usleep();
$snapshotEnd = Snapshot::now('end');
$span = new Span('execution_block', $snapshotStart, $snapshotEnd);

$span->label;  // the span label
$span->start;  // $snapshotStart
$span->end;    // $snapshotEnd
$span->range;  // represents the file range between the start and end (the location)
$span->metrics // the calculated metrics computed from the 2 snapshots.
```

### Label

The `label` property is similar to the snapshot identity and uses the same requirements. But, in contrast to
the `Snapshot`, **the label value is mandatory**.

### Metrics

The `metrics` property is a `Metrics` value object which exposes the following readonly metric:

- `executionTime`
- `cpuTime`
- `memoryUsage`
- `memoryUsageGrowth`
- `peakMemoryUsage`
- `peakMemoryUsageGrowth`
- `realMemoryUsage`
- `realMemoryUsageGrowth`
- `realPeakMemoryUsage`
- `realPeakMemoryUsageGrowth`

```php
$span->metrics->executionTime;             // in nanoseconds
$span->metrics->cpuTime;                   // in nanoseconds
$span->metrics->memoryUsage;               // in bytes
$span->metrics->memoryUsageGrowth;         // in bytes
$span->metrics->peakMemoryUsage;           // in bytes
$span->metrics->peakMemoryUsageGrowth;     // in bytes
$span->metrics->realMemoryUsage;           // in bytes
$span->metrics->realMemoryUsageGrowth;     // in bytes
$span->metrics->realPeakMemoryUsage;       // in bytes
$span->metrics->realPeakMemoryUsageGrowth; // in bytes
```

### Call Location Range

The `range` property is a `CallRange` value object which allows comparing the call location between
the starting and the ending snapshots.

```php
$span->range->start->path;    // the starting locaton path can be null
$span->range->start->line;    // the starting locaton line can be null
$span->range->end->path;      // the starting locaton path can be null
$span->range->end->line;      // the starting locaton line can be null
$span->range->isCrossFile();  // returns true if the starting and ending are not in the same file.
$span->range->isSameFile();   // returns true if the starting and ending are in the same file.
$span->range->hasStart():     // returns true if a starting location exists
$span->range->hasEnd():       // returns true if a starting location exists
$span->range->sComplete();    // returns true if both location exists
$span->range->isEmpty();      // returns true if no location is present 
/**
 * Returns the inclusive line span of the range (number of lines covered, including start and end).
 * Returns null if the range is cross-file or either line is undefined.
 */
$span->range->lineSpan();
/**
 * Returns the exclusive length of the range (number of lines between start and end).
 * Returns null if the range is cross-file or either line is undefined.
 */
$span->range->length();
$span->range->isForward();       //returns true if the both locations are in the same file and the start location is before the end location 
$span->range->isBackward();      //returns true if the both locations are in the same file and the start location is after the end location 
$span->range->isSameLine();      //returns true if the both locations are the same (same file and same line) 
$span->range->isDifferentLine(); //returns false if the both locations are the same (same file and same line)
```

## Report

The `Report` value object represents a **profiling or metrics summary** of a process, function or operation. It encapsulates **row-level statistics**
for each metric type (CPU time, memory usage, etc.) and provides **column-oriented access** to aggregated metrics.

### Accessing Row-Level Statistics

You can retrieve a single row of metrics ( a `Statistics` object) by metric type:

```php
use Bakame\Stackwatch\MetricType;

$report = stack_report(fn () => true, iterations: 50);
$cpuRow = $report->row(MetricType::CpuTime); //returns a Statistics object
```

### Accessing Column-Level Aggregated Metrics

You can also retrieve aggregated metrics (like min, max, average) **across all types** for a given aggregation:

```php 
use Bakame\Stackwatch\AggregationType;

$report = stack_report(fn () => true, iterations: 50);
$sumColumn = $report->column(AggregationType::Sum); //returns a AggregatedMetrics object
```

### the Statistics Object

A `Statistics` object represents all metrics for a single metric type and exposes the following **public readonly properties**:

- `$type` — the `MetricType`
- `$iterations` — number of iterations recorded
- `$minimum` — minimum value
- `$maximum` — maximum value
- `$range` — difference between maximum and minimum value
- `$sum` — total sum
- `$average` — mean value
- `$median` — median value
- `$variance` — variance
- `$stdDev` — standard deviation
- `$coefVar` — coefficient of variation (unitless, expressed as a percentage)

#### Units

- For duration related metrics (CPU Time, Execution times) values are expressed in **nanoseconds**
- For memory metrics: values are expressed in **bytes**
- For percentages (like Coefficient of Variation), the unit is `%`

### the AggregatedMetrics Object

An `AggregatedMetrics` object represents a single aggregation across all metric types and exposes the same properties as `Metrics`, with a few key differences:

- `$type` — represents the `AggregationType` (e.g., Sum, Average, Variance)
- `$iterations` — number of iterations recorded (always unitless)
- **Units depend on the aggregation type:**
    - `AggregationType::Variance` → values are squared (unit²)
    - `AggregationType::CoefVar` → unitless, expressed in percentage

All other types → values have the same unit as in the corresponding `Metrics` object.

## Exporting the building blocks

All the value objects can be

- Converted to an `array` via `toArray` method,
- JSON-encoded via `json_encode` (implementing the `JsonSerializable` interface)
- Returned in a **human-readable** format via `toHuman()`. (except for the `CallLocation` and the `CallRange` objects.

### Full human-readable export

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

### Accessing individual properties

You can retrieve a single value by providing a key. The `key` is either the property name or its corresponding key
used in the `toArray` representation.

```php
$snapshot->human('cpuUserTime');   // returns "36.923 ms"
$snapshot->human('cpu_user_time'); // returns "36.923 ms"
```

All built-in exporters (`JsonExporter`, `OtlExporter`, `ViewExporter`) export the value object either through a
dedicated `export*()` method where the method prefix is the name of the value object. The exported format
depends on the selected exporter.
