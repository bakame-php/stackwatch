---
layout: default
title: Attribute-Driven CLI Profiling
---

# CLI command

Outside the `Profiler`and the `Timeline` you can use the package features through a CLI command.
A CLI Command is available to allow you to benchmark PHP **functions and methods** located in a
specific file or directory using the custom `#[Bakame\Stackwatch\Profile]` attribute.

This is especially useful for:

- Automating performance regressions in CI pipelines
- Profiling code outside the context of an application

## Usage

```bash
php vendor/bin/stackwatch --path=PATH [options]
```

| Option                | Description                                                             |
|-----------------------|-------------------------------------------------------------------------|
| `-p, --path=PATH`     | Path to scan for PHP files to profile (required)                        |
| `-o, --output=OUTPUT` | Path to store the profiling output (optional)                           |
| `-f, --format=FORMAT` | Output format: `table` or `json` (default: `table`)                     |
| `-d, --depth=DEPTH`   | Recursion depth (`0` = current dir only, default: unlimited) (optional) |
| `-n, --no-recursion`  | Disable directory recursion (optional)                                  |
| `-x, --isolation`     | Enable profiling each file in a separate process                        |
| `--dry-run`           | List profiling targets without executing them                           |
| `-P, --pretty`        | Pretty-print the JSON/NDJSON output (json only)                         |
| `-i, --info`          | Show additional system/environment information                          |
| `-h, --help`          | Display the help message                                                |
| `-V, --version`       | Display the version and exit                                            |
| `-t, --tags`          | filter the target to profile using tags (ie --tags=web) (optional)      |
| `--memory-limit`      | change the memory usage of the main process (optional)                  |

## Example

let's assume you have the following file located in `/path/profiler/test.php`.

```php
<?php

declare(strict_types=1);

namespace Foobar\Baz;

use Bakame\Stackwatch\Profile;
use function random_int;
use function usleep;

require 'vendor/autoload.php';

trait TimerTrait {
    #[Profile(type: Profile::SUMMARY, iterations: 10)]
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

#[Profile(type: Profile::DETAILED, iterations: 20, warmup: 2)]
function test() : int {
    usleep(100);

    return random_int(1, 100);
}
```
If you run the following command:

```bash
php vendor/bin/stackwatch --path=/path/profiler/test.php
```
It will output the following:

```bash
```bash
stackwatch v0.13.0 (Marrakesh) by Ignace Nyamagana Butera and contributors.

Runtime: PHP 8.3.24 OS: Linux Memory Limit: 64M

(Average) Target: Foobar\Baz\Foobar::test; Path: /path/to/profiling/code.php; Iterations: 3; Warmup: 0;

CPU Time ............................................................. 19.000 µs
Execution Time ...................................................... 144.611 µs
Memory Usage ............................................................ 1.0 KB
Real Memory Usage ........................................................ 0.0 B
Peak Memory Usage ........................................................ 0.0 B
Real Peak Memory Usage ................................................... 0.0 B

