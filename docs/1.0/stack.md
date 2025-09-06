---
layout: default
title: Scoped Execution Profiling
---

# Stack

Traditionally, profiling a section of code quickly looks like this:

```php
$start = microtime(true);
$service->calculateHeavyStuff();
echo microtime(true) - $start; // the execution time of your code
```

**StackWatch** makes this process simpler and more powerful with a set of **global helper functions**.

## Quick Profiling

```php
$result = stack_call($service->calculateHeavyStuff(...));

$result->returnValue; // the actual return value from the callback
$result->span;        // profiling data (Span object)
```

## Summary Metrics

Use `stack_bench()` to collect execution time, memory usage, and CPU time:

```php
use Bakame\Stackwatch\DurationUnit;

$metrics = stack_bench($service->calculateHeavyStuff(...));
echo DurationUnit::format($metrics->executionTime); // "1.271 ms"
```
Each metric is available as a readonly property:

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

Convert them to human-readable strings with:

```php
$metrics->toHuman();             // array of all formatted values
$metrics->human('memory_usage'); // only one metric
```

## Iterations & Warm-up

Run multiple iterations and skip warm-ups:

```php
$metrics = stack_bench($service->calculateHeavyStuff(...), 5, 2);
```
Here:
    - `5` iterations are recorded
    - `2` warm-up runs are discarded

## Aggregation

Choose how results are aggregated:

```php
use Bakame\Stackwatch\AggregationType;

$metrics = stack_bench(
    $service->calculateHeavyStuff(...),
    5,
    2,
    AggregationType::Median
);
```

Supported aggregation strategies:

- `Average` (default)
- `Median`
- `Minimum`
- `Maximum`
- `Sum`
- `Range`

## Full Report

For detailed statistics instead of a single number:

```php
$report = stack_report($service->calculateHeavyStuff(...), 100);

$report->executionTime->minimum;
$report->executionTime->average;
$report->executionTime->stdDev;

$report->executionTime->human('minimum'); // "42.318 μs"
```

Each property is a `Statistics` object, exposing:

- `minimum`, `maximum`, `sum`,`average`, `median`, `variance`, `stdDev`, `coeffVar`
- `toArray()` for raw data
- `toHuman()` / `human()` for formatted data
- JSON export (implements `JsonSerializable`)

## Dumping

For quick inspection:

 - `stack_cdump()` → dumps span/metrics, continues execution
- `stack_cdd()` → dumps span/metrics, halts execution
- `stack_rdump()` → dumps detailed report, continues execution
- `stack_rdd()` → dumps detailed report, halts execution
- `stack_mdump()` → dumps summary metrics, continues execution
- `stack_mdd()` → dumps summary metrics, halts execution


| Function          | Returns   | Dumps? | Halts? |
|-------------------|-----------|--------|--------|
| `stack_call()`    | `Result`  | ❌      | ❌      |
| `stack_cdump()`   | `Result`  | ✅      | ❌      |
| `stack_cdd()`     | `never`   | ✅      | ✅      |
| `stack_report()`  | `Report`  | ❌      | ❌      |
| `stack_rdump()`   | `Report`  | ✅      | ❌      |
| `stack_rdd()`     | `never`   | ✅      | ✅      |
| `stack_bench()` | `Metrics` | ❌      | ❌      |
| `stack_mdump()`   | `Metrics` | ✅      | ❌      |
| `stack_mdd()`     | `never`   | ✅      | ✅      |
