# RCV Core Package - Working Commands Documentation

## Overview
This document lists all the working Artisan commands provided by the `rcv/core` package. All commands have been tested and are functioning properly.

## Package Information
- **Package Name**: rcv/core
- **Description**: Enterprise-Grade Modular Architecture for Laravel Applications
- **Version**: 1.x-dev
- **Total Commands**: 74 working commands (73 module: commands + 1 list:commands)

## Command Categories

### 1. Module Management Commands

#### Basic Module Operations
```bash
# Show module states from database
php artisan module:state

# Debug module state information  
php artisan module:debug

# Check health of all modules
php artisan module:health

# Enable one or more modules
php artisan module:enable <module>

# Disable one or more modules (with options for cleanup)
php artisan module:disable <module> [--force] [--remove] [--rollback] [--dry-run]

# Create new modules
php artisan module:make <name>

# Set active module for CLI session
php artisan module:use <module>

# Unset active module for CLI session
php artisan module:unuse

# Manage module backups
php artisan module:backup <action> [module]

# Backup subcommands:
php artisan module:backup create <module>              # Create a backup of a module
php artisan module:backup restore <module>             # Restore a module from backup
php artisan module:backup list                         # List all available backups
php artisan module:backup delete <module>              # Delete a module backup
php artisan module:backup cleanup                      # Clean up old backups

# Set up folder structure for new module
php artisan module:setup

# Synchronize module enabled status between JSON files and database
php artisan module:sync [module] [--db-priority] [--json-priority] [--force] [--dry-run]
```

#### Module Discovery & Analysis
```bash
# Compile class that registers all discovered modules
php artisan module:discover

# Update module autoload configuration for enabled modules
php artisan module:autoload

# Analyze module dependencies and detect conflicts/cycles
php artisan module:analyze

# Generate and visualize module dependency graph
php artisan module:dependency-graph

# Check for available module updates
php artisan module:check-updates

# Remove module compiled class file
php artisan module:clear-compiled
```

#### Module Information & Utilities
```bash
# Display table of module commands with selection
php artisan module:commands

# List all registered Artisan commands related to modules
php artisan list:commands

# Display information about Eloquent models in modules
php artisan module:model-show

# Prune models by module that are no longer needed
php artisan module:prune

# Manage modules through marketplace
php artisan module:marketplace <action> [module]

# Marketplace subcommands:
php artisan module:marketplace list                    # List all modules in marketplace
php artisan module:marketplace install <module>        # Install a module from marketplace
php artisan module:marketplace remove <module>         # Remove a module from marketplace
php artisan module:marketplace update <module>         # Update a module from marketplace
php artisan module:marketplace cleanup                 # Clean up orphaned module states

# Simple module profiler using ModuleMetrics timers
php artisan module:profile
```

### 2. Code Generation Commands (Make Commands)

#### Controllers & Models
```bash
# Create new controller for specified module
php artisan module:make-controller <name> <module> [--resource] [--api]

# Create new model for module
php artisan module:make-model <name> <module> [--migration] [--factory] [--seed]

# Create new resource class for specified module
php artisan module:make-resource <name> <module>

# Create new repository for module
php artisan module:make-repository <name> <module>
```

#### Events & Listeners
```bash
# Create new event class for specified module
php artisan module:make-event <name> <module>

# Create new event listener class for specified module
php artisan module:make-listener <name> <module>

# Create event provider for module
php artisan module:make-event-provider <module>
```

#### Services & Utilities
```bash
# Create service for module
php artisan module:make-service <name> <module>

# Create new helper class inside specified module
php artisan module:make-helper <name> <module>

# Create new exception class for specified module
php artisan module:make-exception <name> <module>

# Create new scope class for specified module
php artisan module:make-scope <name> <module>


```

#### Advanced Classes
```bash
# Create new channel class for specified module
php artisan module:make-channel <name> <module>

# Create new class using stub file inside specified module
php artisan module:make-class <name> <module>

# Generate new Artisan command for specified module
php artisan module:make-command <name> <module>

# Create new observer for specified module
php artisan module:make-observer <name> <module>

# Create new policy class for specified module
php artisan module:make-policy <name> <module>

# Create new validation rule for specified module
php artisan module:make-rule <name> <module>

# Create new trait class for specified module
php artisan module:make-trait <name> <module>

# Create new enum class inside specified module
php artisan module:make-enum <name> <module>
```

