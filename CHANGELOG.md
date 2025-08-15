# Changelog

All Notable changes to `bakame/stackwatch` will be documented in this file.

## [Next - African City](https://github.com/bakame-php/stackwatch/compare/0.13.0...main) - TBD

### Added

- `CallLocation` class to allow recording where the package `Snapshot` was called from [#8](https://github.com/bakame-php/stackwatch/pull/8)

### Fixed

-  `UnitOfWork` internal code improvement.

### Deprecated

- None

### Removed

- None

## [0.13.0 - Marrakesh](https://github.com/bakame-php/stackwatch/compare/0.12.0...0.13.0) - 2025-08-14

### Added

- `--tags` option to the CLI command to filter profile that needs to be run based on the `tags` property of the #[Profile] attribute.
- `--memory-limit` option to the CLI command to control the memroy limit of the main process.
- `UnitSpacing` Enum to control the space between value and unit when using the format related methods of `MemoryUnit` and `DurationUnit`.
- `Visibility` and `State` Enums to improve `Input` option flags properties.

### Fixed

- **BC BREAK:** `Timeline::delta` default to creating the delta between the `from` and the next snapshot before it was with the last snapshot if no `to` label was given.
- **BC BREAK:** `Input` boolean properties are replaced by discret `Enum`

### Deprecated

- None

### Removed

- **BC BREAK:** `Marker` class renamed `Timeline`

## [0.12.0 - Luanda](https://github.com/bakame-php/stackwatch/compare/0.11.0...0.12.0) - 2025-08-11

### Added

- `--depth` argument and `--no-recursion` flag to control recursion on directories.
- `--isolation` flag to handle in isolation each file.

### Fixed

- **BC BREAK:** command line format options `table` replace the default option `cli`
- **BC BREAK:** Changed `Bakama\Stachwatcher\Marker::summary` method name to `Bakama\Stachwatcher\Marker::summarize`
- Internal improvement, Lazy evaluation is now done in the `UnitOfWork`

### Deprecated

- None

### Removed

- None

## [0.11.0 - Kampala](https://github.com/bakame-php/stackwatch/compare/0.10.0...0.11.0) - 2025-08-03

### Added

- **BC BREAK:** Changed namespace from `Bakame\Aide\Profiler` to `Bakama\Stachwatcher`
- `Statistics` class
- `Report` class and the `Profiler::report` method.
- `ConsoleExporter::exportMetrics`
- `ConsoleExporter::exportStatistics`
- `ConsoleExporter::exportReport`
- `JsonExporter`
- The `Stackwatcher` command to ease profiling using command line

### Fixed

- None

### Deprecated 

- None

### Removed

- **BC BREAK:** `ConsoleTableExporter` renamed `ConsoleExporter` and moved under the `Exporter` namespace
- **BC BREAK:** `Profiler::executionTime` removed use `Profiler::metrics` instead
- **BC BREAK:** `Profiler::cpuTime` removed use `Profiler::metrics` instead
- **BC BREAK:** `Profiler::memoryUsage` removed use `Profiler::metrics` instead
- **BC BREAK:** `Profiler::peakMemoryUsage` removed use `Profiler::metrics` instead
- **BC BREAK:** `Profiler::realMemoryUsage` removed use `Profiler::metrics` instead
- **BC BREAK:** `Profiler::realPeakMemoryUsage` removed use `Profiler::metrics` instead
- **BC BREAK:** `Marker::executionTime` removed use `Marker::metrics` instead
- **BC BREAK:** `Marker::cpuTime` removed use `Marker::metrics` instead
- **BC BREAK:** `Marker::memoryUsage` removed use `Marker::metrics` instead
- **BC BREAK:** `Marker::peakMemoryUsage` removed use `Marker::metrics` instead
- **BC BREAK:** `Marker::realMemoryUsage` removed use `Marker::metrics` instead
- **BC BREAK:** `Marker::realPeakMemoryUsage` removed use `Marker::metrics` instead
- **BC BREAK:** `ProfiledResult` is renamed `Result`

## [0.10.0 - Johannesburg](https://github.com/bakame-php/stackwatch/compare/0.9,0...0.10.0) - 2025-07-17

### Added

- `Marker::complete` and `Marker::isComplete`
- `Profiler::hasSummaries`
- `Profiler::filter`
- `Snapshot::fromArray`
- `Metrics::fromArray`
- `Summary::fromArray`
- `LabelGenerator::withLength` label length can be configured
- `MemoryUnit::convertFrom` and `MemoryUnit::convertTo`
- `DurationUnit::convertFrom` and `DurationUnit::convertTo`
- The static methods from the `Profiler` now can warm up before recording the metrics.

### Fixed

- **BC BREAK:** `ProfilingResult::result` is renamed `ProfilingResult::returnValue`
- **BC BREAK:** `ProfilingResult::ProfilingData` is renamed `ProfilingResult::summary`
- **BC BREAK:** `ProfilingResult` is renamed `ProfiledResult`
- **BC BREAK:** `ProfilingData` is renamed `Summary`
- **BC BREAK:** `Label` is renamed `LabelGenerator`
- **BC BREAK:** `Exporter::exportProfilingData` is renamed `Exporter::exportSummary`
- **BC BREAK:** `Snapshot::toArray` and `Snapshot::jsonSerialize` representation simplified
- **BC BREAK:** `Summary::toArray` and `Summary::jsonSerialize` representation simplified
- **BC BREAK:** `Summary::__construct`signature changed
- **BC BREAK:** `Profiler::toArray` and `Profiler::jsonSerialize` representation simplified
- **BC BREAK:** `Marker::toArray` and `Marker::jsonSerialize` representation simplified
- **BC BREAK:** `Marker::delta` removed the 3rd argument
- **BC BREAK:** `Marker::summary` throws when no summary can be generated
- **BC BREAK:** `Marker::finish` is renamed `Marker::take`
- `Snapshot::cpu` keys presence is validated on instantiation

### Deprecated

- None

### Removed

- None

## [0.9.0 - Ibadan](https://github.com/bakame-php/stackwatch/compare/0.8,0...0.9.0) - 2025-07-09

### Added

- Added `Metrics::forHuman` to ease getting human-readable metrics representations.
- Added `Snapshot::forHuman` to ease getting human-readable metrics representations.
- Added `Environment` OS Platform related methods.
- Added `Marker` to provide an alternative way to profile your code.
- Added `Label` to decouple label generation from both `Marker` and `Profiler`.
- Added `Exporter::exportMarker` method to the interface.
- Added `Profiler::run` with `Profiler::__invoke` becoming its alias
- Added `Marker::identifier` and `Profiler::identifier`to ease identify each instance uniquely

### Fixed

- **BC BREAK:** `Profiler::last` is renamed `Profiler::latest` to be consistent with `Marker::latest`
- **BC BREAK:** `Profiler::runWithLabel` is renamed `Profiler::profile`

### Deprecated

- None

### Removed

- None

## [0.8.0 - Harare](https://github.com/bakame-php/stackwatch/compare/0.7.1...0.8.0) - 2025-07-07

### Added

- Added `Environment::rawMemoryLimit` to keep the original value if it cannot be properly parsed.

### Fixed

- **BC BREAK:** `Environment::memoryLimit` is a nullable int  
- **BC BREAK:**  Renamed `Metrics::stats` to `Metrics::toArray`
- **BC BREAK:**  Renamed `Snapshot::stats` to `Snapshot::toArray`
- **BC BREAK:**  Renamed `ProfilingData::stats`  to `ProfilingData::toArray`
- **BC BREAK:**  Renamed `Environment::stats`  to `Environment::toArray`

### Deprecated

- None

### Removed

- None

## [0.7.1 - Gaborone](https://github.com/bakame-php/stackwatch/compare/0.7.0...0.7.1) - 2025-07-06

### Added

- None

### Fixed

- `Metrics::cpuTime` calculation

### Deprecated

- None

### Removed

- None

## [0.7.0 - Gaborone](https://github.com/bakame-php/stackwatch/compare/0.6.0...0.7.0) - 2025-07-05

### Added

- `Profiler::average`
- `Environment` class
- `ConsoleTableExporter::exportEnvironment` to visually show the environment settings
- `ConsoleTableExporter::exportProfiler` also provide the average as a summary

### Fixed

- `Exporter::exportProfiler` now takes a second parameter to filter using the label.
- `Metrics::average`, fix bug in the calculation.

### Deprecated

- None

### Removed

- None

## [0.6.0 - Fezzan](https://github.com/bakame-php/stackwatch/compare/0.5.0...0.6.0) - 2025-07-03

### Added

- `DurationUnit` replaces `TimeUnit` Enum.
- Adding `Exporter::exportSnapshot` method.

### Fixed

- **BC BREAK:** simplify `ConsoleTableExporter` memory and time unit display
- **BC BREAK:** rewrite and simplify `MemoryUnit` and `DurationUnit` Enum
- **BC BREAK:** `Snapshot::executionTime` is renamed `Snapshot::hrtime`.
- **BC BREAK:** `Metrics::avg` is renamed `Metrics::average`.

### Deprecated

- None

### Removed

- **BC BREAK:**  `TimeUnit` is removed in favor of `DurationUnit` Enum

## [0.5.0 - Enugu](https://github.com/bakame-php/stackwatch/compare/0.4.0...0.5.0) - 2025-07-01

### Added

- All `Profiler` static methods can have a logger attached to them
- `MemoryUnit` and `TimeUnit` Enum to ease metrics values conversion.

### Fixed

- None

### Deprecated

- None

### Removed

- **BC BREAK:** renamed `CliExporter` as `ConsoleTableExporter`

## [0.4.0 - Durban](https://github.com/bakame-php/stackwatch/compare/0.3.0...0.4.0) - 2025-06-29

### Added

- `Metrics::avg` supports `ProfilingData` instances.
- `Metrics::add` public method.

### Fixed

- library packaging by removing development related files from downloads.
- **BC BREAK:** normalize duration related metrics to be expressed in nanoseconds.
- **BC BREAK:** normalize memory related metrics to be expressed in bytes.
- **BC BREAK:** Adding missing field in `Metrics::stats` returned array.

### Deprecated

- None

### Removed

- **BC BREAK:** `ProfilingResult::profile` static method removed
- **BC BREAK:** `ProfilingData::randomLabel` static method removed

## [0.3.0 - Cairo](https://github.com/bakame-php/stackwatch/compare/0.2.0...0.3.0) - 2025-06-29

### Added

- `Profiler::first`, `Profiler::last`, `Profiler::nth`
- `Metrics`
- `Profiler` metrics related static methods.
- `Profiler::execute` static method.

### Fixed

- None

### Deprecated

- None

### Removed

- **BC BREAK:**  The `Renderer` interface is removed
- **BC BREAK:** `CliTableRenderer` renamed `CliExporter`
- **BC BREAK:** `CliTableRenderer` implements the `Exporter` interface
- **BC BREAK:** `Profiler::lastProfile` replaced by `Profiler::last`
- **BC BREAK:** `Profiler::get` returns the last `Profile` for a specific label before it was returning the first one.
- **BC BREAK:** `Profile::metrics` returns a `Metrics` object before the metrics where attached directly to the profile.
- **BC BREAK:** The `Metrics::executionTime` is now calculated using `hrtime` instead of `microtime` and returns the value in nanoseconds
- **BC BREAK:** The `Profile` class is now a readonly value object, the methods showing the progress of the profile generation are removed.
- **BC BREAK:** `Profile` renamed `ProfilingData`
- **BC BREAK:** `ProfileResult::value` renamed `ProfileResult::result`
- **BC BREAK:** `ProfileResult::profile` renamed `ProfileResult::profilingData`

## [0.2.0 - Bamako](https://github.com/bakame-php/stackwatch/compare/0.1.0...0.2.0) - 2025-06-27

### Added

- `ProfilingException` as the exception marker
- `UnableToProfile` and `InvalidProfileState` exceptions
- `OpenTelemetryExporter` to enable exporting the `Profiler`

### Fixed

- None

### Deprecated

- None

### Removed

- None

## [0.1.0 - Addis Ababa](https://github.com/bakame-php/stackwatch/releases/tag/0.1.0) - 2025-06-26

**Initial release!**
