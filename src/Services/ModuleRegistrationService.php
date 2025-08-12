<?php

namespace Rcv\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class ModuleRegistrationService
{
    protected $appConfigPath;
    protected $modulePath;

    public function __construct()
    {
        $this->appConfigPath = config_path('app.php');
        $this->modulePath = base_path('Modules');
    }

    /**
     * Register a module in app.php
     */
    public function registerModule(string $moduleName): bool
    {
        try {
            $config = require $this->appConfigPath;
            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            
            // Check if provider is already registered
            if (in_array($providerClass, $config['providers'])) {
                return true;
            }

            // Add provider to the list
            $config['providers'][] = $providerClass;

            // Write back to app.php
            $this->writeConfig($config);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Unregister a module from app.php
     */
    public function unregisterModule(string $moduleName): bool
    {
        try {
            $config = require $this->appConfigPath;
            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            
            // Remove provider from the list
            $config['providers'] = array_filter($config['providers'], function($provider) use ($providerClass) {
                return $provider !== $providerClass;
            });

            // Write back to app.php
            $this->writeConfig($config);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Write configuration back to app.php
     */
    protected function writeConfig(array $config): void
    {
        $content = "<?php\n\nuse Illuminate\Support\Facades\Facade;\nuse Illuminate\Support\ServiceProvider;\n\nreturn " . 
            var_export($config, true) . ";\n";
        
        File::put($this->appConfigPath, $content);
    }

    /**
     * Get all registered modules
     */
    public function getRegisteredModules(): array
    {
        $config = require $this->appConfigPath;
        $modules = [];
        
        foreach ($config['providers'] as $provider) {
            if (strpos($provider, 'Modules\\') === 0) {
                $parts = explode('\\', $provider);
                if (count($parts) >= 3) {
                    $modules[] = $parts[1];
                }
            }
        }
        
        return array_unique($modules);
    }
} 