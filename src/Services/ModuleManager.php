<?php

namespace RCV\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RCV\Core\Models\ModuleState;

class ModuleManager
{
    protected $modulePath;

    protected string $cacheKey = 'rcv_enabled_modules';

    public function getEnabledModulesCacheKey(): string
    {
        return hash('sha256', $this->cacheKey);
    }

    protected $enabledModules = [];

    /** @var array<string, array|null> */
    protected array $moduleManifests = [];

    protected ?array $availableModulesCache = null;

    public function __construct()
    {
        $this->modulePath = base_path('Modules');
        $this->initialize();
    }

    public function initialize(): void
    {
        if (! File::isDirectory($this->modulePath)) {
            File::makeDirectory($this->modulePath, 0755, true);
        }

        $this->loadModuleStates();
    }

    protected function loadModuleStates(?array $enabledModuleNames = null): void
    {
        $modules = $this->getAvailableModules();
        $states = [];
        $enabledModuleNames ??= $this->getEnabledModuleNames();
        $enabledModules = array_fill_keys($enabledModuleNames, true);

        foreach ($modules as $module) {
            $config = $this->getModuleManifest($module);
            if ($config === null) {
                continue;
            }

            $states[$module] = [
                'enabled' => isset($enabledModules[$module]),
                'dependents' => is_array($config) ? ($config['dependents'] ?? []) : [],
                'loaded' => false,
            ];
        }

        $this->enabledModules = $states;
    }

    /**
     * Get all available modules.
     */
    public function getAvailableModules(): array
    {
        if ($this->availableModulesCache !== null) {
            return $this->availableModulesCache;
        }

        return $this->scanAvailableModules();
    }

    /**
     * Scan for available modules.
     */
    protected function scanAvailableModules(): array
    {
        $modules = [];
        $modulesPath = base_path('Modules');

        if (File::exists($modulesPath)) {
            $directories = File::directories($modulesPath);
            foreach ($directories as $directory) {
                $moduleName = basename($directory);
                if ($this->isValidModule($moduleName)) {
                    $modules[] = $moduleName;
                }
            }
        }

        return $this->availableModulesCache = array_values(array_unique($modules));
    }

    /**
     * Get enabled modules.
     */
    public function getEnabledModules(): array
    {
        $enabledModules = array_fill_keys($this->getEnabledModuleNames(), true);

        return array_values(array_filter(
            $this->getAvailableModules(),
            static fn (string $moduleName): bool => isset($enabledModules[$moduleName])
        ));
    }

    /**
     * Get enabled module names from cache, loading them from the database on a cache miss.
     *
     * @return array<int, string>
     */
    protected function getEnabledModuleNames(): array
    {
        if (app()->bound('cache')) {
            try {
                $enabledModuleNames = Cache::rememberForever(
                    $this->getEnabledModulesCacheKey(),
                    fn (): array => DB::table('module_states')->where('enabled', true)->pluck('name')->all()
                );

                if (is_array($enabledModuleNames)) {
                    return array_values(array_filter($enabledModuleNames, 'is_string'));
                }
            } catch (\Throwable $exception) {
                Log::debug('Unable to load enabled modules from cache.', ['exception' => $exception]);
            }
        }

        try {
            return DB::table('module_states')->where('enabled', true)->pluck('name')->all();
        } catch (\Throwable $exception) {
            Log::debug('Unable to load enabled modules from the database.', ['exception' => $exception]);

            return [];
        }
    }

    /**
     * Scan for enabled modules.
     */
    protected function scanEnabledModules(): array
    {
        return $this->getEnabledModules();
    }

    /**
     * Check if cache is available.
     */
    /**
     * Check if a module is enabled.
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        if (! ModuleNameValidator::isValid($moduleName)) {
            return false;
        }

        return in_array($moduleName, $this->getEnabledModuleNames(), true);
    }

    /**
     * Get module state.
     */
    public function getModuleState(string $moduleName): array
    {
        if (! ModuleNameValidator::isValid($moduleName)) {
            return [];
        }

        try {
            $state = DB::table('module_states')->where('name', $moduleName)->first();
        } catch (\Throwable $exception) {
            Log::debug("Database check failed for module {$moduleName}.", ['exception' => $exception]);

            return [];
        }

        if (! $state) {
            return [];
        }

        $config = $this->getModuleManifest($moduleName) ?? [];

        return [
            ...((array) $state),
            'enabled' => (bool) $state->enabled,
            'dependents' => is_array($config) ? ($config['dependents'] ?? []) : [],
            'loaded' => false,
        ];
    }

    /**
     * Enable a module.
     */
    public function enableModule(string $moduleName): bool
    {
        if (! $this->isValidModule($moduleName)) {
            return false;
        }

        $this->persistModuleState($moduleName, true);
        $this->refreshEnabledModulesCache();

        return true;
    }

    /**
     * Disable a module.
     */
    public function disableModule(string $moduleName): bool
    {
        if (! $this->isValidModule($moduleName)) {
            return false;
        }

        $this->persistModuleState($moduleName, false);
        $this->refreshEnabledModulesCache();

        return true;
    }

