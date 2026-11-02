<?php

namespace RCV\Core\Services;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ModuleMiddlewareManager
{
    protected $router;

    protected $modulePath;

    protected $middlewareGroups = [
        'web' => [],
        'api' => [],
        'global' => [],
    ];

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->modulePath = base_path('Modules');
    }

    public function registerModuleMiddleware(string $moduleName): void
    {
        if (! ModuleNameValidator::isValid($moduleName)) {
            return;
        }

        $middlewarePath = "{$this->modulePath}/{$moduleName}/src/Http/Middleware";

        if (! File::exists($middlewarePath)) {
            return;
        }

        $middlewareFiles = File::glob("{$middlewarePath}/*.php");

        foreach ($middlewareFiles as $file) {
            $className = 'Modules\\'.$moduleName.'\\Http\\Middleware\\'.basename($file, '.php');

            if (class_exists($className)) {
                $this->registerMiddleware($className, $moduleName);
            }
        }

        // Register middleware groups from config
        $this->registerMiddlewareGroups($moduleName);
    }

    public function unregisterModuleMiddleware(string $moduleName): void
    {
        if (! ModuleNameValidator::isValid($moduleName)) {
            return;
        }

        $middlewarePath = "{$this->modulePath}/{$moduleName}/src/Http/Middleware";

        if (! File::exists($middlewarePath)) {
            return;
        }

        $middlewareFiles = File::glob("{$middlewarePath}/*.php");

        foreach ($middlewareFiles as $file) {
            $className = 'Modules\\'.$moduleName.'\\Http\\Middleware\\'.basename($file, '.php');

            if (class_exists($className)) {
                $this->unregisterMiddleware($className);
            }
        }

        // Unregister middleware groups
        $this->unregisterMiddlewareGroups($moduleName);
    }

    protected function registerMiddleware(string $className, string $moduleName): void
    {
        try {
            $middlewareName = $this->getMiddlewareName($className, $moduleName);

            // Register the middleware
            $this->router->aliasMiddleware($middlewareName, $className);

            // Add to global middleware if configured
            if ($this->shouldRegisterGlobally($className)) {
                $this->router->pushMiddlewareToGroup('global', $middlewareName);
            }

        } catch (\Exception $e) {
            Log::error("Failed to register middleware {$className}: ".$e->getMessage());
        }
    }

    protected function unregisterMiddleware(string $className): void
    {
        try {
            $middlewareName = $this->getMiddlewareName($className);

            // Remove from global middleware
            $this->router->removeMiddlewareFromGroup('global', $middlewareName);

            // Remove the middleware alias
            $this->router->removeMiddleware($middlewareName);

        } catch (\Exception $e) {
            Log::error("Failed to unregister middleware {$className}: ".$e->getMessage());
        }
    }

    protected function registerMiddlewareGroups(string $moduleName): void
    {
        $configPath = "{$this->modulePath}/{$moduleName}/src/Config/middleware.php";

        if (! File::exists($configPath)) {
            return;
        }

        $config = require $configPath;

        foreach ($this->middlewareGroups as $group => $middleware) {
            if (isset($config[$group])) {
                foreach ($config[$group] as $middleware) {
                    $this->router->pushMiddlewareToGroup($group, $middleware);
                }
            }
        }
    }

    protected function unregisterMiddlewareGroups(string $moduleName): void
    {
        $configPath = "{$this->modulePath}/{$moduleName}/src/Config/middleware.php";

        if (! File::exists($configPath)) {
            return;
        }

        $config = require $configPath;

        foreach ($this->middlewareGroups as $group => $middleware) {
            if (isset($config[$group])) {
                foreach ($config[$group] as $middleware) {
                    $this->router->removeMiddlewareFromGroup($group, $middleware);
                }
            }
        }
    }

    protected function getMiddlewareName(string $className, ?string $moduleName = null): string
    {
        $name = strtolower(basename($className));

        if ($moduleName) {
            $name = strtolower($moduleName).'.'.$name;
        }

        return $name;
    }

    protected function shouldRegisterGlobally(string $className): bool
    {
        $reflection = new \ReflectionClass($className);
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'GlobalMiddleware') {
                return true;
            }
        }

        return false;
    }

    public function getRegisteredMiddleware()
    {
        $registeredMiddleware = [];

        // Get global middleware
        $registeredMiddleware['global'] = app()->make('router')->getMiddleware();

        // Get middleware groups
        $registeredMiddleware['groups'] = app()->make('router')->getMiddlewareGroups();

        return $registeredMiddleware;
    }

    public function validateMiddleware(): array
    {
        $issues = [];
        $registeredMiddleware = $this->getRegisteredMiddleware();

        foreach ($registeredMiddleware as $group => $middleware) {
            foreach ($middleware as $name => $class) {
                if (! class_exists($class)) {
                    $issues[] = [
                        'group' => $group,
                        'name' => $name,
                        'class' => $class,
                        'issue' => 'Middleware class does not exist',
                    ];
                }
            }
        }

        return $issues;
    }
}
