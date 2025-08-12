<?php

namespace Rcv\Core\Providers;

use Illuminate\Support\Str;
use Rcv\Core\Services\BaseService;
use Rcv\Core\Services\ModuleLoader;
use Illuminate\Support\Facades\File;
use Rcv\Core\Services\ModuleManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Rcv\Core\Contracts\ServiceInterface;
use Rcv\Core\Repositories\BaseRepository;
use Rcv\Core\Repositories\MainRepository;
use Rcv\Core\Services\MarketplaceService;
use Rcv\Core\Contracts\RepositoryInterface;
use Rcv\Core\Console\Commands\Make\MakeEnum;
use Rcv\Core\Console\Commands\Make\MakeAction;
use Rcv\Core\Console\Commands\Make\MakeChannel;
use Rcv\Core\Services\ModuleRegistrationService;
use Rcv\Core\Console\Commands\ModuleDebugCommand;
use Rcv\Core\Console\Commands\ModuleSetupCommand;
use Rcv\Core\Console\Commands\ModuleStateCommand;
use Rcv\Core\Console\Commands\Make\MakeJobCommand;
use Rcv\Core\Console\Commands\Make\MakeModuleRule;
use Rcv\Core\Console\Commands\ModuleBackupCommand;
use Rcv\Core\Console\Commands\ModuleClearCompiled;
use Rcv\Core\Console\Commands\Make\MakeCastCommand;
use Rcv\Core\Console\Commands\Make\MakeMailCommand;
use Rcv\Core\Console\Commands\Make\MakeModuleClass;
use Rcv\Core\Console\Commands\Make\MakeModuleTrait;
use Rcv\Core\Console\Commands\MigrateV1ModulesToV2;
use Rcv\Core\Console\Commands\Make\MakeModulePolicy;
use Rcv\Core\Console\Commands\ModuleAutoloadCommand;
use Rcv\Core\Console\Commands\UpdatePhpunitCoverage;
use Rcv\Core\Console\Commands\DiscoverModulesCommand;
use Rcv\Core\Console\Commands\Make\MakeComponentView;
use Rcv\Core\Console\Commands\Make\MakeModuleRequest;
use Rcv\Core\Console\Commands\Make\ModuleAllCommands;
use Rcv\Core\Console\Commands\Make\ModuleMakeCommand;
use Rcv\Core\Console\Commands\Make\MakeModuleObserver;
use Rcv\Core\Console\Commands\Make\ModuleMakeListener;
use Rcv\Core\Console\Commands\Actions\ModuleUseCommand;
use Rcv\Core\Console\Commands\Make\MakeModuleComponent;
use Rcv\Core\Console\Commands\ModuleHealthCheckCommand;
use Rcv\Core\Console\Commands\Make\MakeInterfaceCommand;
use Rcv\Core\Console\Commands\Actions\ModulePruneCommand;
use Rcv\Core\Console\Commands\Actions\ModuleUnuseCommand;
use Rcv\Core\Console\Commands\Make\ModuleMakeViewCommand;
use Rcv\Core\Console\Commands\Actions\ModuleEnableCommand;
use Rcv\Core\Console\Commands\Make\MakeModuleNotification;
use Rcv\Core\Console\Commands\Make\ModuleMakeEventCommand;
use Rcv\Core\Console\Commands\Make\ModuleMakeScopeCommand;
use Rcv\Core\Console\Commands\Make\ModuleModelMakeCommand;
use Rcv\Core\Console\Commands\Publish\ModulePublishConfig;
use Rcv\Core\Console\Commands\Actions\ModuleDisableCommand;
use Rcv\Core\Console\Commands\Database\Seeders\ListSeeders;
use Rcv\Core\Console\Commands\Make\ModuleMakeHelperCommand;
use Rcv\Core\Console\Commands\Make\ModuleMiddlewareCommand;
use Rcv\Core\Console\Commands\ModuleDependencyGraphCommand;
use Rcv\Core\Console\Commands\Make\MakeModuleArtisanCommand;
use Rcv\Core\Console\Commands\Make\ModuleServiceMakeCommand;
use Rcv\Core\Console\Commands\Actions\ModuleShowModelCommand;
use Rcv\Core\Console\Commands\Make\ModuleResourceMakeCommand;
use Rcv\Core\Console\Commands\Publish\ModulePublishMigration;
use Rcv\Core\Console\Commands\Make\ModuleEventProviderCommand;
use Rcv\Core\Console\Commands\Make\ModuleMakeExceptionCommand;
use Rcv\Core\Console\Commands\Actions\ModuleMarketplaceCommand;
use Rcv\Core\Console\Commands\Make\ModuleControllerMakeCommand;
use Rcv\Core\Console\Commands\Make\ModuleRepositoryMakeCommand;
use Rcv\Core\Console\Commands\Publish\ModulePublishTranslation;
use Rcv\Core\Console\Commands\Actions\ModuleCheckUpdatesCommand;
use Rcv\Core\Console\Commands\Actions\ModuleCommandsListCommand;
use Rcv\Core\Console\Commands\Database\Seeders\MakeModuleSeeder;
use Rcv\Core\Console\Commands\Database\Migrations\MigrateRefresh;
use Rcv\Core\Console\Commands\Database\Seeders\ModuleSeedCommand;
use Rcv\Core\Console\Commands\Make\ModuleRouteProviderMakeCommand;
use Rcv\Core\Console\Commands\Database\Factories\MakeModuleFactory;
use Rcv\Core\Console\Commands\Database\Migrations\ModuleMigrateFresh;
use Rcv\Core\Console\Commands\Database\Migrations\MigrateStatusCommand;
use Rcv\Core\Console\Commands\Database\Migrations\ModuleMigrateCommand;
use Rcv\Core\Console\Commands\Database\Migrations\ModuleMigrationMakeCommand;
use Rcv\Core\Console\Commands\Database\Migrations\MigrateSingleModuleMigration;
use Rcv\Core\Console\Commands\Database\Migrations\ModuleMigrateRollbackCommand;

