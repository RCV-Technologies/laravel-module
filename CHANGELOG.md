# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),  
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-11-27

### Security
- **Module routes now use the `web` / `api` middleware groups**: The generated module service provider (`provider.stub`) loaded `Routes/web.php` and `Routes/api.php` without any middleware group, so module web routes had no session, CSRF protection or cookie encryption, and API routes had no API middleware such as rate limiting. Routes are now registered inside `Route::middleware('web')` and `Route::middleware('api')`. **Modules generated with 2.0.0 or earlier must update `registerRoutes()` in their own service provider.**
- **`module:migrate-fresh` requires confirmation in production**: The command drops every table in the default connection. It now uses Laravel's `ConfirmableTrait` (cancelled in production unless `--force` is given), warns which connection and database will be wiped, and uses `Schema::dropAllTables()` instead of MySQL-only raw SQL. It no longer calls `env('DB_DATABASE')`, which returned `null` under `config:cache` and crashed after foreign-key checks had been disabled.
- **Backup paths are restricted to the backup directory**: `ModuleBackupManager::restoreBackup()` and `deleteBackup()` (and `module:backup restore|delete --backup=`) now reject any path outside `marketplace.modules.backup.path`, so they can no longer recursively delete or copy arbitrary directories.
- **Generated form requests deny by default**: `module-request.stub` now returns `false` from `authorize()` (matching Laravel's default) instead of allowing every user.
- **Package config no longer ships a machine-specific path**: `src/Config/config.php` contained a hard-coded absolute path and module list from the build machine. It now uses `base_path('Modules')` and no module list.

### Fixed
- **Stale module status in `module:analyze`**: `module:enable` and `module:disable` now clear the marketplace module list (`modules.list`) and per-module details cache. Previously the `ClearCacheOnModuleEnable` / `ClearCacheOnModuleDisable` listeners only forgot the prefixed `rcv_core:module_1.0.0:*` keys, so `MarketplaceService::list()` kept serving the old status for up to the cache TTL.
- **Dependency graph cache invalidation**: `modules.dependency_graph.v1` is now cleared whenever a module is enabled, disabled, installed, updated or removed, so a cached graph (`modules.dependency_graph.cache_enabled = true`) no longer reports outdated module status.
- **Dependency graph enabled status**: `ModuleDependencyGraph` now reads enabled status from `ModuleManager::isModuleEnabled()`, the same source used by autoloading and middleware, instead of the marketplace cache.
- **Enabled-module cache was never used**: `ModuleManager` is resolved while providers register, before Laravel's deferred cache provider is available, so it queried `module_states` on every request and command. `CoreServiceProvider` now registers the cache provider (and the Redis provider when the default store uses Redis) early, and `ModuleManager` remembers the enabled list for the lifetime of the instance instead of hitting the cache store on every `isModuleEnabled()` call.
- **`module:make` no longer writes into `vendor/`**: `module:make` and `module:make-multiple` rewrote `vendor/rcv/core/src/Config/config.php` to track modules. That change was lost on every `composer install`, required a writable `vendor/` and baked in a server path. Existing modules are now checked against the `module_states` table.
- **`module:migrate-fresh` missed package migrations**: It ran `migrate --path=database/migrations`, which skipped the package's own `module_states` migration and then failed when refreshing the module cache. It now runs the full `migrate` first and module migrations second, so modules can rely on core tables.
- **`module` middleware alias pointed to an abstract class**: The base `ModuleServiceProvider` aliased `module` to the abstract `ModuleMiddleware`, so any route using it failed with a 500. The alias was removed; modules should alias their own concrete subclass.
- **Route caching in the base `ModuleServiceProvider`**: Module routes are no longer re-registered on every request when routes are cached (`route:cache`).
- **`ModuleBackupManager` error handling**: The `Log` facade was not imported, so every failure path threw "Class not found" and hid the real error.
- **Duplicate query and noisy logs when `module_states` is missing**: On a fresh install (before `migrate`) `ModuleManager` ran the failing `module_states` query twice and logged two DEBUG entries with full stack traces on every request and artisan command. It now queries once and logs one short line. A failing cache store still falls back to the database.
- **`Module` facade alias was broken**: `composer.json` registered a `Module` alias for `RCV\Core\Facades\Module`, but that class did not exist, so any `Module::` call crashed. The facade now exists and resolves to `ModuleManager`.
- **Branch alias**: `dev-main` is now aliased to `2.x-dev` (was `1.x-dev`).
- **Cache warm-up crashed without `module_states`**: `CacheManager::warmUp()` (called by `invalidateAll()`) threw when the table did not exist yet; it now skips the warm-up.

### Changed
- **README rewritten**: Accurate requirements, installation, command reference (matching the real command signatures), configuration and an upgrade guide for 2.0.1. Removed documentation for commands, config keys and features that do not exist, and the leftover GitLab template.
- **Removed info-level logging**: The enable/disable cache listeners and `ComposerDependencyManager` no longer write info-level log entries. Warnings and errors are still logged.
- **Shorter log entries**: `ModuleManager` and `CacheManager` log the exception class and message instead of full stack traces for expected conditions. Rejected backup paths in `ModuleBackupManager` are thrown to the caller without also being logged as errors.
- **Less work on web requests**: `CoreServiceProvider` reads its command list from `config.php` only in the console, and `ModuleManager` creates the `Modules/` directory only in the console.
- Removed duplicate route-path lines and unreachable `return` statements in the base `ModuleServiceProvider`.

### Added
- `RCV\Core\Facades\Module` facade (alias `Module`) for `ModuleManager`.
- `MarketplaceService::clearCachedState()` to clear a module's marketplace cache entries after a state change made outside the service.
- `MarketplaceService::LIST_CACHE_KEY` and `ModuleDependencyGraph::CACHE_KEY` constants for the cache keys.

## [2.0.0] - 2026-11-04

### Fixed
- **Safe module registration**: Removed the `config/app.php` rewrite so evaluated application configuration is never serialized back to disk.
- **Safer middleware logging**: Disabled per-request logging and limited error logs to exception class and module context, avoiding query-string and stack-trace disclosure.
- **Targeted module cache invalidation**: Module enable/disable events now invalidate package-owned entries instead of flushing the application's entire cache store.
- **Controlled Composer dependencies**: Disabled automatic Composer dependency installation and removal from module manifests.
- **Validated module operations**: Validate module names before filesystem operations and store the active CLI module in runtime config instead of editing `.env`.
- **Stable module cache key**: Derive the enabled-module cache key from a fixed package key using SHA-256, without using `app.key`.
- **Module seeder discovery**: Corrected seeder class resolution and limit the opt-in seeder hook to enabled modules; the hook remains disabled by default.
- **Single provider boot**: Removed the second module-provider boot pass and made generated event providers skip duplicate email-verification listener registration.
- **Cache store compatibility**: Added indexed-key invalidation for cache stores that do not support cache tags.
- **Module state persistence**: Persist lifecycle state in `module_states` without replacing module manifest data.
- **Single command registration**: Register package console commands once.
- **Cached enabled modules**: Cache the enabled-module list indefinitely and refresh it after state changes. Provider boot no longer queries module state or checks/creates Laravel's migrations table, and cache access is skipped before Laravel binds the cache service.
- **Configurable message dispatch**: Support configurable queues, after-commit dispatch, and backoff.
- **Middleware exception handling**: Log safe context and rethrow exceptions so Laravel preserves validation, authorization, and other response behavior without exposing exception text.
- **Module error layout**: Add the shared `core::layouts.error` view used by the package's disabled-module and module-error pages.
- **Framework test matrix**: Add CI coverage for Laravel 10–13 and PHP 8.2–8.4, with tests for middleware exception propagation and package error-view rendering.
- **Migration boot performance**: Move migration-table setup and metadata updates out of provider boot; providers now only register migration paths.

## [1.2.0] - 2026-02-10

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

## [1.0.2] - 2025-09-23

### Fixed
- MakeModuleCommand for taking Module Name at last as signature and registering component appropriately in the service provider

## [1.0.1] - 2025-09-23

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
## [1.0.0] - 2025-09-22

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

## [1.0.0-beta.1] - 2025-08-22

### Fixed
- Update README logo and clean up commented/unnecessary code

## [1.0.0-alpha] - 2025-07-31

### Added
- Initial Commit: Modular Package System by [@Vishal-kumar007](https://github.com/Vishal-kumar007) in [#1](https://github.com/RCV-Technologies/laravel-module/pull/1)
- Updated README logo and removed commented/unnecessary code by [@vatsrajatkjha](https://github.com/vatsrajatkjha) in [#2](https://github.com/RCV-Technologies/laravel-module/pull/2)

### Contributors
- [@Vishal-kumar007](https://github.com/Vishal-kumar007) – First contribution
- [@vatsrajatkjha](https://github.com/vatsrajatkjha) – First contribution

**Full Changelog:** [v1.0.0-alpha commits »](https://github.com/RCV-Technologies/laravel-module/commits/v1.0.0-alpha)