    /**
     * Update module state.
     *
     * @param  array  $state
     */
    protected function persistModuleState(string $moduleName, bool $enabled): void
    {
        $moduleMetadata = $this->getModuleManifest($moduleName) ?? [];
        $state = ModuleState::firstOrNew(['name' => $moduleName]);
        $state->version = is_array($moduleMetadata) ? ($moduleMetadata['version'] ?? $state->version ?? '1.0.0') : '1.0.0';
        $state->description = is_array($moduleMetadata)
            ? ($moduleMetadata['description'] ?? "{$moduleName} module")
            : "{$moduleName} module";
        $state->enabled = $enabled;
        $state->status = $enabled ? 'enabled' : 'disabled';
        $state->{$enabled ? 'last_enabled_at' : 'last_disabled_at'} = now();
        $state->save();
    }

    /**
     * Check if a module is valid.
     */
    protected function isValidModule(string $moduleName): bool
    {
        if (! ModuleNameValidator::isValid($moduleName)) {
            return false;
        }

        $modulePath = module_path($moduleName);
        $providerPath = "{$modulePath}/src/Providers/{$moduleName}ServiceProvider.php";

        return File::exists($modulePath) &&
               $this->getModuleManifest($moduleName) !== null &&
               File::exists($providerPath);
    }

    protected function getModuleManifest(string $moduleName): ?array
    {
        if (array_key_exists($moduleName, $this->moduleManifests)) {
            return $this->moduleManifests[$moduleName];
        }

        try {
            return $this->moduleManifests[$moduleName] = ModuleManifestValidator::read(
                $moduleName,
                module_path($moduleName, 'module.json')
            );
        } catch (\InvalidArgumentException $exception) {
            $this->moduleManifests[$moduleName] = null;
            Log::warning($exception->getMessage());

            return null;
        }
    }

    /**
     * Clear module cache.
     */
    public function refreshEnabledModulesCache(): void
    {
        try {
            $this->availableModulesCache = null;
            $this->moduleManifests = [];
            $enabledModuleNames = DB::table('module_states')->where('enabled', true)->pluck('name')->all();

            if (app()->bound('cache')) {
                try {
                    Cache::forever($this->getEnabledModulesCacheKey(), $enabledModuleNames);
                } catch (\Throwable $exception) {
                    Log::debug('Unable to write enabled modules to cache.', ['exception' => $exception]);
                }
            }

            $this->loadModuleStates($enabledModuleNames);
        } catch (\Throwable $exception) {
            Log::warning('Unable to refresh enabled module cache.', ['exception' => $exception]);
        }
    }

    public function loadModules()
    {
        $loadedModules = [];

        foreach ($this->getLoadableEnabledModules() as $module) {
            try {
                if ($this->loadModule($module)) {
                    $loadedModules[] = $module;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to load module {$module}: ".$e->getMessage());
            }
        }

        return $loadedModules;
    }

    /**
     * Return enabled modules in dependency order, skipping modules with invalid graphs.
     *
     * @return array<int, string>
     */
    public function getLoadableEnabledModules(): array
    {
        $enabledModules = array_keys(array_filter(
            $this->enabledModules,
            fn (array $module): bool => $module['enabled']
        ));
        $status = [];
        $stack = [];
        $loadOrder = [];

        $visit = function (string $moduleName) use (&$visit, &$status, &$stack, &$loadOrder): bool {
            if (($status[$moduleName] ?? null) === 'loaded') {
                return true;
            }

            if (($status[$moduleName] ?? null) === 'invalid') {
                return false;
            }

            if (($status[$moduleName] ?? null) === 'visiting') {
                $cycleStart = array_search($moduleName, $stack, true);
                $cycle = array_slice($stack, $cycleStart === false ? 0 : $cycleStart);
                $cycle[] = $moduleName;
                foreach ($cycle as $cycleModule) {
                    $status[$cycleModule] = 'invalid';
                }
                Log::error('Circular module dependency detected: '.implode(' -> ', $cycle));

                return false;
            }

            $status[$moduleName] = 'visiting';
            $stack[] = $moduleName;
            $module = $this->enabledModules[$moduleName];

            foreach ($module['dependents'] as $dependency) {
                if (! isset($this->enabledModules[$dependency])) {
                    Log::warning("Module [{$moduleName}] requires missing or invalid module [{$dependency}].");
                    $status[$moduleName] = 'invalid';
                    array_pop($stack);

                    return false;
                }

                if (! $this->enabledModules[$dependency]['enabled']) {
                    Log::warning("Module [{$moduleName}] requires disabled module [{$dependency}].");
                    $status[$moduleName] = 'invalid';
                    array_pop($stack);

                    return false;
                }

                if (! $visit($dependency)) {
                    $status[$moduleName] = 'invalid';
                    array_pop($stack);

                    return false;
                }
            }

            array_pop($stack);
            if (($status[$moduleName] ?? null) === 'invalid') {
                return false;
            }

            $status[$moduleName] = 'loaded';
            $loadOrder[] = $moduleName;

            return true;
        };

        foreach ($enabledModules as $moduleName) {
            $visit($moduleName);
        }

        return $loadOrder;
    }

    protected function loadModule($moduleName)
    {
        $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";

        if (class_exists($providerClass)) {
            try {
                // Let Laravel's service container handle the instantiation
                $instance = app()->resolveProvider($providerClass);
                app()->register($instance);
                $this->enabledModules[$moduleName]['loaded'] = true;

                return true;
            } catch (\Exception $e) {
                Log::error("Failed to load provider {$providerClass} for module {$moduleName}: ".$e->getMessage());
                throw $e;
            }
        }

        return false;
    }

    public function getAllModuleStates()
    {
        return $this->enabledModules;
    }
}