#### Components & UI
```bash
# Create new view file for specified module
php artisan module:make-view <name> <module>

# Create component classes and blade views for module
php artisan module:make-component <name> <module>

# Create new form request class inside module
php artisan module:make-request <name> <module>

# Create RouteServiceProvider for given module
php artisan module:make-route-provider <module>
```

#### Laravel Specific Classes
```bash
# Create new Eloquent cast class for specified module
php artisan module:make-cast <name> <module>

# Create new Job class inside src/Jobs folder of module
php artisan module:make-job <name> <module>

# Create new email class for specified module
php artisan module:make-mail <name> <module>

# Create new notification class for specified module
php artisan module:make-notification <name> <module>

# Create new action class for specified module
php artisan module:make-action <name> <module>

# Create new interface inside Repositories/Interfaces
php artisan module:make-interface <name> <module>

# Create new middleware class in specified module
php artisan module:make-middleware <name> <module>
```

### 3. Database Commands

#### Migrations
```bash
# Run migrations for all modules, specific module, or single migration
php artisan module:migrate [module] [--file=migration_file]

# Force drop all tables and re-run all module migrations
php artisan module:migrate-fresh

# Rollback and re-run all module migrations
php artisan module:migrate-refresh

# Reset the modules migrations
php artisan module:migrate-reset

# Rollback migrations for specific module or all modules
php artisan module:migrate-rollback [module]

# Show status of each module's migrations
php artisan module:migrate-status

# Run specific migration file from specific module
php artisan module:migrate-one <module> <migration>

# Create new migration file in specific module with field options
php artisan module:make-migration <name> <module> [--fields=title:string,slug:text] [--plain]
```

**Migration Creation Examples:**
```bash
# Create a basic table migration
php artisan module:make-migration create_posts_table Blog

# Create migration with predefined fields
php artisan module:make-migration create_posts_table Blog --fields="title:string,content:text,status:boolean"

# Create migration to add columns to existing table
php artisan module:make-migration add_slug_to_posts_table Blog --fields="slug:string:nullable"

# Create a plain migration without any fields
php artisan module:make-migration update_posts_table Blog --plain
```

#### Factories & Seeders
```bash
# Create new model factory inside module's Database/Factories directory
php artisan module:make-factory <name> <module>

# Create new seeder inside module folder
php artisan module:make-seeder <name> <module>

# Seed specific module's database seeds
php artisan module:seed <module>

# List all seeder classes in main and module seeders directories
php artisan module:seeder-list
```

### 4. Publishing Commands

```bash
# Publish module's configuration files to application's config directory
php artisan module:publish-config <module>

# Publish module's migration files to application's migrations directory
php artisan module:publish-migration <module>

# Publish module's translations to application
php artisan module:publish-translation <module>
```

### 5. DevOps & Development Tools

```bash
# Generate basic module documentation stubs
php artisan module:docs <module>

# Upgrade module to target version with checks
php artisan module:upgrade <module> <version>

# Publish Docker, CI, and K8s stubs for modules
php artisan module:devops:publish <module>

# Check and validate translation files/keys across locales in module
php artisan module:lang <module>
```

### 6. Migration & Upgrade Commands

```bash
# Migrate laravel-modules v1 modules to v2 structure
php artisan module:v2:migrate

# Update phpunit.xml source/include path with enabled modules
php artisan module:update-phpunit-coverage
```

## Complete Command Reference (All 73 Commands)

### Module Management & Core Operations (20 commands)
```bash
module:analyze                  # Analyze module dependencies and detect conflicts/cycles
module:autoload                 # Update module autoload configuration for enabled modules
module:backup                   # Manage module backups (create|restore|list|delete|cleanup)
module:check-updates            # Check for available module updates
module:clear-compiled           # Remove the module compiled class file
module:commands                 # Display a table of module commands and allow selection
module:debug                    # Debug module state information
module:dependency-graph         # Generate and visualize module dependency graph
module:discover                 # Compile a class that registers all discovered modules
module:enable                   # Enable one or more modules from Modules/ or vendor/rcv/
module:disable                  # Disable one or more modules (with cleanup options)
module:health                   # Check the health of modules
module:make                     # Create one or more new modules
module:marketplace              # Manage modules through the marketplace (list|install|remove|update|cleanup)
module:model-show               # Display information about Eloquent models in modules
module:profile                  # Simple module profiler using ModuleMetrics timers
module:prune                    # Prune models by module that are no longer needed
module:setup                    # Set up the folder structure for a new module
module:state                    # Show module states from the database
module:sync                     # Synchronize module enabled status between JSON files and database
```

