# Module Dependents Feature - Corrected Logic

## Overview

The **dependents** feature allows you to define which modules a module DEPENDS ON. This is different from the `dependencies` array which is used for third-party packages.

## Key Concepts

### Dependencies vs Dependents

- **`dependencies`**: Array of third-party packages (e.g., `laravel/sanctum`, `doctrine/dbal`)
  - Used for Composer packages
  - Checked and installed when module is enabled

- **`dependents`**: Array of internal module names that THIS module DEPENDS ON
  - Used for module-to-module relationships
  - Example: User module depends on Vishal and Ashish modules

## Correct Understanding

```json
{
    "name": "User",
    "dependents": ["Vishal", "Ashish"]
}
```

**This means:** "User module DEPENDS ON Vishal and Ashish modules" (User needs them to work)

**NOT:** "Vishal and Ashish depend on User"

## How It Works

### 1. Enabling a Module with Dependents

When you enable a module that has `dependents` defined:

```json
{
    "name": "User",
    "version": "1.0.0",
    "enabled": false,
    "dependencies": ["doctrine/dbal"],
    "dependents": ["Vishal", "Ashish"]
}
```

**Behavior:**
- User module needs Vishal and Ashish to work
- System checks if Vishal and Ashish modules exist
- If they exist, asks: "Module [User] requires [Vishal]. Enable it now?"
- If user confirms, Vishal module is enabled first
- Same process for Ashish module
- If a required module doesn't exist, shows error and asks to continue
- If user says NO to continue, User module is NOT enabled

**Command:**
```bash
php artisan module:enable User
```

**Output:**
```
Enabling module [User]...
Installing dependencies for module [User]...
Module [User] enabled.

Module [User] depends on: Vishal, Ashish

Module [User] requires [Vishal]. Enable it now? (yes/no) [yes]:
> yes
Enabling required module [Vishal]...
✅ Required module [Vishal] enabled successfully.

Module [User] requires [Ashish]. Enable it now? (yes/no) [yes]:
> yes
Enabling required module [Ashish]...
✅ Required module [Ashish] enabled successfully.
```

**If Ashish doesn't exist:**
```
Enabling module [User]...
Module [User] depends on: Vishal, Ashish

⚠️  Required module(s) not found: Ashish
Module [User] requires these modules to work properly. Do you want to continue anyway? (yes/no) [no]:
> no
Operation cancelled. Module [User] was NOT enabled.
```

### 2. Disabling a Module

**Scenario 1: Disabling User (which depends on Vishal)**
```bash
php artisan module:disable User
```

**Behavior:**
- User depends on Vishal, not the other way around
- Disabling User is ALLOWED (no --force needed)
- User will be disabled successfully

**Scenario 2: Disabling Vishal (which User depends on)**
```bash
php artisan module:disable Vishal
```

**Behavior:**
- System checks if any module has "Vishal" in their `dependents` array
- Finds that User has `"dependents": ["Vishal"]`
- Shows error and BLOCKS disabling
- Requires --force to override

**Output:**
```
Processing module: Vishal
⚠️  Cannot disable [Vishal] because the following modules depend on it:
   - User
Use --force to override and proceed anyway (may break dependent modules).
```

**Force Disable:**
```bash
php artisan module:disable Vishal --force
```

### 3. Syncing Modules with Dependents

When syncing modules, required modules are automatically handled:

```bash
php artisan module:sync
```

**Behavior:**
- If a module is enabled and has `dependents`, those required modules are auto-enabled
- Ensures consistency across all modules

## Configuration Examples

### Example 1: Simple Dependency

**User Module** (`Modules/User/module.json`):
```json
{
    "name": "User",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": ["doctrine/dbal"],
    "dependents": ["Vishal"],
    "config": []
}
```

**Meaning:** User module DEPENDS ON Vishal module (User needs Vishal to work)

**Vishal Module** (`Modules/Vishal/module.json`):
```json
{
    "name": "Vishal",
    "version": "1.0.0",
    "enabled": false,
    "dependencies": [],
    "dependents": [],
    "config": []
}
```

**Dependency:** User → Vishal (User needs Vishal)

### Example 2: Multiple Dependencies

**Sales Module** (`Modules/Sales/module.json`):
```json
{
    "name": "Sales",
    "version": "1.0.0",
    "enabled": false,
    "dependencies": [],
    "dependents": ["Product", "User", "Inventory"],
    "config": []
}
```

**Meaning:** Sales module DEPENDS ON Product, User, and Inventory modules

When enabling Sales module, it will check and enable Product, User, and Inventory first.

### Example 3: Dependency Chain

