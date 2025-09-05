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

$exporter = new JsonExporter('path/to/store/the/profile.json', JSON_PRETTY_PRINT|JSON_BIGINT_AS_STRING);
$exporter->exportReport(stack_report($service->calculateHeavyStuff(...), 500));
```
The report will be stored in the designated location.

<div class="message-warning">
If you try to store multiple export in the same file (specified by a string) They will get overwritten
and only the last export will be stored. To get the data appended provide an already open <code>resource</code> or <code>SplFileObject</code>.
</div>

## Dumping Results

All the classes are `dumpable` using the `dump` and the `dd` methods. Those methods are context aware.
For instance, you can dump  the `Timeline` recorded data.

```php
use Bakame\Stackwatch\Profiler;
use Bakame\Stackwatch\ViewExporter;

$callback = function (int ...$args): int {
    usleep(100);

    return array_sum($args);
};

$profiler = new Profiler($callback);
$profiler->profile('first_run', 1, 2);
$profiler->profile('last_run', 1, 2);
$profiler->run(1, 2);

$profiler->dump();
```
the following table will be outputted in your terminal.

```bash
                                                    test timeline
+--------+---------------------------+-------------------+-----------+--------------+-------------------+-------------------+------------------------+
| Label  | Call Location             | Timestamp         | CPU Time  | Memory Usage | Real Memory Usage | Peak Memory Usage | Real Peak Memory Usage |
+--------+---------------------------+-------------------+-----------+--------------+-------------------+-------------------+------------------------+
|init    |/path/to/my/script.php:13  |  1757085767.753199|  48.070 ms|      1.759 MB|           4.000 MB|           2.109 MB|                4.000 MB|
|middle  |/path/to/my/script.php:15  |  1757085767.753369|  48.126 ms|      1.760 MB|           4.000 MB|           2.109 MB|                4.000 MB|
|render  |/path/to/my/script.php:17  |  1757085767.753518|  48.151 ms|      1.760 MB|           4.000 MB|           2.109 MB|                4.000 MB|
+--------+---------------------------+-------------------+-----------+--------------+-------------------+-------------------+------------------------+
```
and the following in a browser

<style>
.bkm-sw-container {border: 1px #c5cdd5 solid;background-color: #18171B;padding: .5em .5em;margin: 1em auto;font-family: "IBM Plex Mono", mono, source-code-pro, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;font-size:12px;}
.bkm-sw-container .bkm-sw-header {padding: .3em .7em; font-size: 12px;}
.bkm-sw-container .bkm-sw-table {margin: 0 auto;border-collapse: collapse;width: 100%; font-size:12px}
.bkm-sw-container .bkm-sw-table td,
.bkm-sw-container .bkm-sw-table th {padding: .7em;border: 1px solid #5c636a;text-align: right; color: yellow;}
.bkm-sw-container .bkm-sw-table tr {background-color: #111;}
.bkm-sw-container .bkm-sw-table tbody tr:nth-child(odd) {background-color: #222;}
.bkm-sw-container .bkm-sw-table tbody tr:hover {background-color: #333;}
.bkm-sw-container .bkm-sw-table tbody td {transition:border .2s}
.bkm-sw-container .bkm-sw-table tbody td:hover {border-left: 2px solid #ffa;border-right: 1px solid #ffa;}
.bkm-sw-container .bkm-sw-dotted-list {width: 100%; list-style: none; padding:0;}
.bkm-sw-container .bkm-sw-dotted-item {display: flex; padding: .3em .7em; align-items: center;}
.bkm-sw-container .bkm-sw-dotted-item:hover {background-color: #333;}
.bkm-sw-container .bkm-sw-dotted-item .bkm-sw-dots {flex: 1;border-bottom: 1px dotted #666;margin: 0 0.5em; color:transparent;}
.bkm-sw-container .bkm-sw-ansi-black {color:black;}.bkm-sw-container .hover\:bkm-sw-ansi-black:hover {color:black;}
.bkm-sw-container .bkm-sw-ansi-black-bg {background-color:black;}.bkm-sw-container .hover\:bkm-sw-ansi-black-bg:hover {background-color:black;}
.bkm-sw-container .bkm-sw-ansi-blink {text-decoration:blink;}.bkm-sw-container .hover\:bkm-sw-ansi-blink:hover {text-decoration:blink;}
.bkm-sw-container .bkm-sw-ansi-blue {color:blue;}.bkm-sw-container .hover\:bkm-sw-ansi-blue:hover {color:blue;}
.bkm-sw-container .bkm-sw-ansi-blue-bg {background-color:blue;}.bkm-sw-container .hover\:bkm-sw-ansi-blue-bg:hover {background-color:blue;}
.bkm-sw-container .bkm-sw-ansi-bold {font-weight:bold;}.bkm-sw-container .hover\:bkm-sw-ansi-bold:hover {font-weight:bold;}
.bkm-sw-container .bkm-sw-ansi-bright-black {color:#555555;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-black:hover {color:#555555;}
.bkm-sw-container .bkm-sw-ansi-bright-black-bg {background-color:#555555;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-black-bg:hover {background-color:#555555;}
.bkm-sw-container .bkm-sw-ansi-bright-blue {color:#5555ff;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-blue:hover {color:#5555ff;}
.bkm-sw-container .bkm-sw-ansi-bright-blue-bg {background-color:#5555ff;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-blue-bg:hover {background-color:#5555ff;}
.bkm-sw-container .bkm-sw-ansi-bright-cyan {color:#55ffff;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-cyan:hover {color:#55ffff;}
.bkm-sw-container .bkm-sw-ansi-bright-cyan-bg {background-color:#55ffff;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-cyan-bg:hover {background-color:#55ffff;}
.bkm-sw-container .bkm-sw-ansi-bright-green {color:#55ff55;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-green:hover {color:#55ff55;}
.bkm-sw-container .bkm-sw-ansi-bright-green-bg {background-color:#55ff55;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-green-bg:hover {background-color:#55ff55;}
.bkm-sw-container .bkm-sw-ansi-bright-magenta {color:#ff55ff;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-magenta:hover {color:#ff55ff;}
.bkm-sw-container .bkm-sw-ansi-bright-magenta-bg {background-color:#ff55ff;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-magenta-bg:hover {background-color:#ff55ff;}
.bkm-sw-container .bkm-sw-ansi-bright-red {color:#ff5555;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-red:hover {color:#ff5555;}
.bkm-sw-container .bkm-sw-ansi-bright-red-bg {background-color:#ff5555;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-red-bg:hover {background-color:#ff5555;}
.bkm-sw-container .bkm-sw-ansi-bright-white {color:#ffffff;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-white:hover {color:#ffffff;}
.bkm-sw-container .bkm-sw-ansi-bright-white-bg {background-color:#ffffff;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-white-bg:hover {background-color:#ffffff;}
.bkm-sw-container .bkm-sw-ansi-bright-yellow {color:#ffff55;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-yellow:hover {color:#ffff55;}
.bkm-sw-container .bkm-sw-ansi-bright-yellow-bg {background-color:#ffff55;}.bkm-sw-container .hover\:bkm-sw-ansi-bright-yellow-bg:hover {background-color:#ffff55;}
.bkm-sw-container .bkm-sw-ansi-cyan {color:cyan;}.bkm-sw-container .hover\:bkm-sw-ansi-cyan:hover {color:cyan;}
.bkm-sw-container .bkm-sw-ansi-cyan-bg {background-color:cyan;}.bkm-sw-container .hover\:bkm-sw-ansi-cyan-bg:hover {background-color:cyan;}
.bkm-sw-container .bkm-sw-ansi-dim {opacity:0.7;}.bkm-sw-container .hover\:bkm-sw-ansi-dim:hover {opacity:0.7;}
.bkm-sw-container .bkm-sw-ansi-green {color:green;}.bkm-sw-container .hover\:bkm-sw-ansi-green:hover {color:green;}
.bkm-sw-container .bkm-sw-ansi-green-bg {background-color:green;}.bkm-sw-container .hover\:bkm-sw-ansi-green-bg:hover {background-color:green;}
.bkm-sw-container .bkm-sw-ansi-magenta {color:magenta;}.bkm-sw-container .hover\:bkm-sw-ansi-magenta:hover {color:magenta;}
.bkm-sw-container .bkm-sw-ansi-magenta-bg {background-color:magenta;}.bkm-sw-container .hover\:bkm-sw-ansi-magenta-bg:hover {background-color:magenta;}
.bkm-sw-container .bkm-sw-ansi-red {color:red;}.bkm-sw-container .hover\:bkm-sw-ansi-red:hover {color:red;}
.bkm-sw-container .bkm-sw-ansi-red-bg {background-color:red;}.bkm-sw-container .hover\:bkm-sw-ansi-red-bg:hover {background-color:red;}
.bkm-sw-container .bkm-sw-ansi-reset {all:initial;}.bkm-sw-container .hover\:bkm-sw-ansi-reset:hover {all:initial;}
.bkm-sw-container .bkm-sw-ansi-reverse {filter:invert(100%);}.bkm-sw-container .hover\:bkm-sw-ansi-reverse:hover {filter:invert(100%);}
.bkm-sw-container .bkm-sw-ansi-underline {text-decoration:underline;}.bkm-sw-container .hover\:bkm-sw-ansi-underline:hover {text-decoration:underline;}
.bkm-sw-container .bkm-sw-ansi-white {color:white;}.bkm-sw-container .hover\:bkm-sw-ansi-white:hover {color:white;}
.bkm-sw-container .bkm-sw-ansi-white-bg {background-color:white;}.bkm-sw-container .hover\:bkm-sw-ansi-white-bg:hover {background-color:white;}
.bkm-sw-container .bkm-sw-ansi-yellow {color:yellow;}.bkm-sw-container .hover\:bkm-sw-ansi-yellow:hover {color:yellow;}
.bkm-sw-container .bkm-sw-ansi-yellow-bg {background-color:yellow;}.bkm-sw-container .hover\:bkm-sw-ansi-yellow-bg:hover {background-color:yellow;}
</style>
<div class="bkm-sw-container" id="bkm-sw-timeline-30735"><table class="bkm-sw-table">
<caption style="caption-side:top; text-align:center; padding:1em;color:#55ff55;font-weight:bold;">test timeline</caption>
<thead>
<tr>
<th style="text-align:center;color:#55ff55;">Label</th>
<th style="text-align:center;color:#55ff55;">Call Location</th>
<th style="text-align:center;color:#55ff55;">Timestamp</th>
<th style="text-align:center;color:#55ff55;">CPU Time</th>
<th style="text-align:center;color:#55ff55;">Memory Usage</th>
<th style="text-align:center;color:#55ff55;">Real Memory Usage</th>
<th style="text-align:center;color:#55ff55;">Peak Memory Usage</th>
<th style="text-align:center;color:#55ff55;">Real Peak Memory Usage</th>
</tr>
</thead>
<tbody>
<tr><td style=";;text-align:right" colspan="1">init</td><td style=";;text-align:right" colspan="1"><a class="bkm-sw-ansi-bold bkm-sw-ansi-bright-cyan hover:bkm-sw-ansi-bright-white" href="phpstorm://open?file=%2Fpath%2Fto%2Fmy%2Fscript.php&line=13">/path/to/my/script.php:13</a></td><td style=";text-align:right" colspan="1">1757085970.479185</td><td style=";;text-align:right" colspan="1">4.875 s</td><td style=";;text-align:right" colspan="1">1.759 MB</td><td style=";;text-align:right" colspan="1">4.000 MB</td><td style=";;text-align:right" colspan="1">2.110 MB</td><td style=";;text-align:right" colspan="1">4.000 MB</td></tr>
<tr><td style=";;text-align:right" colspan="1">middle</td><td style=";;text-align:right" colspan="1"><a class="bkm-sw-ansi-bold bkm-sw-ansi-bright-cyan hover:bkm-sw-ansi-bright-white" href="phpstorm://open?file=%2Fpath%2Fto%2Fmy%2Fscript.php&line=15">/path/to/my/script.php:15</a></td><td style=";text-align:right" colspan="1">1757085970.479381</td><td style=";;text-align:right" colspan="1">4.875 s</td><td style=";;text-align:right" colspan="1">1.760 MB</td><td style=";;text-align:right" colspan="1">4.000 MB</td><td style=";;text-align:right" colspan="1">2.110 MB</td><td style=";;text-align:right" colspan="1">4.000 MB</td></tr>
<tr><td style=";;text-align:right" colspan="1">render</td><td style=";;text-align:right" colspan="1"><a class="bkm-sw-ansi-bold bkm-sw-ansi-bright-cyan hover:bkm-sw-ansi-bright-white" href="phpstorm://open?file=%2Fpath%2Fto%2Fmy%2Fscript.php&line=17">/path/to/my/script.php:17</a></td><td style=";text-align:right" colspan="1">1757085970.479553</td><td style=";;text-align:right" colspan="1">4.875 s</td><td style=";;text-align:right" colspan="1">1.761 MB</td><td style=";;text-align:right" colspan="1">4.000 MB</td><td style=";;text-align:right" colspan="1">2.110 MB</td><td style=";;text-align:right" colspan="1">4.000 MB</td></tr>
</tbody>
</table></div>


The `dd` method will dump the result and halt the execution of the script.

### URL in the browser view

The browser view detects the IDE automatically to allow opening the file locally.
This method returns the IDE currently in use by checking environment variables and URL parameters.

It resolves the IDE in this order:

- Checks the URL for a query parameter `?ide=` (e.g., `?ide=vscode`).
- Checks the environment variable `IDE`.
- Uses the optional default you provide.
- Falls back to PhpStorm if none of the above are set or valid.