class CoreServiceProvider extends ServiceProvider
{
    protected $moduleName = 'Core';
    protected $moduleNameLower = 'core';
    protected $moduleNamespace = 'Rcv\Core';

    protected $commands = [
        // Action Commands
        ModuleMarketplaceCommand::class,
        ModuleStateCommand::class,
        ModuleEnableCommand::class,
        ModuleDisableCommand::class,
        ModuleDebugCommand::class,
        ModuleCheckUpdatesCommand::class,
        ModulePruneCommand::class,
        ModuleUseCommand::class,
        ModuleUnuseCommand::class,
        ModuleShowModelCommand::class,
        ModuleCommandsListCommand::class,
        ModuleBackupCommand::class,
        ModuleDependencyGraphCommand::class,
        ModuleHealthCheckCommand::class,
        ModuleSetupCommand::class,
        ModuleClearCompiled::class,
        DiscoverModulesCommand::class,

        // Make Commands
        ModuleAllCommands::class,
        ModuleMakeCommand::class,
        ModuleControllerMakeCommand::class,
        ModuleModelMakeCommand::class,
        ModuleResourceMakeCommand::class,
        ModuleRepositoryMakeCommand::class,
        ModuleMakeEventCommand::class,
        ModuleMakeHelperCommand::class,
        ModuleMakeExceptionCommand::class,
        ModuleMakeScopeCommand::class,
        MakeComponentView::class,
        MakeChannel::class,
        MakeModuleClass::class,
        MakeModuleArtisanCommand::class,
        MakeModuleObserver::class,
        MakeModulePolicy::class,
        MakeModuleRule::class,
        MakeModuleTrait::class,
        MakeEnum::class,
        ModuleAutoloadCommand::class,
        MakeModuleComponent::class,
        MakeModuleRequest::class,
        ModuleMakeListener::class,
        ModuleMakeViewCommand::class,
        ModuleRouteProviderMakeCommand::class,
        ModulePublishConfig::class,
        ModulePublishMigration::class,
        ModulePublishTranslation::class,
        ModuleEventProviderCommand::class,
        ModuleServiceMakeCommand::class,
        MakeCastCommand::class,
        MakeJobCommand::class,
        MakeMailCommand::class,
        MakeModuleNotification::class,
        MakeAction::class,
        MakeInterfaceCommand::class,
        ModuleMiddlewareCommand::class,

        // Database Commands
        MakeModuleFactory::class,
        MigrateRefresh::class,
        MigrateSingleModuleMigration::class,
        MigrateStatusCommand::class,

        ModuleMigrateRollbackCommand::class,
        ModuleMigrationMakeCommand::class,
        ListSeeders::class,
        MakeModuleSeeder::class,
        ModuleSeedCommand::class,
        ModuleMigrateCommand::class,
        ModuleMigrateFresh::class,

        // Other Commands
        MigrateV1ModulesToV2::class,
        UpdatePhpunitCoverage::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        parent::register();

        $this->app->bind(RepositoryInterface::class, BaseRepository::class);
        // $this->app->bind(Repository::class, MainRepository::class);
        $this->app->bind(ServiceInterface::class, BaseService::class);
        $this->registerConfig();

        $this->app->singleton(ModuleManager::class);
        $this->app->singleton(ModuleRegistrationService::class);
        $this->app->singleton(MarketplaceService::class);

        $this->app->singleton(ModuleLoader::class, function ($app) {
            return new ModuleLoader();
        });

        $this->commands($this->commands);

        // Use application-level singleton to prevent multiple registrations
        $this->app->singleton('rcv.modules.registered', function () {
            $this->registerModuleProviders();
            return true;
        });

        // Force resolution to ensure registration happens only once
        $this->app->make('rcv.modules.registered');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerCommands();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerMigrations();
        
        // Use application-level singleton to prevent multiple boots
        $this->app->singleton('rcv.modules.booted', function () {
            $this->bootModules();
            return true;
        });

        // Force resolution to ensure booting happens only once
        $this->app->make('rcv.modules.booted');
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $configPath = __DIR__.'/../Config/config.php';
        $marketplaceConfigPath = __DIR__.'/../Config/marketplace.php';
        
        if (File::exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'core');
        }