### Session Management (2 commands)
```bash
module:use                      # Set the specified module as the active module for CLI session
module:unuse                    # Unset the active module for the current CLI session
```

### Code Generation - Controllers & Models (4 commands)
```bash
module:make-controller          # Create a new controller for the specified module
module:make-model               # Create a new model for a module
module:make-repository          # Create a new repository for a module
module:make-resource            # Create a new resource class for the specified module
```

### Code Generation - Events & Messaging (3 commands)
```bash
module:make-event               # Create a new event class for the specified module
module:make-listener            # Create a new event listener class for the specified module
module:make-event-provider      # Create a event provider for a module
```

### Code Generation - Services & Utilities (4 commands)
```bash
module:make-service             # Create a service for a module
module:make-helper              # Create a new helper class inside the specified module
module:make-exception           # Create a new exception class for the specified module
module:make-scope               # Create a new scope class for the specified module
```

### Code Generation - Advanced Classes (8 commands)
```bash
module:make-action              # Create a new action class for the specified module
module:make-channel             # Create a new channel class for the specified module
module:make-class               # Create a new class using a stub file inside the specified module
module:make-command             # Generate a new Artisan command for the specified module
module:make-enum                # Create a new enum class inside the specified module
module:make-observer            # Create a new observer for the specified module
module:make-policy              # Create a new policy class for the specified module
module:make-trait               # Create a new trait class for the specified module
```

### Code Generation - Validation & Requests (2 commands)
```bash
module:make-request             # Create a new form request class inside module
module:make-rule                # Create a new validation rule for the specified module
```

### Code Generation - Views & Components (2 commands)
```bash
module:make-component           # Create component classes and blade views for a module
module:make-view                # Create a new view file for the specified module
```

### Code Generation - Laravel Specific (7 commands)
```bash
module:make-cast                # Create a new Eloquent cast class for the specified module
module:make-interface           # Create a new interface inside Repositories/Interfaces
module:make-job                 # Create a new Job class inside src/Jobs folder of the module
module:make-mail                # Create a new email class for the specified module
module:make-middleware          # Create a new middleware class in the specified module
module:make-notification        # Create a new notification class for the specified module
module:make-route-provider      # Create a RouteServiceProvider for a given module
```

### Database - Migrations (8 commands)
```bash
module:make-migration           # Create a new migration file in a specific module
module:migrate                  # Run migrations for all modules, specific module, or single migration
module:migrate-fresh            # Force drop all tables and re-run all module migrations
module:migrate-one              # Run a specific migration file from a specific module
module:migrate-refresh          # Rollback and re-run all module migrations
module:migrate-reset            # Reset the modules migrations
module:migrate-rollback         # Rollback migrations for a specific module or all modules
module:migrate-status           # Show the status of each module's migrations
```

### Database - Factories & Seeders (4 commands)
```bash
module:make-factory             # Create a new model factory inside a module's Database/Factories directory
module:make-seeder              # Create a new seeder inside a module folder
module:seed                     # Seed the specific module's database seeds
module:seeder-list              # List all seeder classes in main and module seeders directories
```

### Publishing Commands (3 commands)
```bash
module:publish-config           # Publish module's configuration files to application's config directory
module:publish-migration        # Publish module's migration files to application's migrations directory
module:publish-translation      # Publish a module's translations to the application
```

### DevOps & Development Tools (4 commands)
```bash
module:devops:publish           # Publish Docker, CI, and K8s stubs for modules
module:docs                     # Generate basic module documentation stubs
module:lang                     # Check and validate translation files/keys across locales in a module
module:upgrade                  # Upgrade a module to a target version with checks
```

### Migration & Upgrade Utilities (2 commands)
```bash
module:update-phpunit-coverage  # Update phpunit.xml source/include path with enabled modules
module:v2:migrate               # Migrate laravel-modules v1 modules to v2 structure
```

### Utility Commands (1 command)
```bash
list:commands                   # List all registered Artisan commands related to modules with debug info
```

## Command Usage Examples

### Creating a New Module
```bash
# Create a new module named "Blog"
php artisan module:make Blog

# Enable the Blog module
php artisan module:enable Blog

# Check module status
php artisan module:state
```

### Generating Module Components
```bash
# Create a controller for the Blog module
php artisan module:make-controller PostController Blog --resource

# Create a model with migration and factory
php artisan module:make-model Post Blog --migration --factory

# Create a service for the Blog module
php artisan module:make-service PostService Blog

# Create a repository for the Blog module
php artisan module:make-repository PostRepository Blog
```

