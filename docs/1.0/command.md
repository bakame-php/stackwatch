---
layout: default
title: Attribute-Driven CLI Profiling
---

# CLI command

Outside the `Profiler`and the `Timeline` you can use the package features through a CLI command.
A CLI Command is available to allow you to benchmark PHP **functions and methods** located in a
specific file or directory using the custom `#[Bakame\Stackwatch\Profile]` attribute. This is
especially useful for:

- Automating performance regressions in CI pipelines
- Profiling code outside the context of an application

## Example

First, let's assume you have the following file located in `/path/profiler/test.php`.

```php
<?php

declare(strict_types=1);

namespace Foobar\Baz;

use Bakame\Stackwatch\AggregationType;
use Bakame\Stackwatch\Profile;

use function random_int;
use function usleep;

require 'vendor/autoload.php';

trait TimerTrait {
    #[Profile(type: AggregationType::Average, iterations: 10)]
    private function test() : int {
        usleep(100);

        return random_int(1, 100);
    }
}

class Foobar
{
    use TimerTrait;
}

#[Profile(iterations: 20, warmup: 2)]
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
stackwatch v0.14.0 (Nouakchott) by Ignace Nyamagana Butera and contributors.

Runtime: PHP 8.3.7 OS: Linux Memory Limit: 64M

............................

Target: Foobar\Baz\Foobar::test; Path: /path/profiler/test.php; Iterations: 10; Warmup: 0; Type: Average;

CPU Time ............................................................. 20.100 µs
Execution Time ...................................................... 140.025 µs
Memory Usage ............................................................ 3.4 MB
Memory Usage Growth .................................................... 568.0 B
Real Memory Usage ....................................................... 4.0 MB
Real Memory Usage Growth ................................................. 0.0 B
Peak Memory Usage ....................................................... 3.5 MB
Peak Memory Usage Growth ................................................. 0.0 B
Real Peak Memory Usage .................................................. 4.0 MB
Real Peak Memory Usage Growth ............................................ 0.0 B

Target: Foobar\Baz\test; Path: /path/profiler/test.php; Iterations: 20; Warmup: 2; Type: Full;

+-------------------------------+------------+------------+------------+----------+------------+------------+--------------+-----------------+-----------+----------+
| Metrics                       | iterations | Min Value  | Max Value  | Range    | Sum        | Average    | Median Value | Variance        | Std Dev   | Coef Var |
+-------------------------------+------------+------------+------------+----------+------------+------------+--------------+-----------------+-----------+----------+
|CPU Time                       |          20|11.000 µs   |13.000 µs   |2.000 µs  |238.000 µs  |11.900 µs   |12.000 µs     |390,000.000 ns²  |624.500 n  |5.2479 %  |
|Execution Time                 |          20|136.625 µs  |139.792 µs  |3.167 µs  |2.751 ms    |137.525 µs  |137.334 µs    |695,881.528 ns²  |834.195 n  |0.6066 %  |
|Memory Usage                   |          20|3.392 MB    |3.397 MB    |4.836 KB  |67.889 MB   |3.394 MB    |3.394 MB      |2.008 KB²        |1.417 KB   |0.0408 %  |
|Memory Usage Growth            |          20|568.000 B   |568.000 B   |0.000 B   |11.094 KB   |568.000 B   |568.000 B     |0.000 B²         |0.000 B    |0.0000 %  |
|Real Memory Usage              |          20|4.000 MB    |4.000 MB    |0.000 B   |80.000 MB   |4.000 MB    |4.000 MB      |0.000 B²         |0.000 B    |0.0000 %  |
|Real Memory Usage Growth       |          20|0.000 B     |0.000 B     |0.000 B   |0.000 B     |0.000 B     |0.000 B       |0.000 B²         |0.000 B    |0.0000 %  |
|Peak Memory Usage              |          20|3.512 MB    |3.512 MB    |0.000 B   |70.242 MB   |3.512 MB    |3.512 MB      |0.000 B²         |0.000 B    |0.0000 %  |
|Peak Memory Usage Growth       |          20|0.000 B     |0.000 B     |0.000 B   |0.000 B     |0.000 B     |0.000 B       |0.000 B²         |0.000 B    |0.0000 %  |
|Real Peak Memory Usage         |          20|4.000 MB    |4.000 MB    |0.000 B   |80.000 MB   |4.000 MB    |4.000 MB      |0.000 B²         |0.000 B    |0.0000 %  |
|Real Peak Memory Usage Growth  |          20|4.000 MB    |4.000 MB    |0.000 B   |80.000 MB   |4.000 MB    |4.000 MB      |0.000 B²         |0.000 B    |0.0000 %  |
+-------------------------------+------------+------------+------------+----------+------------+------------+--------------+-----------------+-----------+----------+
```