        if (File::exists($marketplaceConfigPath)) {
            $this->mergeConfigFrom($marketplaceConfigPath, 'marketplace');
        }

        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('core.php'),
        ], 'config');
    }

    /**
     * Register commands.
     */
    protected function registerCommands(): void
    {
        $configPath = __DIR__.'/../Config/config.php';
        if (File::exists($configPath)) {
            $config = require $configPath;
            if (isset($config['commands'])) {
                $this->commands($config['commands']);
            }
        }

        $this->commands([
            ModuleAutoloadCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);

            // Register seeder listener only once using singleton
            $this->app->singleton('rcv.seeder.listener.registered', function () {
                \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandFinished::class, function ($event) {
                    static $rcvSeedingModules = false;
                    if ($rcvSeedingModules) {
                        return;
                    }

                    if ($event->command === 'db:seed') {
                        // Only run when no specific class is provided
                        $input = $event->input ?? null;
                        if ($input && $input->getOption('class')) {
                            return;
                        }

                        $modulesPath = base_path('Modules');
                        if (!\Illuminate\Support\Facades\File::exists($modulesPath)) {
                            return;
                        }

                        $rcvSeedingModules = true;
                        try {
                            foreach (\Illuminate\Support\Facades\File::directories($modulesPath) as $moduleDir) {
                                $moduleName = basename($moduleDir);
                                $seederClass = "Modules\\\\{$moduleName}\\\\Database\\\\Seeders\\\\{$moduleName}DatabaseSeeder";
                                if (class_exists($seederClass)) {
                                    try {
                                        \Illuminate\Support\Facades\Artisan::call('db:seed', [
                                            '--class' => $seederClass,
                                            '--force' => true,
                                        ]);
                                        $this->app['log']->info("Seeded module: {$moduleName}");
                                    } catch (\Throwable $t) {
                                        $this->app['log']->error("Failed seeding module {$moduleName}: " . $t->getMessage());
                                    }
                                }
                            }
                        } finally {
                            $rcvSeedingModules = false;
                        }
                    }
                });
                return true;
            });

            // Force resolution
            $this->app->make('rcv.seeder.listener.registered');
        }
    }

    /**
     * Register routes.
     */
    protected function registerRoutes(): void
    {
        Route::group(['middleware' => ['web']], function () {
            $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        });

        Route::group(['middleware' => ['api']], function () {
            $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        });
    }

    /**
     * Register views.
     */
    protected function registerViews(): void
    {
        $viewPath = base_path('Modules/Core/src/Resources/views');
        
        if (File::exists($viewPath)) {
            $this->loadViewsFrom($viewPath, 'core');
        }
    }

    /**
     * Register translations.
     */
    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'core');
    }

    /**
     * Register migrations.
     */
    protected function registerMigrations(): void
    {
        $migrationsPath = base_path(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__ . '/../Database/Migrations' => database_path('migrations/'),
        ], 'core-module-migrations');

        if (File::exists($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Register module service providers.
     */
    protected function registerModuleProviders(): void
    {
        try {
            // Check if we already have registered providers in this application instance
            if ($this->app->bound('rcv.registered.providers')) {
                return; // Already registered
            }

            $moduleManager = $this->app->make(ModuleManager::class);
            $modules = $moduleManager->getEnabledModules();
            
            $registeredProviders = [];
            
            foreach ($modules as $module) {
                $studlyModule = Str::studly($module);
                $providerClass = "Modules\\{$studlyModule}\\Providers\\{$studlyModule}ServiceProvider";
                
                if (class_exists($providerClass)) {
                    try {
                        // Check if provider is already registered in Laravel's container
                        if (!in_array($providerClass, $this->app->getLoadedProviders())) {
                            $provider = $this->app->resolveProvider($providerClass);
                            $this->app->register($provider);
                            $registeredProviders[] = $providerClass;
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to register provider {$providerClass}: " . $e->getMessage());
                        throw $e;
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning("Provider class not found: {$providerClass}");
                }
            }

            // Store the registered providers list as a singleton
            $this->app->singleton('rcv.registered.providers', function () use ($registeredProviders) {
                return $registeredProviders;
            });

            // Only log if there were actual registrations
            if (!empty($registeredProviders) && config('app.debug', false)) {
                \Illuminate\Support\Facades\Log::debug('Module providers registered', $registeredProviders);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error in registerModuleProviders: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Boot registered modules.
     */
    protected function bootModules(): void
    {
        try {
            // Check if we already have booted modules in this application instance
            if ($this->app->bound('rcv.booted.modules')) {
                return; // Already booted
            }

            // Get the registered providers
            if (!$this->app->bound('rcv.registered.providers')) {
                return; // No providers registered yet
            }

            $registeredProviders = $this->app->make('rcv.registered.providers');
            $bootedModules = [];

            foreach ($registeredProviders as $providerClass) {
                try {
                    $provider = $this->app->resolveProvider($providerClass);
                    if (method_exists($provider, 'boot')) {
                        call_user_func([$provider, 'boot']);
                        $bootedModules[] = $providerClass;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to boot provider {$providerClass}: " . $e->getMessage());
                    throw $e;
                }
            }

            // Mark as booted
            $this->app->singleton('rcv.booted.modules', function () use ($bootedModules) {
                return $bootedModules;
            });

            // Only log if there were actual boots
            if (!empty($bootedModules) && config('app.debug', false)) {
                \Illuminate\Support\Facades\Log::debug('Module providers booted', $bootedModules);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error in bootModules: " . $e->getMessage());
            throw $e;
        }
    }
}