# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),  
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_(nothing yet)_

## [1.4.0] - 2026-02-10

### Added
- **Composer Dependency Management**: Automatic installation and removal of third-party packages defined in module's `dependencies` array.
- **Module Dependents Feature**: Support for internal module-to-module relationships via `dependents` array in `module.json`.
- **Smart Dependency Removal**: Only removes packages when no other enabled modules require them.
- **Enhanced module:enable**: Automatically enables required modules listed in `dependents` array with user confirmation.
- **Enhanced module:disable**: Prevents disabling modules that other modules depend on (requires `--force` to override).
- **Enhanced module:sync**: Handles both Composer dependencies and module dependents during synchronization.
- **ComposerDependencyManager Service**: New service class for managing Composer package operations.
- Automatic configuration loading support for module config files.
- Middleware registration support within the Module Service Provider.


### Changed
- **module:enable** now checks for required modules and prompts to enable them automatically.
- **module:disable** now validates if other modules depend on the target module before disabling.
- **module:sync** now ensures dependencies are installed/removed even for already-synced modules.
- Updated `module.json` schema to support both `dependencies` (Composer packages) and `dependents` (internal modules).

### Fixed
- Dependency installation now properly handles version constraints in format `package:version`.
- Shared dependencies are no longer removed when one module is disabled if other modules still use them.
- Prevented removal of shared Composer dependencies when still used by other modules.
- Resolved nested controller file generation issues.
- Fixed namespace resolution issues for module models.
- Corrected repository class name generation during scaffolding.

## [1.3.0] - 2025-09-17

### Added
- Introduced `src/Listeners/` namespace for future module lifecycle event handling.

### Changed
- **ModuleCheckLangCommand**:
  - More robust validation for missing files, invalid arrays, and empty directories.
  - Added fallback + summary reporting for clean states.
- **ModuleDisableCommand**:
  - Default behavior is non-destructive (no rollback/data loss).
  - Added safer handling with `--force`, `--remove`, and optional rollback logic.
- **ModuleEnableCommand** improved for consistent lifecycle management.
- Enhanced **ModuleDisabled** and **ModuleEnabled** events to better track module state changes.
- Updated **CoreServiceProvider** and **ModuleServiceProvider** for event + command registration.
- Refined `config.php` and `core.php` to align with new lifecycle features.
- Expanded README with detailed usage for `module:disable`, `module:commands`, and safety notes.

### Fixed
- Prevented runtime errors in language check when translations return non-array values or directories are missing.

---

## [1.2.0] - 2025-09-16

### Added
- New **DevOps stubs**: `DOCKER_SETUP.md`, `Dockerfile`, `dockerignore`, and configs under `/docker` (`php.ini`, `nginx.conf`, `supervisord.conf`).
- `ModuleUninstalled` event added for lifecycle tracking.
- Extended `PublishDevopsAssets` command to generate structured files for CI/CD, Docker, and K8s setups.

### Changed
- Updated **README.md** with DevOps publish command usage and documentation.
- Improved migration stubs:
  - Fixed `delete.stub` to correctly generate column drop migrations.
  - Fixed `drop.stub` to properly handle table name resolution.
- Enhanced **controller stubs** to resolve namespaces in subdirectories.
- Refined `ModuleMarketplaceCommand` with better details, updates, and error handling.
- Refactored `ModuleAnalyzeCommand` to integrate with `ModuleDependencyGraph` service.
- Improved `MarketplaceService`, `ModuleDependencyGraph`, and `ModuleHealthCheck` for reliability and production readiness.

### Fixed
- Resolved missing stub errors for DevOps commands.
- Fixed incorrect table name handling in **delete** and **drop** migration generators.
- Ensured generated controller namespaces match actual folder structure.

---

## [1.1.0] - 2025-09-15

### Added
- Introduced enterprise modular support with new services: **Communication**, **Config**, **Messaging**, **ModuleMetrics**, **Security**.
- Added **Facades** for new services.
- Bootstrapped `tests/` directory for unit & integration coverage.
- Added DevOps, Analyze, DevTools, Docs, and Upgrade **command stubs**.
- Added new configuration files for **communication**, **metrics**, and **security**.

### Changed
- Refactored module generator commands for consistent namespace + filesystem handling.
- Updated `MakeAction`, `MakeCastCommand`, `MakeChannel`, `MakeInterfaceCommand` to display **final namespace + path** after generation.
- Reordered generator command signatures to follow `{name} {module}` convention.
- Repository **interfaces** are now generated under `Modules/<Module>/src/Repositories/Interfaces` (instead of `Contracts`).
- Standardized stub placeholders across all stubs (`action.stub`, `cast.stub`, `channel.stub`, `interface.stub`, etc.).
- Refactored commands under **Actions**, **Database**, **Publish**, and **Make** to follow consistent conventions.
- Cleaned up `config.php`, `core.php`, and `marketplace.php` for naming alignment.
- Updated `composer.json` and `README.md` to reflect the new structure.

### Fixed
- Corrected directory creation issues where files were being nested under unintended paths (e.g., `Modules/UserManagement/Email/src/*`).
- Improved error handling for **missing stubs**, **duplicate class detection**, and **directory creation**.

### Removed
- Deleted redundant/legacy generator commands:
  - `ComponentView`
  - `Model`
  - `Repository`
  - `Resource`
  - `Service`

---

## [1.0.1] - 2025-07-31

### Fixed
- Fixed case sensitivity issues in file paths and namespace resolution to ensure cross-platform compatibility on Linux and Windows.
  - Replaced manual `mkdir()` with `File::ensureDirectoryExists()` to handle OS differences.
  - Ensured consistent class name formatting using `Str::studly()`.
  - Normalized paths for autoloading accuracy.

## [1.0.0-alpha] - 2025-07-31

### Added
- Initial Commit: Modular Package System by [@Vishal-kumar007](https://github.com/Vishal-kumar007) in [#1](https://github.com/RCV-Technologies/laravel-module/pull/1)
- Updated README logo and removed commented/unnecessary code by [@vatsrajatkjha](https://github.com/vatsrajatkjha) in [#2](https://github.com/RCV-Technologies/laravel-module/pull/2)

### Contributors
- [@Vishal-kumar007](https://github.com/Vishal-kumar007) – First contribution
- [@vatsrajatkjha](https://github.com/vatsrajatkjha) – First contribution

**Full Changelog:** [v1.0.0-alpha commits »](https://github.com/RCV-Technologies/laravel-module/commits/v1.0.0-alpha)