- the leader list shows the average metrics for the `Foobar::test` method.
- the table shows the fully detailed report on the function `test`.

## The Profile attribute

To work, the command line relies on the presence of the `#[Bakame\Stackwatch\Profile]` attribute. The
attribute can mark a function, method, or class for performance profiling during execution.
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

The attribute accept the same arguments as `Profiler::metrics()`:

- the number of **iterations**,
- the number of **warm-up** runs to skip,
- and an optional aggregation type (`AggregationType`).

If no aggregation type is specified, the output will include the full profiling details, similar to what `Profiler::report()` returns.

It optionally also accepts an array of **tags** to enable grouping and filtering in profiling reports, helping users focus on specific categories or subsets of profiled code.  
 **Must be a list of non-emptu string** (default to an empty array)

## Attribute usage

**On a function**

```php
use Bakame\Stackwatch\AggregationType;
use Bakame\Stackwatch\Profile;

#[Profile(iterations: 500, type: AggregationType::Median)]
function calculateSomething(): void
{
    // ...
}
```

**On a method**

```php
use Bakame\Stackwatch\Profile;

class Example 
{
    #[Profile(iterations: 1000, warmup: 50, tags: ['api'])]
    protected function fetchData(): array
    {
        // ...
    }
}
```

**On a class**

```php
use Bakame\Stackwatch\AggregationType;
use Bakame\Stackwatch\Profile;

#[Profile(iterations: 100, type: AggregationType::Median)]
class MyService 
{
    public function methodOne() 
    { 
        /* profiled with class-level config */
    }

    #[Profile(iterations: 50)]
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

## Command Line Usage

The `Stackwatch` command-line profile runner can be invoked through the `stackwatch` command.
The following code shows the generic way to run profiling with the `stackwatch` command-line:

```bash
php vendor/bin/stackwatch --path=PATH [options]
```
Apart from the `path` argument, all the other command line options **are optional**.

### Command Line Options

#### Configuration

**`-p, --path=PATH`**  
Path to scan for PHP files to profile. **Required.** The path can be a file or a directory.
If it is a directory, it will be recursively scan.

**`-i, --info`**  
Show additional system and environment information.

**`-h, --help`**  
Display the help message.

**`-V, --version`**  
Display the version and exit.

#### Output

**`-f, --format=FORMAT`**  
Output format. Can be either `'text'` or `'json'`. Default is `'text'`. Use `'text'` for human-readable
output and `'json'` for newline-delimited JSON (NDJSON).

**`-o, --output=OUTPUT`**  
Path to store the profiling output.

**`--log=FILE`**  
Writes log information to the specified file. By default the log are written to the **`STDERR`** stream.

**`-P, --pretty`**  
Pretty-print the JSON/NDJSON output. **JSON only.**

**`--no-progress`**  
Hides the progress bar

#### Selection

**`-d, --depth=DEPTH`**  
Recursion depth. `0` means scan only the current directory. By default, recursion is unlimited.

**`-n, --no-recursion`**  
Disable directory recursion an alias to `--depth=0`

**`-t, --tags=TAGS`**  
Only run the profiles for the listed tag(s). The tags are separated by a `,` and refers
to the tags defined on the `#[Profile]` attribute.

**`--file-suffix=SUFFIX`**  
Only search for test in files with specified suffix(es). Default: `.php` The suffixes are separated by a `,`

**`--method-visibility=VISIBILITY`**
Only run the profiles for methods with the listed visibilities. The visibility is separated by a `,`
And can only take `private`, `protected` or `public` values. By default, all methods are run
regardless of their visibility status.

#### Execution

**`-x, --isolation`**  
Profile each file in isolation.

**`--dry-run`**  
List profiling targets without actually performing the profiling.

**`--memory-limit=MEMORY-LIMIT`**  
Memory limit to use for the analysis.

## Integration into CI

You can run the profiling command in your CI pipelines to detect regressions or performance anomalies.

```yaml
- name: Run Profiler
  run: php vendor/bin/stackwatch --path=/path/profiler/test.php --format=json
```

<div class="message-info">
The json output is a NDJSON each line representing the result of a successful file scan.
</div>

