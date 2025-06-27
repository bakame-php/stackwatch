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

### Composer

~~~
composer require bakame/aide-profiler
~~~

### System Requirements

You need:

- **PHP >= 8.1** but the latest stable version of PHP is recommended

## Usage

Traditionally, you will use `microtime` to quickly profile your snippet.

The `Bakame\Aide\Profiler` package is an utility class that helps you remove
the burden of calculating and setting up your code for profiling.

### Basic usage

```php
use Bakame\Aide\Profiler\Profiler;

$callable = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callable);
$result = $profiler(1, 2, 3);
//$result equals 6;
$profile = $profiler->lastProfile(); // returns the Profile from the last call
// the $profile object returns the following metrucs
$profile->executionTime();
$profile->cpuTime(); 
$profile->memoryUsage();
$profile->peakMemoryUsage();
$profile->realMemoryUsage();
$profile->realPeakMemoryUsage();
````

### Using labels

You can add a custom label on each run if you use `Profiler::runWithLabel` this method does the same as the `__invoke` method but
allows to add a custom label to the return `Profile` obejct submitted via the method first argument.

```php
use Bakame\Aide\Profiler\Profiler;

$callable = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callable);
$profiler(1, 2, 3); // returns 6
$profile = $profiler->lastProfile(); // returns the last Profile object from the last call
$profiler->runWithLabel('my_test', 7, 8, 9); // returns 24
$namedProfile = $profiler->get('my_test'); // will return the associated Profile

$profiler->get('foobar'); //returns null because the `foobar` label does not exist
$profiler->has('foobar'); //return false because the label does not exist
$profiler->labels(); //will return all the labels attached to the Profiler
````

> [!NOTE]  
> If you do not provide a label, the `Profiler` will generate a unique label for each run.

### Resetting the Profiler

At any given time you can reset the `Profiler` by clearing all the profiles already run.

```php
use Bakame\Aide\Profiler\Profiler;

$callable = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callable);
$profiler(1, 2, 3);
$profiler->runWithLabel('my_test', 4, 5, 6);
$profiler(7, 8, 9);

count($profiler); // returns 3
$profiler->isEmpty(); // return false

$profiler->reset();

count($profiler); // returns 0
$profiler->isEmpty(); // return true
```

### Logging

The `Profiler` can optionally take a `Psr\LoggerInterface` implementing object to log the profiling process.

```php
use Bakame\Aide\Profiler\Profiler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('profiler');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

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

### JSON Rendering

You can convert the `Profiler` into its JSON representation using the `json_encode` method.
The JSON representation will return the timestamp, the snapshots as well as the metrics
associated to all the `Profile` instances attached to the object.

```php
echo json_encode($profiler), PHP_EOL;
```

### CLI Rendering

If you have the `symfony\console` package installed in your application, you can generate
a table showning all the profiles generated by the profiler using the `CliTableRenderer`.

```php
use Bakame\Aide\Profiler\Profiler;
use Bakame\Aide\Profiler\CliTableRenderer;

$callable = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callable);
$profiler->runWithLabel('first_run', 1, 2);
$profiler->runWithLabel('last_run', 1, 2);
$profiler(1, 2);

$renderer = new CliTableRenderer();
$renderer->render($profiler);
```
the following table will be outputted in your terminal.

```bash
+--------------+--------------+---------------+-------------+---------------+---------------+----------------+
| Label        | CPU Time (s) | Exec Time (s) | Memory (kB) | Real Mem (kB) | Peak Mem (kB) | Real Peak (kB) |
+--------------+--------------+---------------+-------------+---------------+---------------+----------------+
| first_run    | 0.000094     | 0.001270      | 2.5         | 0.0           | 0.0           | 0.0            |
| last_run     | 0.000009     | 0.001259      | 2.5         | 0.0           | 0.0           | 0.0            |
| 2dc8fd3a8c5e | 0.000009     | 0.001260      | 2.5         | 0.0           | 0.0           | 0.0            |
+--------------+--------------+---------------+-------------+---------------+---------------+----------------+
```

### Open telemetry exporter

The `Profiler` results can be exported to a Open telemetry compatible server using the `open-telemetry/exporter-otlp` package.

To do so, first install the package if it is not yet the case, then do the following:

```php
use Bakame\Aide\Profiler\OpenTelemetryExporter;
use Bakame\Aide\Profiler\Profiler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

// add a logger is optional.
$logger = new Logger('profiler');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$tracerProvider = new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter()));
$exporter = new OpenTelemetryExporter($tracerProvider, $logger);

$callable = function (int ...$args): int|float => {
    usleep(100)
    
    return array_sum($args);
}; 

$profiler = new Profiler($callable);
$profiler->runWithLabel('first_run', 1, 2);
$profiler->runWithLabel('last_run', 1, 2);
$profiler(1, 2);

$exporter->exportProfilter($profier); 
// the Profiler content is exported to the Open Telemetry Server.
```

Do not forget to change the `$tracerProvider` to connect to your own environment and server.

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