---
layout: homepage
---

# Introduction

**Stackwatch** is a lightweight profiler for PHP 8.1+.  It helps you measure performance with 
precision—without unnecessary complexity.

Once installed, it will allow you to

## Profile any block using closures

```php
$duration = stack_bench(function () {
    // code that will be profiled
})->executionTime;
// $duration is the execution time in nanosecond using hrtime instead of microtime
````

## Segment code with named checkpoints

```php
use Bakame\Stackwatch\Timeline;

$timeline = Timeline::start('start');
// the piece of code to be profiled
$timeline->capture('middle');
// another piece of code
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

enum MyEnum
{
    use TimerTrait;

    case Case1;
    case Case2;
}
```

If you mark your code with the `Bakame\Stackwatch\Profile` attribute and run the CLI command:

```bash
php vendor/bin/stackwatch --path=/path/to/profiling/code.php
```
It will output the following with no complex setup.

```bash
stackwatch v0.14.0 (Nouakchott) by Ignace Nyamagana Butera and contributors.

Runtime: PHP 8.3.25 OS: Linux Memory Limit: 64M

............................

Target: Foobar\Baz\MyEnum::test; Path: /path/to/profiling/code.php; Iterations: 3; Warmup: 0; Type: Full;

+-------------------------------+------------+------------+------------+------------+------------+------------+--------------+---------------+-----------+-----------+
| Metrics                       | iterations | Min Value  | Max Value  | Range      | Sum        | Average    | Median Value | Variance      | Std Dev   | Coef Var  |
+-------------------------------+------------+------------+------------+------------+------------+------------+--------------+---------------+-----------+-----------+
|CPU Time                       |           3|19.000 µs   |78.000 µs   |59.000 µs   |134.000 µs  |44.667 µs   |37.000 µs     |609.556 μs²    |24.689 µs  |55.2743 %  |
|Execution Time                 |           3|143.500 µs  |192.375 µs  |48.875 µs   |486.625 µs  |162.208 µs  |150.750 µs    |463.774 μs²    |21.535 µs  |13.2764 %  |
|Memory Usage                   |           3|3.292 MB    |3.392 MB    |101.578 KB  |10.076 MB   |3.359 MB    |3.391 MB      |2,287.987 KB²  |47.833 KB  |1.3908 %   |
|Memory Usage Growth            |           3|568.000 B   |568.000 B   |0.000 B     |1.664 KB    |568.000 B   |568.000 B     |0.000 B²       |0.000 B    |0.0000 %   |
|Real Memory Usage              |           3|4.000 MB    |4.000 MB    |0.000 B     |12.000 MB   |4.000 MB    |4.000 MB      |0.000 B²       |0.000 B    |0.0000 %   |
|Real Memory Usage Growth       |           3|0.000 B     |0.000 B     |0.000 B     |0.000 B     |0.000 B     |0.000 B       |0.000 B²       |0.000 B    |0.0000 %   |
|Peak Memory Usage              |           3|3.358 MB    |3.513 MB    |159.164 KB  |10.384 MB   |3.461 MB    |3.513 MB      |5,629.600 KB²  |75.031 KB  |2.1168 %   |
|Peak Memory Usage Growth       |           3|0.000 B     |0.000 B     |0.000 B     |0.000 B     |0.000 B     |0.000 B       |0.000 B²       |0.000 B    |0.0000 %   |
|Real Peak Memory Usage         |           3|4.000 MB    |4.000 MB    |0.000 B     |12.000 MB   |4.000 MB    |4.000 MB      |0.000 B²       |0.000 B    |0.0000 %   |
|Real Peak Memory Usage Growth  |           3|4.000 MB    |4.000 MB    |0.000 B     |12.000 MB   |4.000 MB    |4.000 MB      |0.000 B²       |0.000 B    |0.0000 %   |
+-------------------------------+------------+------------+------------+------------+------------+------------+--------------+---------------+-----------+-----------+
```

## PHPUnit helper traits

A **fluent, unit-aware PHPUnit helper** for asserting profiler metrics.

```php
use Bakame\Stackwatch\MetricsAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    use MetricsAssertions;

    #[Test]
    public function it_can_test_callback_performance(): void
    {
        $performance = $this
             ->iter(5)
             ->warmup(2)
             ->aggMedian()
             ->assertMetrics($service->calculateHeavyStuff(...));
        
        $performance->executionTime()->lessThan(200, 'milliseconds')
        $performance->memoryUsage()->greaterThan(10, 'mb');
    }
}
```

# Motivation

**Stackwatch** bridges the gap between basic timers and heavy profiling tools like [PHPBench](https://phpbench.readthedocs.io/en/latest/), [Xdebug](https://xdebug.org/) or [Blackfire](https://www.blackfire.io/).
It is perfect for:

- Isolated performance testing
- Annotated profiling of large codebases
- Lightweight integration into dev workflows