(Detailed) Target: Foobar\Baz\test; Path: /path/to/test.php; Iterations: 20; Warmup: 2:
+------------------------+---------------+------------+------------+--------------+------------+-----------+------------+------------+----------+-----------+
| Metric                 | Nb Iterations | Min Value  | Max Value  | Median Value | Sum        | Range     | Average    | Variance   | Std Dev  | Coef Var  |
+------------------------+---------------+------------+------------+--------------+------------+-----------+------------+------------+----------+-----------+
| CPU Time               | 20            | 9.000 µs   | 20.000 µs  | 12.000 µs    | 240.000 µs | 11.000 µs | 12.000 µs  | 6.300 μs²  | 2.510 µs | 20.9165 % |
| Execution Time         | 20            | 135.166 µs | 169.833 µs | 149.979 µs   | 2.980 ms   | 34.667 µs | 149.021 µs | 65.499 μs² | 8.093 µs | 5.4309 %  |
| Memory Usage           | 20            | 1.031 KB   | 1.031 KB   | 1.031 KB     | 20.625 KB  | 0.000 B   | 1.031 KB   | 0.000 B²   | 0.000 B  | 0.0000 %  |
| Peak Memory Usage      | 20            | 0.000 B    | 0.000 B    | 0.000 B      | 0.000 B    | 0.000 B   | 0.000 B    | 0.000 B²   | 0.000 B  | 0.0000 %  |
| Real Memory Usage      | 20            | 0.000 B    | 0.000 B    | 0.000 B      | 0.000 B    | 0.000 B   | 0.000 B    | 0.000 B²   | 0.000 B  | 0.0000 %  |
| Real Peak Memory Usage | 20            | 0.000 B    | 0.000 B    | 0.000 B      | 0.000 B    | 0.000 B   | 0.000 B    | 0.000 B²   | 0.000 B  | 0.0000 %  |
+------------------------+---------------+------------+------------+--------------+------------+-----------+------------+------------+----------+-----------+
```

- the leader list shows the average metrics for the `Foobar::test` method.
- the table shows the fully detailed report on the function `test`.

## The Profile attribute

The `#[Profile]` attribute marks a function, method, or class for performance profiling during execution.
When applied, the profiler will repeatedly execute the target code to collect detailed runtime metrics,
allowing developers to analyze and optimize code performance with statistically meaningful data.

The attribute can be applied to:

- **Standalone functions**
- **Class methods**, regardless of visibility (`public`, `protected`, or `private`)
- **Classes** — When applied at the class level, all methods of that class will be profiled using the class-level attribute configuration.

<div class="message-info">
If a method within a class is also marked with its own <code>#[Profile]</code> attribute, <strong>the method-level
attribute configuration overrides the class-level configuration</strong> for that specific method.
</div>

<div class="message-warning">
Functions or methods <strong>that declare one or more arguments will not be profiled</strong>. Only functions or methods without parameters can be profiled using this attribute.
</div>

## Attribute properties

- `type`: (`string`) Level of detail in the profiling output, (default to: `Profile::SUMMARY` ).
    - `Profile::SUMMARY`: Outputs core statistics such as average execution time.
    - `Profile::DETAILED`: Tags enable grouping and filtering in profiling reports, helping users focus on specific categories or subsets of profiled code.
- `iterations`: (`int`) Controls how many times the target will be executed during profiling to ensure statistical significance. Larger values provide more accurate metrics but increase profiling time. **Must be > 0** (default to `3`)
- `warmup`: Allows the profiler to run the target code several times before recording metrics, which helps mitigate effects like JIT compilation or caching impacting the results. **Must be >= 0** (default to `0`)
- `tags`: Tags enable grouping and filtering in profiling reports, helping users focus on specific categories or subsets of profiled code.  **Must be a list of non-emptu string** (default to an empty array)

## Attribute usage

**On a function**

```php
#[Profile(iterations: 500, type: Profile::SUMMARY)]
function calculateSomething(): void
{
    // ...
}
```

**On a method**

```php
class Example 
{
    #[Profile(iterations: 1000, warmup: 50, type: Profile::DETAILED, tags: ['api'])]
    protected function fetchData(): array
    {
        // ...
    }
}
```

**On a class**

```php
#[Profile(iterations: 100, type: Profile::SUMMARY)]
class MyService 
{
    public function methodOne() 
    { 
        /* profiled with class-level config */
    }

    #[Profile(iterations: 50, type: Profile::DETAILED)]
    private function methodTwo()
    { 
        /* profiled using the method-level attribute */
    }

    public function methodThree(string $arg1)
    {
        /* not profiled due to parameter */
    }
}
```

<div class="message-info">
Be mindful of the performance impact during profiling, especially with high iteration counts.
</div>

<div class="message-info">
All required dependencies should be loaded in the target file (use `require`, `include` or Composer autoload).
</div>

## Integration into CI

You can run the profiling command in your CI pipelines to detect regressions or performance anomalies.

```yaml
- name: Run Profiler
  run: php vendor/bin/stackwatch --path=/path/profiler/test.php --format=json
```

<div class="message-info">
The json output is a NDJSON each line representing the result of a successful file scan.
</div>

