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

You can learn more about this `Span` object in the [building block page](/stackwatch/1.0/building-blocks)


## Summary Metrics

Use `stack_bench()` to collect execution time, memory usage, and CPU time:

```php
use Bakame\Stackwatch\DurationUnit;

$metrics = stack_bench($service->calculateHeavyStuff(...));
echo DurationUnit::format($metrics->executionTime); // "1.271 ms"
```
The `$metrics` variable is an `AggregatedMetrics` object. In addition, to exposing aggregated metrics, the object
exposes the `AggregationType` and the number of iterations used via the `type` and `iterations` properties.

You can learn more about this `AggregatedMetrics` in the [building block page](/stackwatch/1.0/building-blocks)

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
    callback: $service->calculateHeavyStuff(...),
    iterations: 5,
    warmup: 2,
    type: AggregationType::Median
);
```

Supported aggregation strategies:

- `Average` (default)
- `Median`
- `Minimum`
- `Maximum`
- `Sum`
- `Range`
- `StdDev`
- `CoefVar`
- `Variance`

## Full Report

For detailed statistics instead of a single number:

```php
use Bakame\Stackwatch\AggregationType;
use Bakame\Stackwatch\MetricType;

$report = stack_report($service->calculateHeavyStuff(...), 100);
$report->row(MetricType::CpuTime)->minimum;
$report->column(AggregationType::Sum)->memoryUsage;

$executionTime = $report->row(MetricType::ExecutionTime);
$executionTime->human('minimum'); // "42.318 μs"
```

Each property is either a `Statistics` object or an `AggregatedMetrics` which
expose profiling data.

You can learn more about this `Statistics` and `AggregatedMetrics` in the [building block page](/stackwatch/1.0/building-blocks)

## Dumping

For quick inspection:

 - `stack_cdump()` → dumps span/metrics, continues execution
- `stack_cdd()` → dumps span/metrics, halts execution
- `stack_rdump()` → dumps detailed report, continues execution
- `stack_rdd()` → dumps detailed report, halts execution
- `stack_mdump()` → dumps summary metrics, continues execution
- `stack_mdd()` → dumps summary metrics, halts execution


| Function         | Returns   | Dumps? | Halts? |
|------------------|-----------|--------|--------|
| `stack_call()`   | `Result`  | ❌      | ❌      |
| `stack_cdump()`  | `Result`  | ✅      | ❌      |
| `stack_cdd()`    | `never`   | ✅      | ✅      |
| `stack_report()` | `Report`  | ❌      | ❌      |
| `stack_rdump()`  | `Report`  | ✅      | ❌      |
| `stack_rdd()`    | `never`   | ✅      | ✅      |
| `stack_bench()`  | `Metrics` | ❌      | ❌      |
| `stack_mdump()`  | `Metrics` | ✅      | ❌      |
| `stack_mdd()`    | `never`   | ✅      | ✅      |
