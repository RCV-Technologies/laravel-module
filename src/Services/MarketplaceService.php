<?php

namespace Rcv\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Artisan;
use Rcv\Core\Models\ModuleState;
use Rcv\Core\Notifications\ModuleUpdateAvailable;
use Rcv\Core\Events\ModuleInstalled;
use Rcv\Core\Events\ModuleRemoved;
use Rcv\Core\Events\ModuleEnabled;
use Rcv\Core\Events\ModuleDisabled;
use Illuminate\Cache\CacheManager;
use Rcv\Core\Events\ModuleUninstalled;
use Rcv\Core\Services\ModuleRegistrationService;

class MarketplaceService
{
    protected $localPath;
    protected $cacheEnabled;
    protected $cacheTtl;
    protected $backupPath;
    protected $modulePath;
    protected $config;
    protected $cacheManager;
    protected $moduleRegistrationService;

    public function __construct(
        ?CacheManager $cacheManager = null,
        ModuleRegistrationService $moduleRegistrationService
    )
    {
        $this->localPath = Config::get('marketplace.local.path', base_path('Modules'));
        $this->cacheEnabled = Config::get('marketplace.cache.enabled', true);
        $this->cacheTtl = Config::get('marketplace.cache.ttl', 3600);
        $this->backupPath = Config::get('marketplace.modules.backup.path', storage_path('app/modules/backups'));
        $this->modulePath = base_path('Modules');
        $this->config = config('marketplace');
        $this->cacheManager = $cacheManager;
        $this->moduleRegistrationService = $moduleRegistrationService;
    }

    public function getAvailableModules()
    {
        // Temporarily bypass cache for debugging
        return $this->discoverModules();

        // Original cached version
        if ($this->cacheManager && $this->cacheEnabled) {
            return $this->cacheManager->remember('modules.list', $this->cacheTtl, function () {
                return $this->discoverModules();
            });
        }

        return $this->discoverModules();
    }

    public function getModuleDetails(string $name)
    {
        if ($this->cacheManager && $this->cacheEnabled) {
            return $this->cacheManager->remember($this->getModuleCacheKey($name), $this->cacheTtl, function () use ($name) {
                return $this->getLocalModuleDetails($name);
            });
        }

        return $this->getLocalModuleDetails($name);
    }

    protected function getModuleCacheKey(string $name): string
    {
        return 'module.' . strtolower($name);
    }

