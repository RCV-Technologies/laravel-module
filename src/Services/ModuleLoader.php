<?php

namespace RCV\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        if (! File::isDirectory($this->modulePath)) {
            return [];
        }

        $modules = $this->getAvailableModules();
        $loadedModules = [];

        foreach ($modules as $module) {
            try {
                if ($this->canLoadModule($module)) {
                    if ($this->loadModule($module)) {
                        $loadedModules[] = $module;
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Failed to load module {$module}: ".$e->getMessage());
            }
        }

        return $loadedModules;
    }

    protected function getAvailableModules(): array
    {
        return array_values(array_filter(
            array_map(static fn (string $path): string => basename($path), File::directories($this->modulePath)),
            static fn (string $moduleName): bool => ModuleNameValidator::isValid($moduleName)
        ));
    }

    protected function canLoadModule(string $moduleName): bool
    {
        $configPath = module_path($moduleName, 'src/Config/config.php');
        if (! File::exists($configPath)) {
            return false;
        }

        $config = require $configPath;

        return (bool) ($config['enabled'] ?? $config['settings']['enabled'] ?? false);
    }

    protected function loadModule(string $moduleName): bool
    {
        $configPath = module_path($moduleName, 'src/Config/config.php');
        $config = require $configPath;

        ModuleAutoloader::register($moduleName);
        $providers = $config['providers'] ?? [
            'Modules\\'.Str::studly($moduleName).'\\Providers\\'.Str::studly($moduleName).'ServiceProvider',
        ];

        foreach ($providers as $provider) {
            if (! is_string($provider) || ! class_exists($provider)) {
                continue;
            }

            try {
                $instance = app()->resolveProvider($provider);
                app()->register($instance);
                $this->loadedModules[$moduleName] = true;
            } catch (\Throwable $e) {
                Log::error("Failed to load provider {$provider} for module {$moduleName}: ".$e->getMessage());
                throw $e;
            }
        }

        return isset($this->loadedModules[$moduleName]);
    }

    public function getLoadedModules(): array
    {
        return array_keys($this->loadedModules);
    }
}
