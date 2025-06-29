# Changelog

All Notable changes to `bakame/aide-profiler` will be documented in this file.

## [Next](https://github.com/bakame-php/aide-profiler/compare/0.3.0...main) - TBD

### Added

- `ProfilingResult` implements the `JsonSeriazilable` interface and returns the same JSON as `ProfilingData`.
- `Metrics::avg` supports `ProfilingData` instances.

### Fixed

- library packaging by removing development related files from downloads.

### Deprecated

- None

### Removed

- None

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
