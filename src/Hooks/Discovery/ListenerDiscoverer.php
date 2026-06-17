<?php

namespace RCV\Core\Hooks\Discovery;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RCV\Core\Services\ModuleManager;
use RCV\Core\Hooks\HookManager;
use RCV\Core\Hooks\Contracts\HookListenerInterface;

class ListenerDiscoverer
{
    protected HookManager $hookManager;
    protected array $config;

    public function __construct(HookManager $hookManager, array $config)
    {
        $this->hookManager = $hookManager;
        $this->config = $config;
    }

    /**
     * Discover and register active module listeners.
     */
    public function discover(): void
    {
        $cacheEnabled = $this->config['cache_enabled'] ?? true;
        $cacheKey = $this->config['cache_key'] ?? 'rcv_hooks_discovered_listeners';
        $cacheTtl = $this->config['cache_ttl'] ?? 86400;

        if ($cacheEnabled) {
            $listeners = Cache::remember($cacheKey, $cacheTtl, function () {
                return $this->scanForListeners();
            });
        } else {
            $listeners = $this->scanForListeners();
        }

        foreach ($listeners as $listener) {
            $className = $listener['class'];
            $hookName = $listener['hook'];
            $priority = $listener['priority'];

            // Register with lazy resolution via Laravel Service Container
            $this->hookManager->listen($hookName, function (...$args) use ($className) {
                return app($className)->handle(...$args);
            }, $priority);
        }
    }

    /**
     * Scan only enabled modules for Hook classes. Non-recursive scan.
     */
    protected function scanForListeners(): array
    {
        $listeners = [];

        // Check if ModuleManager is registered in container
        if (!app()->bound(ModuleManager::class)) {
            return [];
        }

        /** @var ModuleManager $moduleManager */
        $moduleManager = app(ModuleManager::class);
        $enabledModules = $moduleManager->getEnabledModules();

        $modulesPath = base_path('Modules');

        foreach ($enabledModules as $module) {
            // Standard modules path: Modules/{ModuleName}/src/Hooks/
            $hooksPath = "{$modulesPath}/{$module}/src/Hooks";

            if (!File::isDirectory($hooksPath)) {
                continue;
            }

            // Scan files in Hooks directory non-recursively
            $files = File::files($hooksPath);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $className = "Modules\\{$module}\\Hooks\\" . basename($file->getFilename(), '.php');

                if (class_exists($className) && is_subclass_of($className, HookListenerInterface::class)) {
                    $listeners[] = [
                        'class' => $className,
                        'hook' => $className::getHook(),
                        'priority' => $className::getPriority(),
                    ];
                }
            }
        }

        return $listeners;
    }
}