### Advanced Migration Examples
```bash
# Create a basic table migration
php artisan module:make-migration create_posts_table Blog

# Create migration with predefined fields
php artisan module:make-migration create_posts_table Blog --fields="title:string,content:text,status:boolean,published_at:timestamp:nullable"

# Create migration to add columns to existing table
php artisan module:make-migration add_slug_to_posts_table Blog --fields="slug:string:unique,meta_description:text:nullable"

# Create migration to add foreign key relationships
php artisan module:make-migration add_user_id_to_posts_table Blog --fields="user_id:unsignedBigInteger:foreign"

# Create a plain migration without any fields for custom logic
php artisan module:make-migration update_posts_table_indexes Blog --plain
```

### Database Operations
```bash
# Run migrations for Blog module
php artisan module:migrate Blog

# Run a specific migration file
php artisan module:migrate-one Blog 2024_01_01_000000_create_posts_table

# Check migration status
php artisan module:migrate-status

# Create a seeder for Blog module
php artisan module:make-seeder PostSeeder Blog

# Run the seeder
php artisan module:seed Blog

# Create a factory for the Post model
php artisan module:make-factory PostFactory Blog
```

### Complete Component Generation Workflow
```bash
# Create a complete CRUD setup for a Blog module
php artisan module:make Blog
php artisan module:make-model Post Blog --migration --factory
php artisan module:make-controller PostController Blog --resource
php artisan module:make-repository PostRepository Blog
php artisan module:make-service PostService Blog
php artisan module:make-request CreatePostRequest Blog
php artisan module:make-request UpdatePostRequest Blog
php artisan module:make-resource PostResource Blog
php artisan module:make-seeder PostSeeder Blog

# Enable and migrate
php artisan module:enable Blog
php artisan module:migrate Blog
php artisan module:seed Blog
```

### Module Management
```bash
# Analyze all modules for dependencies
php artisan module:analyze

# Check health of all modules
php artisan module:health

# Generate dependency graph
php artisan module:dependency-graph

# Update autoload configuration for enabled modules
php artisan module:autoload

# Discover and compile modules
php artisan module:discover
```

### Marketplace Operations
```bash
# List all modules in marketplace
php artisan module:marketplace list

# Install a module from marketplace
php artisan module:marketplace install SomeModule

# Remove a module from marketplace
php artisan module:marketplace remove SomeModule

# Update a module from marketplace
php artisan module:marketplace update SomeModule

# Clean up orphaned module states
php artisan module:marketplace cleanup

# Force marketplace operations
php artisan module:marketplace install SomeModule --force
```

### Module Synchronization
```bash
# Check what would be synchronized (dry run)
php artisan module:sync --dry-run

# Synchronize all modules, giving database priority
php artisan module:sync --db-priority

# Synchronize specific modules
php artisan module:sync Blog User --db-priority

# Give JSON file priority over database
php artisan module:sync --json-priority

# Force synchronization (same as --db-priority)
php artisan module:sync --force

# Sync a single module
php artisan module:sync Harshit --db-priority
```

**Use Cases for module:sync:**
- **Create missing DB entries**: When modules have JSON state but no database entry, creates matching DB entries
- **Maintain consistency**: Ensures JSON and database states are always synchronized
- **Fix manual JSON edits**: Handles cases where someone manually edits module.json files
- **Conflict resolution**: Resolve conflicts between JSON file and database states
- **Audit compliance**: Ensure all modules have proper database tracking

### Backup Operations
```bash
# Create a backup of a module
php artisan module:backup create Blog

# List all available backups
php artisan module:backup list

# Restore a module from backup
php artisan module:backup restore Blog --backup=/path/to/backup

# Delete a specific backup
php artisan module:backup delete Blog

# Clean up old backups
php artisan module:backup cleanup
```

## Notes

- **All 73 commands have been tested and are working properly**
- Commands support various options and flags for customization
- Use `--help` flag with any command to see detailed usage information
- Some commands may require specific module structures to function properly
- The package provides comprehensive modular architecture support for Laravel applications

### Migration Field Types Supported:
- **String types**: `string`, `text`, `longText`, `mediumText`
- **Numeric types**: `integer`, `bigInteger`, `unsignedInteger`, `unsignedBigInteger`, `decimal`, `float`, `double`
- **Date/Time types**: `date`, `dateTime`, `timestamp`, `time`
- **Boolean**: `boolean`
- **JSON**: `json`, `jsonb`
- **Binary**: `binary`
- **Special**: `uuid`, `enum`

