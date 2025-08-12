<?php

namespace Rcv\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModuleLoader
{
    protected $modulePath;
    protected $cacheKey = 'module_states';
    protected $loadedModules = [];
    
    public function __construct()
    {
        $this->modulePath = base_path('Modules');
    }
    
    public function loadModules()
    {
        if (!File::isDirectory($this->modulePath)) {
            return [];
        }
        
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
    
    protected function getAvailableModules()
    {
        return array_map(function($path) {
            return basename($path);
        }, File::directories($this->modulePath));
    }
    
    protected function canLoadModule($moduleName)
    {
        $configPath = "{$this->modulePath}/{$moduleName}/config/config.php";
        if (!File::exists($configPath)) {
            return false;
        }
        
        $config = require $configPath;
        return $config['enabled'] ?? false;
    }
    
    protected function loadModule($moduleName)
    {
        $configPath = "{$this->modulePath}/{$moduleName}/config/config.php";
        $config = require $configPath;
        
        // Load module providers
        if (isset($config['providers'])) {
            foreach ($config['providers'] as $provider) {
                if (class_exists($provider)) {
                    try {
                        // Let Laravel's service container handle the instantiation
                        $instance = app()->resolveProvider($provider);
                        app()->register($instance);
                        $this->loadedModules[$moduleName] = true;
                    } catch (\Exception $e) {
                        Log::error("Failed to load provider {$provider} for module {$moduleName}: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
        }
        
        return true;
    }
    
    public function getLoadedModules()
    {
        return array_keys($this->loadedModules);
    }
}