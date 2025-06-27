# Changelog

All Notable changes to `bakame/aide-profiler` will be documented in this file.

## [Next](https://github.com/bakame-php/aide-profiler/compare/0.2.0...main) - TBD

### Added

- `Profiler::first`, `Profiler::last`, `Profiler::nth`
- `Metrics`
- `Profiler` metrics related methods.

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
- **BC BREAK:** The `Metrics::executionTime` is now calculated using `hrtime` instead of `microtime`

## [0.2.0](https://github.com/bakame-php/aide-profiler/compare/0.1.0...0.2.0) - 2025-06-27

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

## [0.1.0](https://github.com/bakame-php/aide-profiler/releases/tag/0.1.0) - 2025-06-26

**Initial release!**
