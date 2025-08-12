<?php

namespace Rcv\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Rcv\Core\Models\ModuleState;
use Rcv\Core\Services\MarketplaceService;

class ModuleHealthCheck
{
    protected $marketplaceService;
    protected $modulePath;

    public function __construct(MarketplaceService $marketplaceService)
    {
        $this->marketplaceService = $marketplaceService;
        $this->modulePath = base_path('Modules');
    }

    public function check()
    {
        $results = [];
        $modules = File::directories($this->modulePath);

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            $results[$moduleName] = $this->checkModuleHealth($moduleName);
        }

        return $results;
    }

    public function checkModuleHealth(string $moduleName): array 
    {
        $health = [
            'name' => $moduleName,
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => now(),
        ];

        // Check module files
        $health['checks']['files'] = $this->checkModuleFiles($moduleName);

        // Check database
        $health['checks']['database'] = $this->checkDatabase($moduleName);

        // Check service provider
        $health['checks']['service_provider'] = $this->checkServiceProvider($moduleName);

        // Check migrations
        $health['checks']['migrations'] = $this->checkMigrations($moduleName);

        // Check cache
        $health['checks']['cache'] = $this->checkCache($moduleName);

        // Check dependencies
        $health['checks']['dependencies'] = $this->checkDependencies($moduleName);

        // Check routes
        $health['checks']['routes'] = $this->checkRoutes($moduleName);

        // Check views
        $health['checks']['views'] = $this->checkViews($moduleName);

        // Check config
        $health['checks']['config'] = $this->checkConfig($moduleName);

        // Update overall status
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'error') {
                $health['status'] = 'unhealthy';
                break;
            }
        }

        // Log health check results
        $this->logHealthCheck($health);

        return $health;
    }

    protected function checkModuleFiles(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'All required files exist',
            'details' => []
        ];

        $requiredFiles = [
            'composer.json',
            'src/Providers/' . $moduleName . 'ServiceProvider.php',
            'src/Routes/web.php',
            'src/Config/config.php'
        ];

        foreach ($requiredFiles as $file) {
            $path = "{$this->modulePath}/{$moduleName}/{$file}";
            if (!File::exists($path)) {
                $result['status'] = 'error';
                $result['message'] = 'Missing required files';
                $result['details'][] = "Missing file: {$file}";
            }
        }

        return $result;
    }

    protected function checkDatabase(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'messages' => [],
            'details' => []
        ];

        try {
            $moduleState = DB::table('modules')->where('name', $moduleName)->first();

            if (!$moduleState) {
                $result['status'] = 'error';
                $result['messages'][] = "Module state not found in database";
                return $result;
            }

            // Check if migrations are in sync
            $appliedMigrations = is_array($moduleState->applied_migrations) 
                ? $moduleState->applied_migrations 
                : json_decode($moduleState->applied_migrations ?? '[]', true);
            
            $failedMigrations = is_array($moduleState->failed_migrations)
                ? $moduleState->failed_migrations
                : json_decode($moduleState->failed_migrations ?? '[]', true);

            if (!empty($failedMigrations)) {
                $result['status'] = 'error';
                $result['messages'][] = "Module has failed migrations";
                $result['details']['failed_migrations'] = $failedMigrations;
            }

            $result['details']['applied_migrations'] = $appliedMigrations;
            $result['details']['failed_migrations'] = $failedMigrations;

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['messages'][] = "Database check failed: " . $e->getMessage();
        }

        return $result;
    }

    protected function checkServiceProvider(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Service provider is properly registered',
            'details' => []
        ];

        $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
        
        if (!class_exists($providerClass)) {
            $result['status'] = 'error';
            $result['message'] = 'Service provider class not found';
            return $result;
        }

        $providers = Config::get('app.providers', []);
        if (!in_array($providerClass, $providers)) {
            $result['status'] = 'error';
            $result['message'] = 'Service provider not registered in config/app.php';
        }

        return $result;
    }

    protected function checkMigrations(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Migrations are up to date',
            'details' => []
        ];

        $migrationPath = "{$this->modulePath}/{$moduleName}/src/Database/Migrations";
        
        if (!File::exists($migrationPath)) {
            $result['status'] = 'warning';
            $result['message'] = 'No migrations directory found';
            return $result;
        }

        $migrations = File::glob("{$migrationPath}/*.php");
        $dbMigrations = DB::table('migrations')
            ->where('migration', 'like', "%_{$moduleName}_%")
            ->pluck('migration')
            ->toArray();

        $missingMigrations = array_diff(
            array_map('basename', $migrations),
            array_map(function($migration) {
                return $migration . '.php';
            }, $dbMigrations)
        );

        if (!empty($missingMigrations)) {
            $result['status'] = 'error';
            $result['message'] = 'Missing migrations in database';
            $result['details'] = $missingMigrations;
        }

        return $result;
    }

    protected function checkCache(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'messages' => [],
            'details' => []
        ];

        if (!$this->cacheManager) {
            $result['status'] = 'warning';
            $result['messages'][] = 'Cache manager not available';
            return $result;
        }

        try {
            $testKey = "health_check_{$moduleName}";
            $testValue = 'test_value';

            // Test cache write
            $this->cacheManager->put($testKey, $testValue, 60);

            // Test cache read
            $cachedValue = $this->cacheManager->get($testKey);

            if ($cachedValue !== $testValue) {
                $result['status'] = 'error';
                $result['messages'][] = 'Cache read/write test failed';
                $result['details']['expected'] = $testValue;
                $result['details']['actual'] = $cachedValue;
            }

            // Test cache delete
            $this->cacheManager->forget($testKey);
            if ($this->cacheManager->has($testKey)) {
                $result['status'] = 'error';
                $result['messages'][] = 'Cache delete test failed';
            }

        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['messages'][] = 'Cache test failed: ' . $e->getMessage();
        }

        return $result;
    }

    protected function checkDependencies(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'All dependencies are satisfied',
            'details' => []
        ];

        try {
            $composerFile = "{$this->modulePath}/{$moduleName}/composer.json";
            if (File::exists($composerFile)) {
                $composer = json_decode(File::get($composerFile), true);
                if (isset($composer['require'])) {
                    foreach ($composer['require'] as $package => $version) {
                        if (strpos($package, 'modules/') === 0) {
                            $depModuleName = str_replace('modules/', '', $package);
                            if (!$this->marketplaceService->isModuleEnabled($depModuleName)) {
                                $result['status'] = 'error';
                                $result['message'] = 'Missing required module dependencies';
                                $result['details'][] = "Required module {$depModuleName} is not enabled";
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['message'] = 'Dependency check failed: ' . $e->getMessage();
        }

        return $result;
    }

    protected function checkRoutes(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Routes are properly registered',
            'details' => []
        ];

        $routeFile = "{$this->modulePath}/{$moduleName}/src/Routes/web.php";
        
        if (!File::exists($routeFile)) {
            $result['status'] = 'warning';
            $result['message'] = 'No routes file found';
            return $result;
        }

        // Check if routes are registered
        $routes = app('router')->getRoutes();
        $moduleRoutes = collect($routes)->filter(function ($route) use ($moduleName) {
            return strpos($route->getActionName(), "Modules\\{$moduleName}\\") === 0;
        });

        if ($moduleRoutes->isEmpty()) {
            $result['status'] = 'warning';
            $result['message'] = 'No routes registered for this module';
        }

        return $result;
    }

    protected function checkViews(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Views are properly registered',
            'details' => []
        ];

        $viewPath = "{$this->modulePath}/{$moduleName}/resources/views";
        
        if (!File::exists($viewPath)) {
            $result['status'] = 'warning';
            $result['message'] = 'No views directory found';
            return $result;
        }

        // Check if views are registered
        $viewFinder = app('view.finder');
        $paths = $viewFinder->getPaths();
        
        $moduleViewPath = strtolower($moduleName);
        $found = false;
        
        foreach ($paths as $path) {
            if (strpos($path, $moduleViewPath) !== false) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $result['status'] = 'warning';
            $result['message'] = 'Views are not properly registered';
        }

        return $result;
    }

    protected function checkConfig(string $moduleName): array
    {
        $result = [
            'status' => 'ok',
            'message' => 'Configuration is properly loaded',
            'details' => []
        ];

        $configFile = "{$this->modulePath}/{$moduleName}/src/Config/config.php";
        
        if (!File::exists($configFile)) {
            $result['status'] = 'warning';
            $result['message'] = 'No config file found';
            return $result;
        }

        // Check if config is loaded
        if (!Config::has(strtolower($moduleName))) {
            $result['status'] = 'error';
            $result['message'] = 'Module configuration is not loaded';
        }

        return $result;
    }

    protected function logHealthCheck(array $health): void
    {
        $logMessage = "Module Health Check for {$health['name']}: {$health['status']}";
        $context = [
            'module' => $health['name'],
            'status' => $health['status'],
            'checks' => $health['checks'],
            'timestamp' => $health['timestamp']
        ];

        if ($health['status'] === 'unhealthy') {
            Log::error($logMessage, $context);
        } else {
            Log::info($logMessage, $context);
        }
    }
} 