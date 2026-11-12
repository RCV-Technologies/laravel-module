<?php

namespace RCV\Core\Services;

use Exception;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RCV\Core\Events\ModuleDisabled;
use RCV\Core\Events\ModuleEnabled;
use RCV\Core\Events\ModuleInstalled;
use RCV\Core\Events\ModuleRemoved;
use RCV\Core\Events\ModuleUninstalled;
use RCV\Core\Models\ModuleState;
use RCV\Core\Notifications\ModuleUpdateAvailable;

class MarketplaceService
{
    protected string $modulePath;

    protected string $backupPath;

    protected string $localPath;

    protected bool $cacheEnabled;

    protected int $cacheTtl;

    protected ?CacheManager $cacheManager;

    protected ModuleRegistrationService $moduleRegistrationService;

    /** @var array<string, true> */
    protected array $installingModules = [];

    public function __construct(
        ?CacheManager $cacheManager,
        ModuleRegistrationService $moduleRegistrationService
    ) {
        $this->localPath = Config::get('marketplace.local.path', base_path('Modules'));
        $this->cacheEnabled = Config::get('marketplace.cache.enabled', true);
        $this->cacheTtl = Config::get('marketplace.cache.ttl', 3600);
        $this->backupPath = Config::get('marketplace.modules.backup.path', storage_path('app/Modules/backups'));
        $this->modulePath = base_path('Modules');
        $this->cacheManager = $cacheManager;
        $this->moduleRegistrationService = $moduleRegistrationService;
    }

    /* -----------------------------------------------------------------
     |  Public API
     | -----------------------------------------------------------------
     */

    public function list(): array
    {
        return $this->cacheEnabled && $this->cacheManager
            ? $this->cacheManager->remember('modules.list', $this->cacheTtl, fn () => $this->discoverModules())
            : $this->discoverModules();
    }

    public function details(string $name): array
    {
        $this->validateModuleName($name);

        return $this->cacheEnabled && $this->cacheManager
            ? $this->cacheManager->remember($this->cacheKey($name), $this->cacheTtl, fn () => $this->getLocalDetails($name))
            : $this->getLocalDetails($name);
    }

    public function install(string $name): bool
    {
        $this->validateModuleName($name);

        if (isset($this->installingModules[$name])) {
            throw new InvalidArgumentException("Circular module dependency detected while installing [{$name}].");
        }

        $this->installingModules[$name] = true;

        try {
            $this->assertModuleExists($name);
            $this->installDependencies($name);
            $this->moduleRegistrationService->registerModule($name);
            $this->runMigrations($name);
            $this->publishAssets($name);

            DB::transaction(fn () => $this->updateState($name, 'installed', false));
            $this->clearModuleCache($name);
            Event::dispatch(new ModuleInstalled($name));

            return true;
        } finally {
            unset($this->installingModules[$name]);
        }
    }

    public function uninstall(string $name): bool
    {
        $this->validateModuleName($name);

        $this->assertModuleInstalled($name);
        $this->runUninstallMigrations($name);
        $this->moduleRegistrationService->unregisterModule($name);
        DB::transaction(fn () => $this->updateState($name, 'uninstalled', false));
        $this->clearModuleCache($name);
        Event::dispatch(new ModuleUninstalled($name));

        return true;
    }

    public function remove(string $name, bool $force = false): bool
    {
        $this->validateModuleName($name);

        if (! $force) {
            $this->checkDependencies($name);
        }

        $modulePath = "{$this->modulePath}/{$name}";
        $stagedPath = "{$this->modulePath}/.{$name}.removing.".Str::uuid();
        $moduleExists = File::exists($modulePath);
        $moduleWasStaged = ! $moduleExists || rename($modulePath, $stagedPath);
        if (! $moduleWasStaged) {
            throw new Exception("Unable to stage module files for removal: {$name}.");
        }

        try {
            DB::transaction(fn () => ModuleState::where('name', $name)->delete());
        } catch (\Throwable $exception) {
            if ($moduleWasStaged) {
                rename($stagedPath, $modulePath);
            }

            throw $exception;
        }

        if ($moduleWasStaged && ! File::deleteDirectory($stagedPath)) {
            throw new Exception("Module files were detached but could not be removed from [{$stagedPath}].");
        }

        $this->clearModuleCache($name);
        Event::dispatch(new ModuleRemoved($name));

        return true;
    }

