---
layout: default
title: Profile Exporters
---

# Exporters

The package can help with exporting its metrics using different mechanisms.

## JSON

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

<div class="message-warning">
If you try to store multiple export in the same file (specified by a string) They will get overwritten
and only the last export will be stored. To get the data appended provide an already open <code>resource</code> or <code>SplFileObject</code>.
</div>

## CLI

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

## Open Telemetry

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
