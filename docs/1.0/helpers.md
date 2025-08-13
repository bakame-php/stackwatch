---
layout: default
title: Profiling Helpers
---

# Helpers

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
````

The `ConsoleExporter` also provides an exporter for the class:

```php
use Bakame\Stackwatch\Exporter\ConsoleExporter;
use Bakame\Stackwatch\Environment;

(new ConsoleExporter())->exportEnvironment($system);
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

## Unit of Measurement

To correctly show the memory and duration unit, the package comes with 2 helper Enum:

- `MemoryUnit` to help formatting and converting to and from bytes.
- `DurationUnit` to help formatting and converting to and from nanoseconds.

```php
use Bakame\Stackwatch\MemoryUnit;
use Bakame\Stackwatch\DurationUnit;

MemoryUnit::format(1_024 ** 2); // returns '1 MB'
MemoryUnit::parse('1 kb'); // returns 1000 in bytes

DurationUnit::Second->convertToNano(1); // returns 1_000_000_000
DurationUnit::format('23_000'); // returns 23 µs
DurationUnit::tryParse('28 kb'); // returns null
DurationUnit::Second->convertTo(DurationUnit::Hour, 9_000) // returns 2.5 (the duration expressed in hour)
```