    protected function validateModuleName(string $name): void
    {
        if (! ModuleNameValidator::isValid($name)) {
            throw new InvalidArgumentException("Invalid module name [{$name}].");
        }
    }

    public function enable(string $name): bool
    {
        $this->validateModuleName($name);

        $this->assertModuleInstalled($name);
        $this->moduleRegistrationService->registerModule($name);
        DB::transaction(fn () => $this->updateState($name, 'installed', true));
        $this->clearModuleCache($name);
        Event::dispatch(new ModuleEnabled($name));
        app(ModuleManager::class)->refreshEnabledModulesCache();

        return true;
    }

    public function disable(string $name): bool
    {
        $this->validateModuleName($name);

        $this->assertModuleInstalled($name);
        $this->moduleRegistrationService->unregisterModule($name);
        DB::transaction(fn () => $this->updateState($name, 'installed', false));
        $this->clearModuleCache($name);
        Event::dispatch(new ModuleDisabled($name));
        app(ModuleManager::class)->refreshEnabledModulesCache();

        return true;
    }

    public function checkForUpdates(): array
    {
        $updates = [];
        foreach ($this->list() as $module) {
            $state = ModuleState::where('name', $module['name'])->first();
            if ($state && version_compare($state->version, $module['version'], '<')) {
                $updates[] = [...$module, 'current_version' => $state->version];
            }
        }

        if (! empty($updates) && Config::get('marketplace.notifications.enabled')) {
            $this->sendUpdateNotifications($updates);
        }

        return $updates;
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | -----------------------------------------------------------------
     */

    protected function cacheKey(string $name): string
    {
        return 'module.'.strtolower($name);
    }

    protected function discoverModules(): array
    {
        $modules = [];
        if (! File::exists($this->modulePath)) {
            return [];
        }

        foreach (File::directories($this->modulePath) as $directory) {
            $name = basename($directory);
            $composer = "{$directory}/composer.json";

            $data = File::exists($composer) ? json_decode(File::get($composer), true) : [];
            $state = ModuleState::where('name', $name)->first();

            $modules[] = [
                'name' => $name,
                'version' => $data['version'] ?? '1.0.0',
                'description' => $data['description'] ?? "{$name} module",
                'status' => $state && $state->enabled ? 'enabled' : 'disabled',
            ];
        }

        return $modules;
    }

    public function getModuleDetails(string $name): ?array
    {
        // Try local DB states
        $moduleState = DB::table('module_states')->where('name', $name)->first();
        if ($moduleState) {
            return [
                'name' => $moduleState->name,
                'version' => $moduleState->version,
                'status' => $moduleState->enabled ? 'enabled' : 'disabled',
                'description' => $moduleState->description ?? '',
            ];
        }

        // If not found locally, just return null
        return null;
    }

    protected function getLocalDetails(string $name): array
    {
        $composerFile = "{$this->localPath}/{$name}/composer.json";
        if (! File::exists($composerFile)) {
            throw new Exception("Module [{$name}] not found");
        }

        $data = json_decode(File::get($composerFile), true);
        $state = ModuleState::where('name', $name)->first();

        return [
            'name' => $name,
            'version' => $data['version'] ?? '1.0.0',
            'description' => $data['description'] ?? '',
            'status' => $state?->status ?? 'disabled',
            'dependencies' => $data['require'] ?? [],
            'authors' => $data['authors'] ?? [],
            'license' => $data['license'] ?? 'MIT',
            'last_enabled' => $state?->last_enabled_at,
            'last_disabled' => $state?->last_disabled_at,
            'applied_migrations' => $state?->applied_migrations ?? [],
            'failed_migrations' => $state?->failed_migrations ?? [],
        ];
    }

    protected function updateState(string $name, string $status, bool $enabled): void
    {
        $state = ModuleState::firstOrCreate(['name' => $name], [
            'version' => $this->getModuleVersion($name),
            'status' => $status,
        ]);

        $state->status = $status;
        $state->enabled = $enabled;
        if ($enabled) {
            $state->last_enabled_at = now();
        } else {
            $state->last_disabled_at = now();
        }
        $state->save();
    }

    protected function getModuleVersion(string $name): string
    {
        $composer = "{$this->modulePath}/{$name}/composer.json";

        return File::exists($composer)
            ? (json_decode(File::get($composer), true)['version'] ?? '1.0.0')
            : '1.0.0';
    }

    protected function installDependencies(string $name): void
    {
        $composer = "{$this->modulePath}/{$name}/composer.json";
        if (! File::exists($composer)) {
            return;
        }

        $data = json_decode(File::get($composer), true);
        foreach ($data['require'] ?? [] as $package => $version) {
            if (str_starts_with($package, 'Modules/')) {
                $dep = str_replace('Modules/', '', $package);
                $this->validateModuleName($dep);
                if (isset($this->installingModules[$dep])) {
                    throw new InvalidArgumentException("Circular module dependency detected between [{$name}] and [{$dep}].");
                }

                if (! $this->isModuleInstalled($dep)) {
                    $this->install($dep);
                }
            }
        }
    }

    protected function publishAssets(string $name): void
    {
        Artisan::call('vendor:publish', [
            '--tag' => ["{$name}-assets", "{$name}-config"],
            '--force' => true,
        ]);
    }

    protected function runMigrations(string $name): void
    {
        Artisan::call('migrate', [
            '--path' => "Modules/{$name}/src/Database/Migrations",
            '--force' => true,
        ]);
    }

    protected function runUninstallMigrations(string $name): void
    {
        Artisan::call('migrate:rollback', [
            '--path' => "Modules/{$name}/src/Database/Migrations",
            '--force' => true,
        ]);
    }

    protected function sendUpdateNotifications(array $updates): void
    {
        foreach (Config::get('marketplace.notifications.recipients', []) as $recipient) {
            Notification::route('mail', $recipient)->notify(new ModuleUpdateAvailable($updates));
        }
    }

    protected function isModuleInstalled(string $name): bool
    {
        return ModuleState::where('name', $name)->where('status', 'installed')->exists();
    }

    protected function clearModuleCache(string $name): void
    {
        if ($this->cacheManager) {
            $this->cacheManager->forget($this->cacheKey($name));
            $this->cacheManager->forget('modules.list');
        }
    }

    protected function assertModuleExists(string $name): void
    {
        if (! File::exists("{$this->modulePath}/{$name}")) {
            throw new Exception("Module [{$name}] not found");
        }
    }

    protected function assertModuleInstalled(string $name): void
    {
        $state = ModuleState::where('name', $name)->first();
        if (! $state || $state->status !== 'installed') {
            throw new Exception("Module [{$name}] is not installed");
        }
    }

    /**
     * Check dependencies before installing/removing a module.
     *
     * @throws Exception
     */
    public function checkDependencies(string $name): void
    {
        foreach (ModuleState::query()->where('status', 'installed')->pluck('name') as $dependentName) {
            if ($dependentName === $name || ! ModuleNameValidator::isValid($dependentName)) {
                continue;
            }

            $composerFile = module_path($dependentName, 'composer.json');
            if (! File::exists($composerFile)) {
                continue;
            }

            $composer = json_decode(File::get($composerFile), true);
            $dependencies = is_array($composer) ? ($composer['require'] ?? []) : [];
            if (is_array($dependencies) && array_key_exists("Modules/{$name}", $dependencies)) {
                throw new Exception("Module [{$dependentName}] depends on [{$name}] and must be disabled or removed first.");
            }
        }
    }
}
