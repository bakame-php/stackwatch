---
layout: homepage
---

# Introduction

**Stackwatch** is a lightweight profiler for PHP 8.1+.  It helps you measure performance with 
precision—without unnecessary complexity.

Once installed, it will allow you to

## Profile any block using closures

```php
use Bakame\Stackwatch\Profiler;

$duration = Profiler::metrics(function () {
    // code that will be profiled
})->executionTime;
// $duration is the execution time in nanosecond using hrtime instead of microtime
````

## Segment code with named checkpoints

```php
use Bakame\Stackwatch\Timeline;

$timeline = Timeline::start('start');
// the piece of code to be profiled
$duration = $timeline->take('end')->metrics->executionTime;
// $duration is expressed in nanoseconds
````

## Use a CLI command to profile your codebase

Let's say you have the following file `/path/to/profiling/code.php`:

```php
<?php

declare(strict_types=1);

namespace Foobar\Baz;

use Bakame\Stackwatch\Profile;

use function random_int;
use function usleep;

require 'vendor/autoload.php';

trait TimerTrait {
    #[Profile]
    private function test() : int {
        usleep(100);

        return random_int(1, 100);
    }
}

enum Foobar
{
    use TimerTrait;

    case Foobar;
}
```

If you mark your code with the `Bakame\Stackwatch\Profile` attribute and run the CLI command:

```bash
php bin/stackwatch --path=/path/to/profiling/code.php
```
It will output the following with no complex setup.

```bash
stackwatch v0.11.0 (Kampala) by Ignace Nyamagana Butera and contributors.

Runtime: PHP 8.3.23 OS: Linux Memory Limit: 128M

Average metrics for the method Foobar\Baz\Foobar::test located in /path/to/profiling/code.php called 3 times
+------------------------------------+
|         Execution Time: 148.508 µs |
|               CPU Time: 15.500 µs  |
|           Memory Usage: 1.0 KB     |
|      Real Memory Usage: 0.0 B      |
|      Peak Memory Usage: 0.0 B      |
| Real Peak Memory Usage: 0.0 B      |
+------------------------------------+
```

# Motivation

**Stackwatch**  bridges the gap between basic timers and heavy profiling tools like [PHPBench](https://phpbench.readthedocs.io/en/latest/), [Xdebug](https://xdebug.org/) or [Blackfire](https://www.blackfire.io/).
It is perfect for:

- Isolated performance testing
- Annotated profiling of large codebases
- Lightweight integration into dev workflows
