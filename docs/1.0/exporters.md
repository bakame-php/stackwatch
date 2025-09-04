---
layout: default
title: Profile Exporters
---

# Exporters

The package can help with exporting its metrics using different mechanisms.

## JSON

Both the `SpanAggregator` and `Timeline` classes support JSON export via PHP's `json_encode` function.
This allows you to serialize profiling data for inspection, storage, or transmission.

Calling `json_encode($profiler)` will produce a JSON object containing:

- `identifier`: the profiler's unique identifier
- `spans`: an array of summary entries, **ordered from oldest to latest**

Each summary entry includes:

- `label`: the associated label or name of the profiling block
- `snapshots`: an array of two snapshots (start and end), ordered chronologically
- `metrics`: computed performance metrics between the two snapshots

```php
echo json_encode($profiler), PHP_EOL;
```

See a [sample span aggregator JSON output](./examples/profiler-sample.json) for a complete structure.

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
use Bakame\Stackwatch\JsonExporter;
use Bakame\Stackwatch\Profiler;

$report = Profiler::report($service->calculateHeavyStuff(...), 500);
$exporter = new JsonExporter('path/to/store/the/profile.json', JSON_PRETTY_PRINT|JSON_BIGINT_AS_STRING);
$exporter->exportReport($report);
```
The report will be stored in the designated location.

<div class="message-warning">
If you try to store multiple export in the same file (specified by a string) They will get overwritten
and only the last export will be stored. To get the data appended provide an already open <code>resource</code> or <code>SplFileObject</code>.
</div>

## CLI

You can display the `SpanAggregator` or the `Timeline` recorded data recorded using the `StatsExporter` class.

```php
use Bakame\Stackwatch\SpanAggregator;
use Bakame\Stackwatch\ViewExporter;

$callback = function (int ...$args): int {
    usleep(100);

    return array_sum($args);
};

$aggregator = new SpanAggregator($callback);
$aggregator->profile('first_run', 1, 2);
$aggregator->profile('last_run', 1, 2);
$aggregator->run(1, 2);

$exporter = new ViewExporter();
$exporter->exportSpanAggregator($aggregator);
```
the following table will be outputted in your terminal.

```bash
                                                                                                        profiling my callback
+--------------+-----------+----------------+--------------+---------------------+-------------------+--------------------------+-------------------+--------------------------+------------------------+-------------------------------+
| Label        | CPU Time  | Execution Time | Memory Usage | Memory Usage Growth | Real Memory Usage | Real Memory Usage Growth | Peak Memory Usage | Peak Memory Usage Growth | Real Peak Memory Usage | Real Peak Memory Usage Growth |
+--------------+-----------+----------------+--------------+---------------------+-------------------+--------------------------+-------------------+--------------------------+------------------------+-------------------------------+
|first_run     |65.000 µs  |161.500 µs      |1.7 MB        |568.0 B              |4.0 MB             |0.0 B                     |2.1 MB             |0.0 B                     |4.0 MB                  |0.0 B                          |
|last_run      |24.000 µs  |148.834 µs      |1.8 MB        |568.0 B              |4.0 MB             |0.0 B                     |2.1 MB             |0.0 B                     |4.0 MB                  |0.0 B                          |
|d9d12d3b7b72  |18.000 µs  |146.667 µs      |1.8 MB        |568.0 B              |4.0 MB             |0.0 B                     |2.1 MB             |0.0 B                     |4.0 MB                  |0.0 B                          |
+--------------+-----------+----------------+--------------+---------------------+-------------------+--------------------------+-------------------+--------------------------+------------------------+-------------------------------+
```
