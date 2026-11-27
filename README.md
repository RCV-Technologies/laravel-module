<p align="center">
  <img src="https://www.rcvtechnologies.com/wp-content/uploads/2024/02/RCV-LOGO.webp" alt="RCV Technologies" width="260">
</p>

<h1 align="center">RCV Core — Modular Laravel</h1>

<p align="center">
  <a href="https://packagist.org/packages/rcv/core"><img src="https://img.shields.io/packagist/v/rcv/core.svg" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/rcv/core"><img src="https://img.shields.io/packagist/dt/rcv/core.svg" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/rcv/core"><img src="https://img.shields.io/packagist/php-v/rcv/core.svg" alt="PHP Version"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/packagist/l/rcv/core.svg" alt="License"></a>
</p>

Split a Laravel application into self-contained **modules** (each with its own routes, controllers, models, migrations, views, config and service provider), turn them on and off at runtime, and scaffold everything with `module:*` Artisan commands. Includes a base Repository / Service layer for the service-repository pattern.

**Documentation:** <https://const-ant-laravel-corex-docs.vercel.app/>

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How modules work](#how-modules-work)
- [Module structure](#module-structure)
- [The `module.json` file](#the-modulejson-file)
- [Enabling and disabling modules](#enabling-and-disabling-modules)
- [Routes](#routes)
- [Repositories and services](#repositories-and-services)
- [Migrations and seeders](#migrations-and-seeders)
- [Dependency analysis](#dependency-analysis)
- [Backups](#backups)
- [Facades](#facades)
- [Configuration](#configuration)
- [Command reference](#command-reference)
- [Upgrading](#upgrading)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2 or newer |
| Laravel | 10, 11, 12 or 13 |
| Database | Any database supported by Laravel (module state is stored in a `module_states` table) |

## Installation

```bash
composer require rcv/core
php artisan migrate
```

The service provider is registered automatically through Laravel package discovery. `php artisan migrate` creates the package's `module_states` table, so you do not need to publish the migrations.

Optional — publish the package files if you want to customise them:

```bash
# Config files: config/core.php, marketplace.php, metrics.php, security.php, communication.php
php artisan vendor:publish --tag=rcv-core-config

# Package migrations (only if you need to edit them)
php artisan vendor:publish --tag=rcv-core-migrations
```

## Quick start

```bash
# 1. Create a module in Modules/Blog
php artisan module:make Blog

# 2. Enable it (stores the state in the module_states table)
php artisan module:enable Blog

# 3. Add code to it
php artisan module:make-model Post Blog --migration
php artisan module:make-controller PostController Blog --resource

# 4. Run its migrations
php artisan module:migrate Blog

# 5. Check the result
php artisan module:state Blog
php artisan module:analyze
```

Module routes go in `Modules/Blog/src/Routes/web.php` and `Modules/Blog/src/Routes/api.php`; they are loaded automatically while the module is enabled.

---

## How modules work

- Every module lives in `Modules/<Name>` in your project root and uses the namespace `Modules\<Name>\`, mapped to `Modules/<Name>/src`.
- Each module has its own service provider, `Modules\<Name>\Providers\<Name>ServiceProvider`. On every request, RCV Core registers the providers of **enabled** modules only.
- Whether a module is enabled is stored in the `module_states` database table (and cached). The module's files are never changed when you enable or disable it.
- Modules are loaded in dependency order. A module whose required modules (`dependents` in `module.json`) are missing or disabled is skipped and a warning is logged.

## Module structure

`php artisan module:make Blog` creates:

```
Modules/Blog/
├── module.json                      # Name, version and dependencies (see below)
├── composer.json
└── src/
    ├── Config/config.php            # Merged into config('blog')
    ├── Console/                     # Module Artisan commands (auto-registered)
    ├── Database/
    │   ├── Factories/
    │   ├── Migrations/
    │   └── Seeders/BlogDatabaseSeeder.php
    ├── Http/
    │   ├── Controllers/
    │   │   ├── Api/
    │   │   └── HomeController.php
    │   ├── Middleware/
    │   └── Requests/
    ├── Models/
    ├── Providers/
    │   ├── BlogServiceProvider.php
    │   └── BlogEventServiceProvider.php
    ├── Repositories/
    ├── Resources/
    │   ├── assets/ (css, js, images)
    │   ├── lang/
    │   └── views/                   # view('blog::index')
    ├── Routes/
    │   ├── web.php
    │   └── api.php
    └── Services/
```

Other folders (Events, Listeners, Jobs, Policies, Rules, …) are created by the matching `module:make-*` command when you first need them.

## The `module.json` file

```json
{
    "name": "Blog",
    "version": "1.0.0",
    "description": "Blog posts and comments",
    "dependencies": ["guzzlehttp/guzzle:^7.0"],
    "dependents": ["UserManagement"]
}
```

| Field | Required | Meaning |
|---|---|---|
| `name` | Yes | Must match the module folder name exactly. |
| `version` | Yes | Module version string. |
| `description` | No | Free text. |
| `dependents` | No | **Other modules this module needs.** `module:enable` offers to enable them; this module will not load if any of them is disabled. |
| `dependencies` | No | Composer packages this module needs, for documentation. **They are not installed automatically** — add them to your application's `composer.json` yourself (`composer require ...`). |

> The naming is easy to mix up: `dependents` lists *modules*, `dependencies` lists *Composer packages*.

## Enabling and disabling modules

```bash
php artisan module:enable Blog              # Enable one module (offers to enable required modules)
php artisan module:enable Blog Shop         # Enable several
php artisan module:enable --all             # Enable every module in Modules/

php artisan module:disable Blog             # Disable only — no files or tables are touched
php artisan module:disable Blog --dry-run   # Show what would happen
php artisan module:disable Blog --force     # Disable even if other modules depend on it

php artisan module:state                    # State of all modules
php artisan module:state Blog               # State of one module
```

`module:disable` is non-destructive by default. Two options are **destructive** and should be used with care:

| Option | Effect |
|---|---|
| `--rollback` | Rolls back the module's migrations (drops its tables). |
| `--remove` | Deletes the module's files and its `module_states` row. |

Checking state from code:

```php
module_enabled('Blog');                                        // helper
Module::isModuleEnabled('Blog');                               // facade
app(\RCV\Core\Services\ModuleManager::class)->isModuleEnabled('Blog');
```

### Protecting routes when a module is disabled

Routes of a disabled module are not registered at all. If you also want a friendly "module disabled" response for routes defined elsewhere, extend `RCV\Core\Http\Middleware\ModuleMiddleware` in your module and register it as a route middleware:

```php
namespace Modules\Blog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RCV\Core\Http\Middleware\ModuleMiddleware;

class BlogModuleMiddleware extends ModuleMiddleware
{
    protected function processRequest(Request $request, Closure $next)
    {
        return $next($request);
    }
}
```

The module name is taken from the class namespace (`Modules\Blog\…` → `Blog`). When the module is disabled, the middleware returns a 403 (JSON or the `core::errors.module-disabled` view).

## Routes

The generated module service provider loads:

| File | Middleware group | URL prefix |
|---|---|---|
| `src/Routes/web.php` | `web` (sessions, CSRF, cookies) | none |
| `src/Routes/api.php` | `api` (stateless, rate limiting) | none — add `Route::prefix('api')` yourself if you want one |

```php
// Modules/Blog/src/Routes/web.php
use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\PostController;

Route::middleware('auth')->group(function () {
    Route::resource('posts', PostController::class);
});
```

`php artisan route:cache` is supported.

## Repositories and services

RCV Core ships two abstract base classes:

- `RCV\Core\Repositories\BaseRepository` — `all()`, `paginate()`, `find()`, `findBy()`, `create()`, `update()`, `delete()`, `getModel()`
- `RCV\Core\Services\BaseService` — `getAll()`, `getPaginated()`, `getById()`, `getByField()`, `create()`, `update()`, `delete()`, `getRepository()`

Generate them with:

```bash
php artisan module:make-repository PostRepository Blog
php artisan module:make-service Post Blog          # creates PostService
```

The base repository receives the Eloquent model through its constructor, so pass your model in:

```php
namespace Modules\Blog\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Blog\Models\Post;
use RCV\Core\Repositories\BaseRepository;

class PostRepository extends BaseRepository
{
    public function __construct(Post $post)
    {
        parent::__construct($post);
    }

    public function published(): Collection
    {
        return $this->model->where('published', true)->latest()->get();
    }
}
```

```php
namespace Modules\Blog\Services;

use Modules\Blog\Repositories\PostRepository;
use RCV\Core\Services\BaseService;

class PostService extends BaseService
{
    public function __construct(PostRepository $repository)
    {
        parent::__construct($repository);
    }
}
```

> `findBy()` / `getByField()` take a column name. Never pass a column name that comes straight from user input.

## Migrations and seeders

```bash
php artisan module:make-migration create_posts_table Blog
php artisan module:make-migration create_posts_table Blog --fields="title:string,body:text"
php artisan module:make-migration add_status_to_posts_table Blog --fields="status:string"

php artisan module:migrate                       # All modules
php artisan module:migrate Blog                  # One module
php artisan module:migrate-status
php artisan module:migrate-rollback --module=Blog --step=1
php artisan module:migrate-refresh               # Roll back and re-run all module migrations
php artisan module:migrate-reset                 # Roll back all module migrations

php artisan module:make-seeder PostSeeder Blog
php artisan module:seed Blog
```

> ⚠️ **`module:migrate-fresh` drops every table in your database** (not just module tables), then runs all application and module migrations again. In production it asks for confirmation unless you pass `--force`.

Laravel's own `php artisan migrate` also picks up the migrations of every module.

To have `php artisan db:seed` also run each enabled module's `<Name>DatabaseSeeder`, set `core.seed_modules` to `true` (off by default).

## Dependency analysis

```bash
php artisan module:analyze                      # Table: modules, enabled state, issues
php artisan module:analyze --format=json
php artisan module:analyze --format=dot > modules.dot
dot -Tpng modules.dot -o modules.png            # Requires Graphviz

php artisan module:dependency-graph --validate  # Check for missing/disabled dependencies
php artisan module:dependency-graph --circular  # Look for circular dependencies
```

Example output:

```
Modules: 3 | Relations: 1
Issues detected:
- Module [Blog] requires [UserManagement] which is disabled
+----------------+---------+---------+
| Module         | Enabled | Version |
+----------------+---------+---------+
| Blog           | yes     | 1.0.0   |
| UserManagement | no      | 1.2.1   |
| Analytics      | yes     | 0.9.0   |
+----------------+---------+---------+
```

`module:analyze` lists every module; it does not take a module name.

## Backups

```bash
php artisan module:backup create Blog
php artisan module:backup list Blog
php artisan module:backup restore --backup=<path-from-list>
php artisan module:backup delete --backup=<path-from-list>
php artisan module:backup cleanup               # Remove backups past the retention period
```

Backups are stored in `storage/app/Modules/backups` (configurable in `marketplace.modules.backup.path`). `restore` and `delete` only accept paths inside that directory.

## Facades

| Facade | Purpose |
|---|---|
| `Module` | Module state: `isModuleEnabled()`, `getEnabledModules()`, `getAvailableModules()`, `getModuleState()`, `enableModule()`, `disableModule()` |
| `ModuleMetrics` | Simple timers, counters and gauges: `startTimer()`, `endTimer()`, `increment()`, `decrement()`, `setGauge()`, `recordMemoryUsage()` |
| `MessageBus` | `publish($event, $payload)` dispatches a Laravel event through a queued job |
| `Rpc` | `register($name, $closure)` / `call($name, $payload)` for in-process calls between modules |
| `Security` | Thin wrapper over Laravel's `Gate` (`allows()`, `denies()`) |
| `Rbac` | In-memory role/permission checks, configured from `security.rbac.roles` |
| `Abac` | Register and evaluate attribute-based rules (`define()`, `allows()`) |

```php
use ModuleMetrics;

ModuleMetrics::startTimer('blog.import');
// ... work ...
$milliseconds = ModuleMetrics::endTimer('blog.import');

ModuleMetrics::increment('blog.posts_created');
```

Try it with `php artisan module:profile --duration=5`.

## Configuration

Publish with `php artisan vendor:publish --tag=rcv-core-config`. The most useful keys in `config/core.php`:

| Key | Default | Description |
|---|---|---|
| `lazy_load_modules` | `false` | Register module providers in `boot()` instead of `register()`. |
| `hot_reload` | `false` | Ignore the compiled module list (`bootstrap/cache/modules.php`); useful while developing. |
| `seed_modules` | `false` | Run enabled modules' seeders after `php artisan db:seed`. |
| `max_boot_failures` | `3` | Stop booting after this many module providers fail. `0` = no limit. |
| `strict_mode` / `fail_fast_on_error` | `false` | Throw instead of logging when a module provider fails. |
| `performance_monitoring` | `false` | Log how long module provider registration takes. |
| `messaging.queue` / `tries` / `backoff` / `after_commit` | — | Defaults for `MessageBus` jobs. |

Other config files: `marketplace.php` (cache TTL, backup path and retention), `metrics.php` (`RCV_METRICS_ENABLED`, `RCV_METRICS_DRIVER`, `RCV_METRICS_CACHE_STORE`), `security.php` (RBAC roles), `communication.php`.

Generated modules have their own `src/Config/config.php`, available as `config('<module name in lowercase>')`.

---

## Command reference

Run `php artisan module:commands` for an interactive menu of all commands.

### Module management

| Command | Description |
|---|---|
| `module:make <name>...` | Create one or more modules |
| `module:setup <name>` | Create only the folder structure for a module |
| `module:enable [<module>...] [--all]` | Enable modules |
| `module:disable <module>... [--force] [--dry-run] [--rollback] [--remove] [--no-autoload]` | Disable modules |
| `module:state [<name>]` | Show module state from the database |
| `module:debug [<module>]` | Detailed state information |
| `module:health [<module>]` | Check module structure and configuration |
| `module:marketplace <action> [<module>...] [--force]` | `list`, `install`, `remove`, `update`, `cleanup` |
| `module:check-updates` | Check for module updates |
| `module:upgrade <module> [--to=] [--dry-run]` | Upgrade a module to a target version |
| `module:backup <action> [<module>] [--backup=]` | `create`, `list`, `restore`, `delete`, `cleanup` |
| `module:analyze [--format=table\|json\|dot]` | Dependency analysis |
| `module:dependency-graph [--format=dot\|json] [--output=] [--validate] [--circular]` | Dependency graph |
| `module:discover` | Compile the list of module providers |
| `module:clear-compiled` | Remove the compiled module list |
| `module:autoload` | Register enabled module namespaces |
| `module:use <module>` / `module:unuse` | Set / clear the active module for the CLI session |
| `module:commands` | Interactive command menu |

### Generators

All generators take the class name first and the module name last.

| Command | Creates |
|---|---|
| `module:make-controller <name> <module> [--resource] [--api]` | Controller |
| `module:make-model <name> <module> [--migration] [--factory] [--seed]` | Eloquent model |
| `module:make-migration <name> <module> [--fields=] [--plain]` | Migration |
| `module:make-seeder <name> <module>` | Seeder |
| `module:make-factory <name> <module>` | Model factory |
| `module:make-repository <name> <module>` | Repository |
| `module:make-service <name> <module>` | Service |
| `module:make-interface <name> <module>` | Interface in `Repositories/Interfaces` |
| `module:make-request <name> <module>` | Form request (denies access until you implement `authorize()`) |
| `module:make-resource <name> <module> [--collection]` | API resource |
| `module:make-middleware <name> <module>` | Middleware |
| `module:make-event <name> <module>` | Event |
| `module:make-listener <name> <event> <module>` | Listener |
| `module:make-job <name> <module>` | Queued job |
| `module:make-mail <name> <module>` | Mailable |
| `module:make-notification <name> <module>` | Notification |
| `module:make-policy <name> <module>` | Policy |
| `module:make-rule <name> <module>` | Validation rule |
| `module:make-observer <name> <module>` | Model observer |
| `module:make-scope <name> <module>` | Query scope |
| `module:make-cast <name> <module>` | Eloquent cast |
| `module:make-enum <name> <module>` | Enum |
| `module:make-trait <name> <module>` | Trait |
| `module:make-action <name> <module>` | Action class |
| `module:make-helper <name> <module>` | Helper class |
| `module:make-exception <name> <module>` | Exception |
| `module:make-channel <name> <module>` | Broadcast channel |
| `module:make-command <name> <module>` | Artisan command |
| `module:make-class <name> <module>` | Plain class |
| `module:make-component <names> <module>` | Blade component class and view |
| `module:make-view <name> <module>` | Blade view |
| `module:make-event-provider <name> <module>` | Event service provider |
| `module:make-route-provider <module> [--force]` | Route service provider |

### Database

| Command | Description |
|---|---|
| `module:migrate [<module>] [--migration=] [--force]` | Run module migrations |
| `module:migrate-one <migration_name> <module_name>` | Run a single migration file |
| `module:migrate-status` | Migration status per module |
| `module:migrate-rollback [--module=] [--step=] [--force]` | Roll back module migrations |
| `module:migrate-refresh` | Roll back and re-run all module migrations |
| `module:migrate-reset` | Roll back all module migrations |
| `module:migrate-fresh [--force]` | ⚠️ Drop **all** tables, then re-run every migration |
| `module:seed <module> [--class=] [--fresh]` | Run a module's seeders |
| `module:seeder-list` | List application and module seeders |
| `module:model-show [<module>] [--json]` | Show model information |
| `module:prune <module> [--model=] [--except=] [--chunk=] [--pretend]` | Prune models in a module |

### Publishing, docs and tooling

| Command | Description |
|---|---|
| `module:publish-config <module>` | Copy the module's config to `config/` |
| `module:publish-migration <module>` | Copy the module's migrations to `database/migrations` |
| `module:publish-translation <module>` | Copy the module's translations to the application |
| `module:lang <module> [--fallback=] [--placeholder=]` | Find missing translation keys across locales |
| `module:docs [--output=]` | Generate documentation stubs |
| `module:devops:publish [--output=]` | Publish Docker, CI and Kubernetes stubs |
| `module:profile [--duration=]` | Try out `ModuleMetrics` timers |
| `module:update-phpunit-coverage` | Add enabled modules to `phpunit.xml` coverage |
| `module:v2:migrate` | Convert laravel-modules v1 modules to the v2 layout |

---

## Upgrading

### To 2.0.1

1. `composer update rcv/core`
2. **Update existing module providers.** Modules generated with 2.0.0 or earlier load their routes without the `web` / `api` middleware groups, so their web routes have no CSRF protection. In each `Modules/<Name>/src/Providers/<Name>ServiceProvider.php`, replace `registerRoutes()` with:

   ```php
   use Illuminate\Support\Facades\Route;

   protected function registerRoutes(): void
   {
       if ($this->app->routesAreCached()) {
           return;
       }

       $webRoutePath = __DIR__ . '/../Routes/web.php';
       $apiRoutePath = __DIR__ . '/../Routes/api.php';

       if (file_exists($webRoutePath)) {
           Route::middleware('web')->group($webRoutePath);
       }

       if (file_exists($apiRoutePath)) {
           Route::middleware('api')->group($apiRoutePath);
       }
   }
   ```

3. `php artisan cache:clear`

See [CHANGELOG.md](CHANGELOG.md) for the full list of changes.

## Troubleshooting

| Problem | Fix |
|---|---|
| A module is enabled but its routes or provider are missing | Run `php artisan module:debug <Name>` and check `storage/logs/laravel.log` for "requires missing or disabled module". Every module in `dependents` must exist and be enabled. |
| `module_states` table not found | Run `php artisan migrate`. |
| `module:analyze` or `module:state` shows an old status | Run `php artisan cache:clear` (fixed in 2.0.1). |
| Changes in `module.json` are ignored | The `name` field must match the folder name exactly, and the file must be valid JSON. |

## Contributing

1. Fork <https://github.com/RCV-Technologies/laravel-module> and create a feature branch.
2. Follow PSR-12 and add tests for your change.
3. Add an entry under a new version in [CHANGELOG.md](CHANGELOG.md).
4. Open a pull request.

See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

Please report security issues privately to **support@rcvtechnologies.com** rather than opening a public issue.

## License

MIT — see [LICENSE.md](LICENSE.md).

Built by [RCV Technologies](https://rcvtechnologies.com).