    public function installModule($name)
    {
        try {
            DB::beginTransaction();

            // Special handling for Core module
            if ($name === 'Core') {
                $moduleState = ModuleState::firstOrCreate(
                    ['name' => $name],
                    [
                        'version' => $this->getModuleVersion($name),
                        'status' => 'installed',
                        'enabled' => true,
                        'last_enabled_at' => now()
                    ]
                );
                $moduleState->status = 'installed';
                $moduleState->enabled = true;
                $moduleState->last_enabled_at = now();
                $moduleState->save();
                
                DB::commit();
                return true;
            }

            // 1. Check if module exists
            $modulePath = $this->modulePath . '/' . $name;
            if (!File::exists($modulePath)) {
                throw new \Exception("Module [{$name}] not found");
            }

            // 2. Install dependencies from composer.json
            $this->installDependencies($name);

            // 3. Register service provider
            $this->registerServiceProvider($name);

            // 4. Run migrations with status tracking
            $this->runMigrations($name);

            // 5. Publish assets and configs
            $this->publishAssets($name);

            // 6. Update module state
            $moduleState = ModuleState::firstOrCreate(
                ['name' => $name],
                [
                    'version' => $this->getModuleVersion($name),
                    'status' => 'installed',
                    'enabled' => false,
                    'applied_migrations' => [],
                    'failed_migrations' => []
                ]
            );
            $moduleState->status = 'installed';
            $moduleState->enabled = false;
            $moduleState->save();

            // 7. Clear caches
            if ($this->cacheManager) {
                $this->cacheManager->forget($this->getModuleCacheKey($name));
                $this->cacheManager->forget('modules.list');
            }

            // 8. Fire event
            Event::dispatch(new ModuleInstalled($name));

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function removeModule($name, $force = false)
    {
        try {
            DB::beginTransaction();

            // Check if module directory exists
            $modulePath = "{$this->modulePath}/{$name}";
            
            // Try to find module state, but don't fail if not found
            $moduleState = ModuleState::where('name', $name)->first();
            
            if ($moduleState) {
                // Remove from database
                $moduleState->delete();
            }

            // Remove module files if they exist
            if (File::exists($modulePath)) {
                File::deleteDirectory($modulePath);
            }

            // Clear caches
            if ($this->cacheManager) {
                $this->cacheManager->forget($this->getModuleCacheKey($name));
                $this->cacheManager->forget('modules.list');
            }

            DB::commit();

            event(new ModuleRemoved($name));

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Failed to remove module: " . $e->getMessage());
        }
    }

    public function enableModule($name)
    {
        try {
            DB::beginTransaction();

            // Special handling for Core module
            if ($name === 'Core') {
                $moduleState = ModuleState::firstOrCreate(
                    ['name' => $name],
                    [
                        'version' => $this->getModuleVersion($name),
                        'status' => 'installed',
                        'enabled' => true,
                        'last_enabled_at' => now()
                    ]
                );
                $moduleState->enabled = true;
                $moduleState->last_enabled_at = now();
                $moduleState->save();
                
                DB::commit();
                return true;
            }

            // 1. Check if module exists
            $modulePath = $this->modulePath . '/' . $name;
            if (!File::exists($modulePath)) {
                throw new \Exception("Module [{$name}] not found");
            }

            // 2. Check if module is installed
            $moduleState = ModuleState::where('name', $name)->first();
            if (!$moduleState) {
                // If module state doesn't exist, create it
                $moduleState = ModuleState::create([
                    'name' => $name,
                    'version' => $this->getModuleVersion($name),
                    'status' => 'installed',
                    'enabled' => false,
                    'applied_migrations' => [],
                    'failed_migrations' => []
                ]);
            }

            if ($moduleState->status !== 'installed') {
                throw new \Exception("Module [{$name}] is not installed");
            }

            // 3. Update module state
            $moduleState->enabled = true;
            $moduleState->last_enabled_at = now();
            $moduleState->save();

            // 4. Clear caches
            if ($this->cacheManager) {
                $this->cacheManager->forget($this->getModuleCacheKey($name));
                $this->cacheManager->forget('modules.list');
            }

            // 5. Fire event
            Event::dispatch(new ModuleEnabled($name));

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function disableModule($name)
    {
        try {
            DB::beginTransaction();

            // 1. Check if module exists
            $modulePath = $this->modulePath . '/' . $name;
            if (!File::exists($modulePath)) {
                throw new \Exception("Module [{$name}] not found");
            }

            // 2. Check if module is installed
            $moduleState = ModuleState::where('name', $name)->first();
            if (!$moduleState || $moduleState->status !== 'installed') {
                throw new \Exception("Module [{$name}] is not installed");
            }

            // 3. Update module state
            $moduleState->enabled = false;
            $moduleState->save();

            // 4. Clear caches
            if ($this->cacheManager) {
                $this->cacheManager->forget($this->getModuleCacheKey($name));
                $this->cacheManager->forget('modules.list');
            }

            // 5. Fire event
            Event::dispatch(new ModuleDisabled($name));

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function backupModule(string $name): void
    {
        $modulePath = "{$this->modulePath}/{$name}";
        $backupPath = storage_path("app/modules/backups/{$name}_" . date('Y_m_d_His'));

        if (!File::exists(dirname($backupPath))) {
            File::makeDirectory(dirname($backupPath), 0755, true);
        }

        File::copyDirectory($modulePath, $backupPath);
    }

    protected function checkDependencies($name)
    {
        $modules = $this->getAvailableModules();
        foreach ($modules as $module) {
            if ($module['name'] !== $name && $module['status'] === 'enabled') {
                $composerFile = "{$this->localPath}/{$module['name']}/composer.json";
                if (File::exists($composerFile)) {
                    $composer = json_decode(File::get($composerFile), true);
                    if (isset($composer['require']["modules/{$name}"])) {
                        throw new \Exception("Cannot remove module [{$name}] as it is required by [{$module['name']}]");
                    }
                }
            }
        }
    }

    public function checkForUpdates()
    {
        $modules = $this->getAvailableModules();
        $updates = [];
        
        foreach ($modules as $module) {
            $moduleState = ModuleState::where('name', $module['name'])->first();
            if ($moduleState && version_compare($moduleState->version, $module['version'], '<')) {
                $updates[] = array_merge($module, [
                    'current_version' => $moduleState->version
                ]);
            }
        }
        
        if (!empty($updates) && Config::get('marketplace.notifications.enabled')) {
            $this->sendUpdateNotifications($updates);
        }
        
        return $updates;
    }

    protected function discoverModules()
    {
        $modules = [];
        
        if (File::exists($this->modulePath)) {
            $directories = File::directories($this->modulePath);
            
            foreach ($directories as $directory) {
                $moduleName = basename($directory);
                $composerJson = "{$directory}/composer.json";
                $moduleState = ModuleState::where('name', $moduleName)->first();
                
                if (File::exists($composerJson)) {
                    $moduleData = json_decode(File::get($composerJson), true);
                } else {
                    $moduleData = [];
                }
                
                $modules[] = [
                    'name' => $moduleName,
                    'version' => $moduleData['version'] ?? '1.0.0',
                    'description' => $moduleData['description'] ?? $moduleName . ' module for the application',
                    'status' => $moduleState && $moduleState->enabled ? 'enabled' : 'disabled'
                ];
            }
        }
        
        return $modules;
    }

    protected function getLocalModuleDetails(string $name)
    {
        $modulePath = "{$this->localPath}/{$name}";
        $composerJson = "{$modulePath}/composer.json";
        
        if (!File::exists($composerJson)) {
            throw new \Exception("Module [{$name}] not found");
        }
        
        $moduleData = json_decode(File::get($composerJson), true);
        $moduleState = ModuleState::where('name', $name)->first();
        
        return [
            'name' => $name,
            'version' => $moduleData['version'] ?? '1.0.0',
            'description' => $moduleData['description'] ?? '',
            'status' => $moduleState ? $moduleState->status : 'disabled',
            'dependencies' => $moduleData['require'] ?? [],
            'authors' => $moduleData['authors'] ?? [],
            'license' => $moduleData['license'] ?? 'MIT',
            'last_enabled' => $moduleState ? $moduleState->last_enabled_at : null,
            'last_disabled' => $moduleState ? $moduleState->last_disabled_at : null,
            'applied_migrations' => $moduleState ? $moduleState->applied_migrations : [],
            'failed_migrations' => $moduleState ? $moduleState->failed_migrations : []
        ];
    }

    protected function sendUpdateNotifications(array $updates)
    {
        $recipients = Config::get('marketplace.notifications.recipients', []);
        
        foreach ($recipients as $recipient) {
            Notification::route('mail', $recipient)
                ->notify(new ModuleUpdateAvailable($updates));
        }
    }

    protected function installDependencies($name)
    {
        $composerFile = "{$this->modulePath}/{$name}/composer.json";
        if (File::exists($composerFile)) {
            $composer = json_decode(File::get($composerFile), true);
            if (isset($composer['require'])) {
                foreach ($composer['require'] as $package => $version) {
                    if (strpos($package, 'modules/') === 0) {
                        $moduleName = str_replace('modules/', '', $package);
                        if (!$this->isModuleEnabled($moduleName)) {
                            $this->installModule($moduleName);
                        }
                    }
                }
            }
        }
    }

    protected function publishAssets($name)
    {
        // Publish assets
        Artisan::call('vendor:publish', [
            '--tag' => ["{$name}-assets", "{$name}-config"],
            '--force' => true
        ]);
    }

    protected function unpublishAssets($name)
    {
        // Remove published assets
        $publicPath = public_path("modules/{$name}");
        if (File::exists($publicPath)) {
            File::deleteDirectory($publicPath);
        }

        // Remove published configs
        $configPath = config_path(strtolower($name) . '.php');
        if (File::exists($configPath)) {
            File::delete($configPath);
        }
    }

    protected function rollbackMigrations($name)
    {
        Artisan::call('migrate:rollback', [
            '--path' => "packages/modules/{$name}/src/Database/Migrations",
            '--force' => true
        ]);
    }

    protected function clearCaches()
    {
        // Clear Laravel caches
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Clear module caches
        $this->cacheManager->invalidateAll();

        // Regenerate autoload files
        $this->regenerateAutoload();
    }

    public function isModuleEnabled($moduleName)
    {
        try {
            $moduleState = DB::table('modules')->where('name', $moduleName)->first();
            return $moduleState && $moduleState->is_enabled;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function registerServiceProvider($name)
    {
        $configPath = config_path('app.php');
        $config = file_get_contents($configPath);
        
        $providerClass = "Modules\\\\{$name}\\\\Providers\\\\{$name}ServiceProvider::class";
        
        // Check if provider already exists
        if (!str_contains($config, $providerClass)) {
            // Find the last module service provider
            $lastProvider = "Modules\\\\";
            $lines = explode("\n", $config);
            $insertAt = null;
            
            foreach ($lines as $i => $line) {
                if (str_contains($line, $lastProvider)) {
                    $insertAt = $i;
                }
            }
            
            if ($insertAt !== null) {
                array_splice($lines, $insertAt + 1, 0, "        {$providerClass},");
                file_put_contents($configPath, implode("\n", $lines));
            }
        }
    }

    protected function removeFromConfig($name)
    {
        $configPath = config_path('app.php');
        $config = file_get_contents($configPath);
        
        // Remove service provider with better pattern matching
        $providerClass = preg_quote("Modules\\{$name}\\Providers\\{$name}ServiceProvider::class", '/');
        $config = preg_replace("/^\s*{$providerClass},?\r?\n?/m", '', $config);
        
        file_put_contents($configPath, $config);
    }

    protected function removeFromComposer($name)
    {
        $composerPath = base_path('composer.json');
        $composer = json_decode(file_get_contents($composerPath), true);
        
        // Remove from autoload
        if (isset($composer['autoload']['psr-4']["Modules\\{$name}\\"])) {
            unset($composer['autoload']['psr-4']["Modules\\{$name}\\"]);
        }
        
        // Remove from require if exists
        if (isset($composer['require']["modules/{$name}"])) {
            unset($composer['require']["modules/{$name}"]);
        }
        
        file_put_contents($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function cleanupDatabase($name)
    {
        // Remove module state
        DB::table('module_states')->where('name', $name)->delete();
        
        // Remove module migrations
        $migrations = DB::table('migrations')
            ->where('migration', 'like', "%_{$name}_%")
            ->get();
            
        foreach ($migrations as $migration) {
            DB::table('migrations')->where('id', $migration->id)->delete();
        }
    }

    protected function regenerateAutoload()
    {
        shell_exec('composer dump-autoload');
    }

    protected function runMigrations($name)
    {
        $migrationPath = "packages/modules/{$name}/src/Database/Migrations";
        $moduleState = ModuleState::firstOrCreate(
            ['name' => $name],
            ['version' => $this->getModuleVersion($name), 'status' => 'installed']
        );

        // Get list of migrations
        $migrations = File::glob("{$this->modulePath}/{$name}/src/Database/Migrations/*.php");
        $appliedMigrations = json_decode($moduleState->applied_migrations ?? '[]', true);
        $failedMigrations = json_decode($moduleState->failed_migrations ?? '[]', true);

        foreach ($migrations as $migration) {
            $migrationName = basename($migration, '.php');
            
            // Skip if already applied successfully
            if (in_array($migrationName, $appliedMigrations)) {
                continue;
            }

            try {
                Artisan::call('migrate', [
                    '--path' => $migrationPath,
                    '--force' => true
                ]);

                $appliedMigrations[] = $migrationName;
                $failedMigrations = array_diff($failedMigrations, [$migrationName]);
            } catch (\Exception $e) {
                $failedMigrations[] = $migrationName;
                throw $e;
            }
        }

        // Update module state
        $moduleState->applied_migrations = json_encode($appliedMigrations);
        $moduleState->failed_migrations = json_encode($failedMigrations);
        $moduleState->save();
    }

    protected function getModuleVersion($name)
    {
        $composerFile = "{$this->modulePath}/{$name}/composer.json";
        if (File::exists($composerFile)) {
            $composer = json_decode(File::get($composerFile), true);
            return $composer['version'] ?? '1.0.0';
        }
        return '1.0.0';
    }

    public function uninstallModule($name)
    {
        try {
            DB::beginTransaction();

            // 1. Check if module exists
            $modulePath = $this->modulePath . '/' . $name;
            if (!File::exists($modulePath)) {
                throw new \Exception("Module [{$name}] not found");
            }

            // 2. Check if module is installed
            $moduleState = ModuleState::where('name', $name)->first();
            if (!$moduleState || $moduleState->status !== 'installed') {
                throw new \Exception("Module [{$name}] is not installed");
            }

            // 3. Run uninstall migrations
            $this->runUninstallMigrations($name);

            // 4. Unregister service provider
            $this->unregisterServiceProvider($name);

            // 5. Update module state
            $moduleState->status = 'uninstalled';
            $moduleState->save();

            // 6. Clear caches
            if ($this->cacheManager) {
                $this->cacheManager->forget($this->getModuleCacheKey($name));
                $this->cacheManager->forget('modules.list');
            }

            // 7. Fire event
            Event::dispatch(new ModuleUninstalled($name));

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Install a module
     */
    public function install(string $moduleName): bool
    {
        try {
            // Register module in app.php
            if (!$this->moduleRegistrationService->registerModule($moduleName)) {
                throw new \Exception("Failed to register module in app.php");
            }

            // Run module migrations
            Artisan::call('module:migrate', ['module' => $moduleName]);

            // Run module seeders if they exist
            if (File::exists(base_path("packages/modules/{$moduleName}/src/Database/Seeders"))) {
                Artisan::call('module:seed', ['module' => $moduleName]);
            }

            // Clear cache
            $this->clearCache();

            return true;
        } catch (\Exception $e) {
            // Rollback registration if installation fails
            $this->moduleRegistrationService->unregisterModule($moduleName);
            throw $e;
        }
    }

    /**
     * Uninstall a module
     */
    public function uninstall(string $moduleName): bool
    {
        try {
            // Unregister module from app.php
            if (!$this->moduleRegistrationService->unregisterModule($moduleName)) {
                throw new \Exception("Failed to unregister module from app.php");
            }

            // Run module migrations rollback
            Artisan::call('module:migrate-rollback', ['module' => $moduleName]);

            // Clear cache
            $this->clearCache();

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Enable a module
     */
    public function enable(string $moduleName): bool
    {
        try {
            // Register module in app.php if not already registered
            if (!$this->moduleRegistrationService->registerModule($moduleName)) {
                throw new \Exception("Failed to register module in app.php");
            }

            // Clear cache
            $this->clearCache();

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Disable a module
     */
    public function disable(string $moduleName): bool
    {
        try {
            // Unregister module from app.php
            if (!$this->moduleRegistrationService->unregisterModule($moduleName)) {
                throw new \Exception("Failed to unregister module from app.php");
            }

            // Clear cache
            $this->clearCache();

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * List all modules
     */
    public function list(): array
    {
        return $this->moduleRegistrationService->getRegisteredModules();
    }

    /**
     * Clear all caches
     */
    protected function clearCache(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
    }

    public function cleanupComposerJson($moduleName)
    {
        $composerJsonPath = base_path('composer.json');
        if (!File::exists($composerJsonPath)) {
            return;
        }

        $composerJson = json_decode(File::get($composerJsonPath), true);
        
        // Remove from require section (case-insensitive)
        if (isset($composerJson['require'])) {
            $moduleKey = strtolower("modules/{$moduleName}");
            foreach ($composerJson['require'] as $key => $value) {
                if (strtolower($key) === $moduleKey) {
                    unset($composerJson['require'][$key]);
                }
            }
        }

        // Remove from autoload section (case-insensitive)
        if (isset($composerJson['autoload']['psr-4'])) {
            $moduleNamespace = "Modules\\{$moduleName}\\";
            foreach ($composerJson['autoload']['psr-4'] as $namespace => $path) {
                if (strcasecmp($namespace, $moduleNamespace) === 0) {
                    unset($composerJson['autoload']['psr-4'][$namespace]);
                }
            }
        }

        // Write back to composer.json
        File::put(
            $composerJsonPath,
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Run composer dump-autoload
        exec('composer dump-autoload');
    }
} 