### Field Modifiers:
- `nullable` - Allow null values
- `unique` - Add unique constraint
- `index` - Add database index
- `foreign` - Create foreign key relationship
- `default:value` - Set default value

### Migration Field Examples:
```bash
# String with length and nullable
--fields="name:string:100:nullable"

# Decimal with precision
--fields="price:decimal:8,2"

# Foreign key relationship
--fields="user_id:unsignedBigInteger:foreign"

# Enum with options
--fields="status:enum:active,inactive,pending"

# Multiple fields with various types
--fields="title:string,content:text,price:decimal:10,2,is_active:boolean:default:true,created_by:unsignedBigInteger:foreign:nullable"
```

### Module Sync Behavior:
The `module:sync` command ensures database entries exist for all modules and match their JSON state:
- **Missing DB + JSON enabled** → Creates enabled database entry
- **Missing DB + JSON disabled** → Creates disabled database entry  
- **Conflicting states** → Resolves based on priority flags (`--db-priority` or `--json-priority`)
- **Already synced** → No action needed

This ensures modules can only work when they have proper database entries, preventing manual JSON bypassing.

### Important Notes:
- **Database-First**: Module state is determined by database entries, not JSON files
- **Autoloading**: Enabled modules are automatically added to composer.json autoload
- **Service Providers**: Module service providers are loaded only for enabled modules
- **Routes**: Only enabled modules have their routes registered and accessible
- **Commands**: Use `php artisan route:list` to see all registered module routes




## New Feature: Composer Dependency Management

The RCV Core package now supports automatic Composer dependency management for modules. When you enable, disable, or sync modules, the system will automatically handle third-party package dependencies.

### How It Works

Each module's `module.json` file can now include a `dependencies` array that lists Composer packages required by that module:

```json
{
    "name": "User",
    "version": "1.0.0",
    "enabled": true,
    "dependencies": [
        "guzzlehttp/guzzle:^7.0",
        "league/fractal:^0.20",
        "spatie/laravel-permission:^5.0"
    ],
    "dependents": [],
    "config": []
}
```

### Dependency Format

Dependencies can be specified in two formats:
- **With version**: `"package-name:version"` (e.g., `"guzzlehttp/guzzle:^7.0"`)
- **Without version**: `"package-name"` (defaults to `*` - latest version)

### Automatic Dependency Management

The system automatically handles dependencies during these operations:

#### Module Enable (`php artisan module:enable`)
- Reads dependencies from `module.json`
- Adds packages to main `composer.json`
- Runs `composer install` to download packages
- Continues with normal module enable process

#### Module Disable (`php artisan module:disable`)
- Checks if dependencies are used by other enabled modules
- Only removes packages that are not used elsewhere
- Updates main `composer.json`
- Runs `composer remove` for unused packages
- Continues with normal module disable process

#### Module Sync (`php artisan module:sync`)
- When enabling modules: installs their dependencies
- When disabling modules: removes unused dependencies
- Handles dependency management during state synchronization

### Smart Dependency Removal

The system intelligently handles dependency removal:
- **Shared Dependencies**: If multiple modules use the same package, it won't be removed when one module is disabled
- **Dependency Tracking**: Only removes packages when no enabled modules require them
- **Safe Removal**: Prevents accidental removal of packages needed by other modules

### Example Usage

```bash
# Enable a module with dependencies
php artisan module:enable Blog
# Output: Installing dependencies for module [Blog]: guzzlehttp/guzzle, league/fractal
# Output: Running composer install...
# Output: Module [Blog] enabled.

# Disable a module
php artisan module:disable Blog
# Output: Removing dependencies for module [Blog]: guzzlehttp/guzzle, league/fractal
# Output: All dependencies are still used by other modules, skipping removal
# Output: Module [Blog] disabled.

# Sync modules with dependency management
php artisan module:sync --json-priority
# Output: Installing dependencies for module [User]: spatie/laravel-permission
# Output: Module synchronization completed!
```

### Benefits

1. **Automatic Package Management**: No need to manually manage Composer dependencies
2. **Clean Environment**: Unused packages are automatically removed
3. **Dependency Safety**: Prevents removal of packages used by other modules
4. **Consistent State**: Dependencies are always in sync with module states
5. **Easy Module Distribution**: Modules can specify their exact requirements

## Support

For issues and support:
- GitHub: https://github.com/RCV-Technologies/laravel-module
- Documentation: https://const-ant-laravel-corex-docs.vercel.app/
- Email: support@rcvtechnologies.com