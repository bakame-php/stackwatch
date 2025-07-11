# Changelog

All Notable changes to `bakame/aide-profiler` will be documented in this file.

## [Next](https://github.com/bakame-php/aide-profiler/compare/0.9,0...main) - TBD

### Added

- `Label::random` accepts an optional `$length` parameter to adjust the label length.
- `Marker::complete` and `Marker::isComplete`
- `Profiler::hasSummaries`
- `Snapshot::fromArray`
- `Metrics::fromArray`
- `Summary::fromArray`

### Fixed

- **BC BREAK:** `ProfilingResult::result` is renamed `ProfilingResult::returnValue`
- **BC BREAK:** `ProfilingResult::ProfilingData` is renamed `ProfilingResult::summary`
- **BC BREAK:** `ProfilingResult` is renamed `ProfiledResult`
- **BC BREAK:** `ProfilingData` is renamed `Summary`
- **BC BREAK:** `Exporter::exportProfilingData` is renamed `Exporter::exportSummary`
- **BC BREAK:** `Snapshot::toArray` and `Snapshot::jsonSerialize` representation simplified
- **BC BREAK:** `Summary::toArray` and `Summary::jsonSerialize` representation simplified
- **BC BREAK:** `Summary::__construct`signature changed
- **BC BREAK:** `Profiler::toArray` and `Profiler::jsonSerialize` representation simplified
- **BC BREAK:** `Marker::toArray` and `Marker::jsonSerialize` representation simplified
- **BC BREAK:** `Marker::finish` throws an `UnableToProfile` exception previously was an `InvalidArgument` exception when it cannot complete its task.
- `Snapshot::cpu` keys presence is validated on instantiation

### Deprecated

- None

### Removed

- None

## [0.9.0 - Ibadan](https://github.com/bakame-php/aide-profiler/compare/0.8,0...0.9.0) - 2025-07-09

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

## [0.8.0 - Harare](https://github.com/bakame-php/aide-profiler/compare/0.7.1...0.8.0) - 2025-07-07

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

## [0.7.1 - Gaborone](https://github.com/bakame-php/aide-profiler/compare/0.7.0...0.7.1) - 2025-07-06

### Added

- None

### Fixed

- `Metrics::cpuTime` calculation

### Deprecated

- None

### Removed

- None

## [0.7.0 - Gaborone](https://github.com/bakame-php/aide-profiler/compare/0.6.0...0.7.0) - 2025-07-05

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

## [0.6.0 - Fezzan](https://github.com/bakame-php/aide-profiler/compare/0.5.0...0.6.0) - 2025-07-03

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

## [0.5.0 - Enugu](https://github.com/bakame-php/aide-profiler/compare/0.4.0...0.5.0) - 2025-07-01

### Added

- All `Profiler` static methods can have a logger attached to them
- `MemoryUnit` and `TimeUnit` Enum to ease metrics values conversion.

### Fixed

- None

### Deprecated

- None

### Removed

- **BC BREAK:** renamed `CliExporter` as `ConsoleTableExporter`

## [0.4.0 - Durban](https://github.com/bakame-php/aide-profiler/compare/0.3.0...0.4.0) - 2025-06-29

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

## [0.3.0 - Cairo](https://github.com/bakame-php/aide-profiler/compare/0.2.0...0.3.0) - 2025-06-29

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

## [0.2.0 - Bamako](https://github.com/bakame-php/aide-profiler/compare/0.1.0...0.2.0) - 2025-06-27

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

## [0.1.0 - Addis Ababa](https://github.com/bakame-php/aide-profiler/releases/tag/0.1.0) - 2025-06-26

**Initial release!**
