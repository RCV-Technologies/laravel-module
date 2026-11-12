<?php

namespace RCV\Core\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RCV\Core\Console\Commands\Make\ModuleMiddlewareCommand;
use RCV\Core\Console\Commands\ModuleBackupCommand;
use RCV\Core\Console\Commands\ModuleDependencyGraphCommand;
use RCV\Core\Console\Commands\ModuleHealthCheckCommand;
use RCV\Core\Http\Middleware\ModuleMiddleware;
use RCV\Core\Http\Middleware\ModuleMiddlewareManager;

abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The module namespace.
     *
     * @var string
     */
    protected $moduleNamespace;

    /**
     * The module name.
     *
     * @var string
     */
    protected $moduleName;

    /**
     * The module name in lowercase.
     *
     * @var string
     */
    protected $moduleNameLower;

    /**
     * Create a new service provider instance.
     *
     * @param  Application|null  $app
     * @return void
     */
    public function __construct($app)
    {
        parent::__construct($app);

        try {
            $reflection = new \ReflectionClass($this);
            $namespace = $reflection->getNamespaceName();
            $this->moduleName = str_replace('\\Providers', '', str_replace('Modules\\', '', $namespace));
            $this->moduleNameLower = strtolower($this->moduleName);
            $this->moduleNamespace = "Modules\\{$this->moduleName}";
        } catch (\Exception $e) {
            Log::error('Error in ModuleServiceProvider constructor: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        try {
            $this->registerConfig();
            $this->registerCommands();
            $this->registerMiddlewareManager();
        } catch (\Exception $e) {
            Log::error('Error in ModuleServiceProvider register: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfig();
        $this->publishAssets();
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerMiddleware();
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $moduleName = $this->getModuleName();
        $configPath = module_path($moduleName, 'src/Config/config.php');

        if (File::exists($configPath)) {
            $this->mergeConfigFrom($configPath, strtolower($moduleName));
            $this->publishes([
                $configPath => config_path(strtolower($moduleName).'.php'),
            ], 'config');
        }
    }

    /**
     * Publish config.
     */
    protected function publishConfig(): void
    {
        $moduleName = $this->getModuleName();
        $configPath = $this->getConfigPath();

        if (File::exists($configPath)) {
            $this->publishes([
                $configPath => config_path(strtolower($moduleName).'.php'),
            ], 'config');
        }

        $this->publishes([
            __DIR__.'/../Config/marketplace.php' => config_path('marketplace.php'),
            __DIR__.'/../Config/middleware.php' => config_path('middleware.php'),
        ], 'module-config');
    }

    /**
     * Publish assets.
     */
    protected function publishAssets(): void
    {
        $moduleName = $this->getModuleName();
        $assetsPath = $this->getAssetsPath();

        if (File::exists($assetsPath)) {
            $this->publishes([
                $assetsPath => public_path('Modules/'.strtolower($moduleName)),
            ], 'assets');
        }
    }

    /**
     * Register migrations.
     */
    protected function registerMigrations(): void
    {
        $migrationsPath = $this->getMigrationsPath();

        if (File::exists($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Register routes.
     */
    protected function registerRoutes(): void
    {
        $moduleName = $this->getModuleName();
        $webRoutePath = realpath(module_path($moduleName, 'src/Routes/web.php'));
        $apiRoutePath = realpath(module_path($moduleName, 'src/Routes/api.php'));
        $webRoutePath = realpath(module_path($moduleName, 'src/Routes/web.php'));
        $apiRoutePath = realpath(module_path($moduleName, 'src/Routes/api.php'));

        if ($webRoutePath && File::exists($webRoutePath)) {
            Route::middleware('web')
                ->group($webRoutePath);
        }

        if ($apiRoutePath && File::exists($apiRoutePath)) {
            Route::middleware('api')
                ->group($apiRoutePath);
        }
    }

    /**
     * Register views.
     */
    protected function registerViews(): void
    {
        $moduleName = $this->getModuleName();
        $viewsPath = $this->getViewsPath();

        if (File::exists($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->moduleNameLower);
            $this->publishes([
                $viewsPath => resource_path('views/Modules/'.$this->moduleNameLower),
            ], 'views');
        }
    }

    /**
     * Get module name.
     */
    abstract protected function getModuleName(): string;

    /**
     * Get migrations path.
     */
    protected function getMigrationsPath(): string
    {
        $moduleName = $this->getModuleName();

        return module_path($moduleName, 'src/Database/Migrations');

        return module_path($moduleName, 'src/Database/Migrations');
    }

    /**
     * Get views path.
     */
    protected function getViewsPath(): string
    {
        $moduleName = $this->getModuleName();

        return module_path($moduleName, 'src/Resources/views');

        return module_path($moduleName, 'src/Resources/views');
    }

    /**
     * Get config path.
     */
    protected function getConfigPath(): string
    {
        $moduleName = $this->getModuleName();

        return module_path($moduleName, 'src/Config/config.php');

        return module_path($moduleName, 'src/Config/config.php');
    }

    /**
     * Get assets path.
     */
    protected function getAssetsPath(): string
    {
        $moduleName = $this->getModuleName();

        return module_path($moduleName, 'src/Resources/assets');

        return module_path($moduleName, 'src/Resources/assets');
    }

    protected function registerMiddlewareManager()
    {
        $this->app->singleton(ModuleMiddlewareManager::class, function ($app) {
            return new ModuleMiddlewareManager($app['router']);
        });
    }

    protected function registerMiddleware()
    {
        $this->app['router']->aliasMiddleware('module', ModuleMiddleware::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([
            ModuleHealthCheckCommand::class,
            ModuleDependencyGraphCommand::class,
            ModuleBackupCommand::class,
            ModuleMiddlewareCommand::class,
        ]);
    }
}
