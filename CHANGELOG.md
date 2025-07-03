# Changelog

All Notable changes to `bakame/aide-profiler` will be documented in this file.

## [Next](https://github.com/bakame-php/aide-profiler/compare/0.5.0...main) - TBD

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
