# RCV Core - Composer Dependency Management

## Overview

The RCV Core module system now supports automatic Composer dependency management. When modules are enabled, disabled, or synced, the system automatically handles third-party package dependencies specified in each module's `module.json` file.

## Features

- **Automatic Installation**: Dependencies are installed when modules are enabled
- **Smart Removal**: Dependencies are only removed when no other enabled modules need them
- **Sync Integration**: Works with `module:sync` command for state synchronization
- **Safe Operations**: Prevents accidental removal of shared dependencies

## Configuration

### Module Dependencies

Add dependencies to your module's `module.json` file:

```json
{
    "name": "YourModule",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": [
        "guzzlehttp/guzzle:^7.0",
        "league/fractal:^0.20",
        "spatie/laravel-permission:^5.0",
        "monolog/monolog"
    ],
    "dependents": [],
    "config": []
}
```

### Dependency Formats

1. **With Version Constraint**: `"package-name:version"`
   - Example: `"guzzlehttp/guzzle:^7.0"`
   - Uses the specified version constraint

2. **Without Version**: `"package-name"`
   - Example: `"monolog/monolog"`
   - Defaults to `*` (latest version)

## Commands

### Enable Module with Dependencies

```bash
php artisan module:enable YourModule
```

Output:
```
Enabling module [YourModule]...
Installing dependencies for module [YourModule]: guzzlehttp/guzzle, league/fractal, spatie/laravel-permission
Running composer install...
Composer install completed successfully
Module [YourModule] enabled.
```

### Disable Module with Dependency Cleanup

```bash
php artisan module:disable YourModule
```

Output:
```
Processing module: YourModule
Removing dependencies for module [YourModule]: guzzlehttp/guzzle, league/fractal, spatie/laravel-permission
All dependencies are still used by other modules, skipping removal
Module [YourModule] marked disabled.
```

### Sync Modules with Dependencies

```bash
php artisan module:sync --json-priority
```

The sync command will automatically handle dependencies when enabling or disabling modules during synchronization.

**Important**: The sync command now ensures dependencies are properly managed in all scenarios:

1. **New Enabled Modules**: Installs dependencies when creating new enabled database entries
2. **New Disabled Modules**: Removes unused dependencies when creating new disabled database entries  
3. **State Changes**: Handles dependencies when resolving conflicts between JSON and database
4. **Already Synced Modules**: Ensures dependencies are installed/removed even for modules that are already in sync

This means running `module:sync` will ensure all enabled modules have their dependencies installed, even if they were previously enabled but dependencies weren't installed.

## How It Works

### Installation Process

1. **Read Dependencies**: System reads the `dependencies` array from `module.json`
2. **Parse Format**: Handles both `package:version` and `package` formats
3. **Run Composer Require**: Uses `composer require package:version` to add and install packages
4. **Continue Enable**: Proceeds with normal module enable process

### Removal Process

1. **Read Dependencies**: Gets dependencies from the module being disabled
2. **Check Usage**: Scans all other enabled modules for the same dependencies
3. **Filter Unused**: Only marks dependencies for removal if no other modules use them
4. **Run Composer Remove**: Uses `composer remove package` for unused packages only
5. **Continue Disable**: Proceeds with normal module disable process

## Examples

### Example 1: Blog Module with Content Management Dependencies

```json
{
    "name": "Blog",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": [
        "doctrine/dbal:*",
        "symfony/finder:*",
        "psr/log:*"
    ]
}
```

### Example 2: API Module with HTTP Client Dependencies

```json
{
    "name": "API",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": [
        "guzzlehttp/guzzle:*",
        "psr/http-message:*",
        "symfony/http-foundation:*"
    ]
}
```

### Example 3: User Module with Authentication Dependencies

```json
{
    "name": "User",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": [
        "illuminate/support:*",
        "symfony/process:*",
        "psr/container:*"
    ]
}
```

## Best Practices

### 1. Version Constraints

Always specify version constraints to ensure compatibility:

```json
"dependencies": [
    "guzzlehttp/guzzle:^7.0",  // Good: specific constraint
    "monolog/monolog"          // Acceptable: latest version
]
```

### 2. Shared Dependencies

When multiple modules use the same package, specify the same version constraint:

```json
// Module A
"dependencies": ["guzzlehttp/guzzle:^7.0"]

// Module B  
"dependencies": ["guzzlehttp/guzzle:^7.0"]
```

### 3. Development vs Production

The system uses `composer install --no-dev` to avoid installing development dependencies in production.

### 4. Testing Dependencies

For testing-specific packages, consider using a separate mechanism or conditional loading.

## Troubleshooting

### Common Issues

1. **Composer Install Fails**
   - Check that all specified packages exist
   - Verify version constraints are valid
   - Ensure composer.json is writable

2. **Dependencies Not Removed**
   - This is expected behavior when other modules use the same packages
   - Use `composer show` to see all installed packages

3. **Version Conflicts**
   - Ensure all modules specify compatible version constraints
   - Update module dependencies to resolve conflicts

### Debug Information

Enable verbose logging to see detailed dependency management:

```bash
# Check what dependencies would be installed/removed
php artisan module:sync --dry-run

# View current module states
php artisan module:state

# Check composer dependencies
composer show
```

## Implementation Details

### Files Modified

- `vendor/rcv/core/src/Services/ComposerDependencyManager.php` - New service class
- `vendor/rcv/core/src/Console/Commands/Actions/ModuleEnableCommand.php` - Added dependency installation
- `vendor/rcv/core/src/Console/Commands/Actions/ModuleDisableCommand.php` - Added dependency removal
- `vendor/rcv/core/src/Console/Commands/Actions/ModuleSyncCommand.php` - Added dependency sync

### Service Class Methods

- `installModuleDependencies()` - Install dependencies for a module
- `removeModuleDependencies()` - Remove unused dependencies for a module
- `getModuleDependencies()` - Parse dependencies from module.json
- `filterUnusedDependencies()` - Check which dependencies can be safely removed

