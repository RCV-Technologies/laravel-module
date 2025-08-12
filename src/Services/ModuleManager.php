<?php

namespace Rcv\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ModuleManager
{
    protected $modulePath;
    protected $cacheKey = 'module_states';
    protected $enabledModules = [];
    
    public function __construct()
    {
        $this->modulePath = base_path('Modules');
        $this->initialize();
    }
    
    public function initialize()
    {
        if (!File::isDirectory($this->modulePath)) {
            File::makeDirectory($this->modulePath, 0755, true);
        }
        
        $this->loadModuleStates();
    }
    
    protected function loadModuleStates()
    {
        $modules = $this->getAvailableModules();
        $states = [];
        
        foreach ($modules as $module) {
            $moduleJsonPath = "{$this->modulePath}/{$module}/module.json";
            if (File::exists($moduleJsonPath)) {
                $config = json_decode(File::get($moduleJsonPath), true);
                $states[$module] = [
                    'enabled' => isset($config['enabled']) ? (bool)$config['enabled'] : false,
                    'dependencies' => $config['dependencies'] ?? [],
                    'loaded' => false
                ];
            }
        }
        
        if ($this->isCacheAvailable()) {
            Cache::put($this->cacheKey, $states, 3600);
        }
        $this->enabledModules = $states;
    }
    
    /**
     * Get all available modules.
     *
     * @return array
     */
    public function getAvailableModules(): array
    {
        // Bypass cache for debugging
        $modules = $this->scanAvailableModules();
        return array_map(function($module) {
            return Str::studly($module);
        }, $modules);
    }
    
    /**
     * Scan for available modules.
     *
     * @return array
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

        return $modules;
    }
    
    /**
     * Get enabled modules.
     *
     * @return array
     */
    public function getEnabledModules(): array
    {
        // Bypass cache for debugging
        $modules = $this->scanEnabledModules();
        return array_map(function($module) {
            return Str::studly($module);
        }, $modules);
    }
    
    /**
     * Scan for enabled modules.
     *
     * @return array
     */
    protected function scanEnabledModules(): array
    {
        $modules = [];
        $availableModules = $this->getAvailableModules();

        foreach ($availableModules as $module) {
            if ($this->isModuleEnabled($module)) {
                $modules[] = $module;
            }
        }

        return $modules;
    }
    
    /**
     * Check if cache is available.
     *
     * @return bool
     */
    protected function isCacheAvailable(): bool
    {
        try {
            return app()->bound('cache') && app()->make('cache');
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if a module is enabled.
     *
     * @param string $moduleName
     * @return bool
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        $moduleState = $this->getModuleState($moduleName);
        return $moduleState['enabled'] ?? false;
    }
    
    /**
     * Get module state.
     *
     * @param string $moduleName
     * @return array
     */
    public function getModuleState(string $moduleName): array
    {
        $moduleJsonPath = base_path("Modules/{$moduleName}/module.json");
        
        if (File::exists($moduleJsonPath)) {
            $config = json_decode(File::get($moduleJsonPath), true);
            return [
                'enabled' => isset($config['enabled']) ? (bool)$config['enabled'] : false,
                'dependencies' => $config['dependencies'] ?? [],
                'loaded' => false
            ];
        }

        return [];
    }
    
    /**
     * Enable a module.
     *
     * @param string $moduleName
     * @return bool
     */
    public function enableModule(string $moduleName): bool
    {
        if (!$this->isValidModule($moduleName)) {
            return false;
        }

        $moduleState = $this->getModuleState($moduleName);
        $moduleState['enabled'] = true;
        $moduleState['last_enabled_at'] = now()->toIso8601String();

        $this->updateModuleState($moduleName, $moduleState);
        $this->clearModuleCache();

        return true;
    }
    
    /**
     * Disable a module.
     *
     * @param string $moduleName
     * @return bool
     */
    public function disableModule(string $moduleName): bool
    {
        if (!$this->isValidModule($moduleName)) {
            return false;
        }

        $moduleState = $this->getModuleState($moduleName);
        $moduleState['enabled'] = false;
        $moduleState['last_disabled_at'] = now()->toIso8601String();

        $this->updateModuleState($moduleName, $moduleState);
        $this->clearModuleCache();

        return true;
    }
    
    /**
     * Update module state.
     *
     * @param string $moduleName
     * @param array $state
     * @return void
     */
    protected function updateModuleState(string $moduleName, array $state): void
    {
        $moduleJsonPath = base_path("Modules/{$moduleName}/module.json");
        File::put($moduleJsonPath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * Check if a module is valid.
     *
     * @param string $moduleName
     * @return bool
     */
    protected function isValidModule(string $moduleName): bool
    {
        $modulePath = base_path("Modules/{$moduleName}");
        $moduleJsonPath = "{$modulePath}/module.json";
        $providerPath = "{$modulePath}/src/Providers/{$moduleName}ServiceProvider.php";

        return File::exists($modulePath) &&
               File::exists($moduleJsonPath) &&
               File::exists($providerPath);
    }
    
    /**
     * Clear module cache.
     *
     * @return void
     */
    protected function clearModuleCache(): void
    {
        if ($this->isCacheAvailable()) {
            Cache::forget('core.available_modules');
            Cache::forget('core.enabled_modules');
        }
    }
    
    public function loadModules()
    {
        $modules = $this->getAvailableModules();
        $loadedModules = [];
        
        foreach ($modules as $module) {
            try {
                if ($this->canLoadModule($module)) {
                    $this->loadModule($module);
                    $loadedModules[] = $module;
                }
            } catch (\Exception $e) {
                Log::error("Failed to load module {$module}: " . $e->getMessage());
            }
        }
        
        return $loadedModules;
    }
    
    protected function canLoadModule($moduleName)
    {
        if (!isset($this->enabledModules[$moduleName])) {
            return false;
        }
        
        $module = $this->enabledModules[$moduleName];
        
        if (!$module['enabled']) {
            return false;
        }
        
        foreach ($module['dependencies'] as $dependency) {
            if (!isset($this->enabledModules[$dependency]) || 
                !$this->enabledModules[$dependency]['enabled']) {
                return false;
            }
        }
        
        return true;
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
                if ($this->isCacheAvailable()) {
                    Cache::put($this->cacheKey, $this->enabledModules, 3600);
                }
                return true;
            } catch (\Exception $e) {
                Log::error("Failed to load provider {$providerClass} for module {$moduleName}: " . $e->getMessage());
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