**Reports Module** (`Modules/Reports/module.json`):
```json
{
    "name": "Reports",
    "version": "1.0.0",
    "enabled": false,
    "dependencies": [],
    "dependents": ["Sales"],
    "config": []
}
```

**Sales Module** (`Modules/Sales/module.json`):
```json
{
    "name": "Sales",
    "version": "1.0.0",
    "enabled": false,
    "dependencies": [],
    "dependents": ["Product"],
    "config": []
}
```

**Product Module** (`Modules/Product/module.json`):
```json
{
    "name": "Product",
    "version": "1.0.0",
    "enabled": false,
    "dependencies": [],
    "dependents": [],
    "config": []
}
```

**Chain:** Reports → Sales → Product

When enabling Reports:
1. Reports needs Sales
2. Sales needs Product
3. Product is enabled first, then Sales, then Reports

## Command Reference

### Enable Module
```bash
# Enable single module
php artisan module:enable User

# Enable multiple modules
php artisan module:enable User Vishal Ashish
```

**Behavior:** Checks if required modules (in `dependents` array) exist and enables them first

### Disable Module
```bash
# Disable module (checks if other modules need it)
php artisan module:disable User

# Force disable (ignores checks)
php artisan module:disable Vishal --force

# Disable with rollback
php artisan module:disable User --rollback

# Disable and remove
php artisan module:disable User --remove --force
```

**Behavior:** 
- Disabling User (which depends on Vishal) → Allowed
- Disabling Vishal (which User depends on) → Blocked (unless --force)

### Sync Modules
```bash
# Sync all modules
php artisan module:sync

# Sync specific modules
php artisan module:sync User Vishal

# Sync with priority
php artisan module:sync --db-priority
php artisan module:sync --json-priority

# Dry run (preview changes)
php artisan module:sync --dry-run
```

## Error Handling

### Missing Required Module

**Scenario:** User module has `"dependents": ["Ashish"]` but Ashish module doesn't exist.

**Output:**
```
Module [User] depends on: Ashish
⚠️  Required module(s) not found: Ashish
Module [User] requires these modules to work properly. Do you want to continue anyway? (yes/no) [no]:
> no
Operation cancelled. Module [User] was NOT enabled.
```

### Circular Dependencies

**Not automatically detected** - avoid creating circular dependencies:
```
❌ BAD:
User → Vishal → User (circular)

✅ GOOD:
User → Vishal → Core (linear)
```

## Best Practices

1. **Use dependents for module requirements**
   - User depends on Vishal
   - Sales depends on Product
   - Reports depends on Sales

2. **Use dependencies for third-party packages**
   - laravel/sanctum
   - doctrine/dbal
   - guzzlehttp/guzzle

3. **Keep dependency chains simple**
   - Avoid deep nesting (max 2-3 levels)
   - Avoid circular dependencies

4. **Document module relationships**
   - Add comments in module.json
   - Update module README files

5. **Test thoroughly**
   - Test enable/disable scenarios
   - Test with missing modules
   - Test force operations

## Migration Guide

### Updating Existing Modules

1. Open your module's `module.json` file
2. Add the `dependents` array if it doesn't exist
3. List modules that THIS module DEPENDS ON (not modules that depend on this one)

**Before:**
```json
{
    "name": "User",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": ["doctrine/dbal"]
}
```

**After:**
```json
{
    "name": "User",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": ["doctrine/dbal"],
    "dependents": ["Vishal", "Ashish"]
}
```

**Meaning:** User module needs Vishal and Ashish modules to work

4. Run sync to apply changes:
```bash
php artisan module:sync
```

## Troubleshooting

### Issue: Required module not enabling

**Solution:** Check if the required module exists in `Modules/` directory and has a valid `module.json` file.

### Issue: Cannot disable module

**Solution:** Check which modules list it in their `dependents` array. Use `--force` if you're sure.

### Issue: Module enabled but doesn't work

**Solution:** Check if all required modules (in `dependents` array) are enabled.

### Issue: Sync not working

**Solution:** Run with `--dry-run` first to see what would change, then use `--json-priority` or `--db-priority`.

## Implementation Details

The feature is implemented in:
- `ModuleEnableCommand.php` - Handles enabling dependent modules
- `ModuleDisableCommand.php` - Checks for dependent modules before disabling
- `ModuleSyncCommand.php` - Syncs dependent modules automatically
- `module.json` - Configuration file for each module

## Summary

The `dependents` feature provides a robust way to manage module relationships in your RCV Core application. It ensures that when you enable a module, all modules that depend on it can be automatically enabled, and prevents accidental disabling of modules that other modules rely on.
