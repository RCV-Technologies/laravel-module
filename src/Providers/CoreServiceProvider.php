<?php

namespace RCV\Core\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RCV\Core\Console\Commands\Actions\ModuleCheckLangCommand;
use RCV\Core\Console\Commands\Actions\ModuleCheckUpdatesCommand;
use RCV\Core\Console\Commands\Actions\ModuleCommandsListCommand;
use RCV\Core\Console\Commands\Actions\ModuleDisableCommand;
use RCV\Core\Console\Commands\Actions\ModuleEnableCommand;
use RCV\Core\Console\Commands\Actions\ModuleMarketplaceCommand;
use RCV\Core\Console\Commands\Actions\ModulePruneCommand;
use RCV\Core\Console\Commands\Actions\ModuleShowModelCommand;
use RCV\Core\Console\Commands\Actions\ModuleUnuseCommand;
use RCV\Core\Console\Commands\Actions\ModuleUseCommand;
use RCV\Core\Console\Commands\Analyze\ModuleAnalyzeCommand;
use RCV\Core\Console\Commands\Database\Factories\MakeModuleFactory;
use RCV\Core\Console\Commands\Database\Migrations\MigrateRefresh;
use RCV\Core\Console\Commands\Database\Migrations\MigrateSingleModuleMigration;
use RCV\Core\Console\Commands\Database\Migrations\MigrateStatusCommand;
use RCV\Core\Console\Commands\Database\Migrations\ModuleMigrateCommand;
// Import all command classes
use RCV\Core\Console\Commands\Database\Migrations\ModuleMigrateFresh;
use RCV\Core\Console\Commands\Database\Migrations\ModuleMigrateResetCommand;
use RCV\Core\Console\Commands\Database\Migrations\ModuleMigrateRollbackCommand;
use RCV\Core\Console\Commands\Database\Migrations\ModuleMigrationMakeCommand;
use RCV\Core\Console\Commands\Database\Seeders\ListSeeders;
use RCV\Core\Console\Commands\Database\Seeders\MakeModuleSeeder;
use RCV\Core\Console\Commands\Database\Seeders\ModuleSeedCommand;
use RCV\Core\Console\Commands\DevOps\PublishDevopsAssets;
use RCV\Core\Console\Commands\DevTools\ModuleProfileCommand;
use RCV\Core\Console\Commands\DiscoverModulesCommand;
use RCV\Core\Console\Commands\Docs\GenerateDocs;
use RCV\Core\Console\Commands\Make\MakeAction;
use RCV\Core\Console\Commands\Make\MakeCastCommand;
use RCV\Core\Console\Commands\Make\MakeChannel;
use RCV\Core\Console\Commands\Make\MakeEnum;
use RCV\Core\Console\Commands\Make\MakeInterfaceCommand;
use RCV\Core\Console\Commands\Make\MakeJobCommand;
use RCV\Core\Console\Commands\Make\MakeMailCommand;
use RCV\Core\Console\Commands\Make\MakeModuleArtisanCommand;
use RCV\Core\Console\Commands\Make\MakeModuleClass;
use RCV\Core\Console\Commands\Make\MakeModuleComponent;
use RCV\Core\Console\Commands\Make\MakeModuleNotification;
use RCV\Core\Console\Commands\Make\MakeModuleObserver;
use RCV\Core\Console\Commands\Make\MakeModulePolicy;
use RCV\Core\Console\Commands\Make\MakeModuleRequest;
use RCV\Core\Console\Commands\Make\MakeModuleRule;
use RCV\Core\Console\Commands\Make\MakeModuleTrait;
use RCV\Core\Console\Commands\Make\ModuleAllCommands;
use RCV\Core\Console\Commands\Make\ModuleEventProviderCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeControllerCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeEventCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeExceptionCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeHelperCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeListener;
use RCV\Core\Console\Commands\Make\ModuleMakeModelCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeRepositoryCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeResourceCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeScopeCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeServiceCommand;
use RCV\Core\Console\Commands\Make\ModuleMakeViewCommand;
use RCV\Core\Console\Commands\Make\ModuleMiddlewareCommand;
use RCV\Core\Console\Commands\Make\ModuleRouteProviderMakeCommand;
use RCV\Core\Console\Commands\MigrateV1ModulesToV2;
use RCV\Core\Console\Commands\ModuleAutoloadCommand;
use RCV\Core\Console\Commands\ModuleBackupCommand;
use RCV\Core\Console\Commands\ModuleClearCompiled;
use RCV\Core\Console\Commands\ModuleDebugCommand;
use RCV\Core\Console\Commands\ModuleDependencyGraphCommand;
use RCV\Core\Console\Commands\ModuleHealthCheckCommand;
use RCV\Core\Console\Commands\ModuleSetupCommand;
use RCV\Core\Console\Commands\ModuleStateCommand;
use RCV\Core\Console\Commands\Publish\ModulePublishConfig;
use RCV\Core\Console\Commands\Publish\ModulePublishMigration;
use RCV\Core\Console\Commands\Publish\ModulePublishTranslation;
use RCV\Core\Console\Commands\UpdatePhpunitCoverage;
use RCV\Core\Console\Commands\Upgrade\ModuleUpgradeCommand;
use RCV\Core\Contracts\RepositoryInterface;
use RCV\Core\Contracts\ServiceInterface;
use RCV\Core\Events\ModuleDisabled;
use RCV\Core\Events\ModuleEnabled;
use RCV\Core\Listeners\ClearCacheOnModuleDisable;
use RCV\Core\Listeners\ClearCacheOnModuleEnable;
use RCV\Core\Repositories\BaseRepository;
use RCV\Core\Services\BaseService;
use RCV\Core\Services\Communication\RpcManager;
use RCV\Core\Services\MarketplaceService;
use RCV\Core\Services\Messaging\MessageBus;
use RCV\Core\Services\ModuleAutoloader;
use RCV\Core\Services\ModuleLoader;
use RCV\Core\Services\ModuleManager;
use RCV\Core\Services\ModuleMetrics;
use RCV\Core\Services\ModuleNameValidator;
use RCV\Core\Services\ModuleRegistrationService;
use RCV\Core\Services\Security\AbacEvaluator;
use RCV\Core\Services\Security\AccessManager;
use RCV\Core\Services\Security\RbacManager;

class CoreServiceProvider extends ServiceProvider
{
    protected int $moduleProviderRegistrationFailures = 0;

    protected $moduleName = 'Core';

    protected $moduleNameLower = 'core';

    protected $moduleNamespace = 'RCV\Core';

    /**
     * All available Artisan commands.
     */
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
        ModuleMakeControllerCommand::class,
        ModuleMakeModelCommand::class,
        ModuleMakeResourceCommand::class,
        ModuleMakeRepositoryCommand::class,
        ModuleMakeEventCommand::class,
        ModuleMakeHelperCommand::class,
        ModuleMakeExceptionCommand::class,
        ModuleMakeScopeCommand::class,
        ModuleMakeViewCommand::class,
        ModuleMakeServiceCommand::class,
        ModuleMakeListener::class,
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
        ModuleRouteProviderMakeCommand::class,
        ModulePublishConfig::class,
        ModulePublishMigration::class,
        ModulePublishTranslation::class,
        ModuleEventProviderCommand::class,
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
        ModuleMigrateResetCommand::class,

        // Analysis & DevOps Commands
        GenerateDocs::class,
        ModuleUpgradeCommand::class,
        PublishDevopsAssets::class,
        ModuleProfileCommand::class,
        ModuleAnalyzeCommand::class,

        // Other Commands
        MigrateV1ModulesToV2::class,
        UpdatePhpunitCoverage::class,
        ModuleCheckLangCommand::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        parent::register();

        // Register configuration first
        $this->registerConfig();

        // Register event listeners
        Event::listen(ModuleEnabled::class, ClearCacheOnModuleEnable::class);
        Event::listen(ModuleDisabled::class, ClearCacheOnModuleDisable::class);

        // Bind contracts
        $this->app->bind(RepositoryInterface::class, BaseRepository::class);
        $this->app->bind(ServiceInterface::class, BaseService::class);

        // Register core singletons
        $this->registerCoreSingletons();

        // Register package commands once.
        $this->commands($this->commands);
        $this->registerAdditionalCommands();

        if (! config('core.lazy_load_modules', false)) {
            $this->registerModuleProviders();
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (config('core.lazy_load_modules', false)) {
            $this->registerModuleProviders();
        }
        if (config('core.module_warmup_enabled', false)) {
            $this->app->make(ModuleManager::class)->getLoadableEnabledModules();
        }
        $this->registerRoutes();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerMigrations();
    }

    /**
     * Register core singleton services.
     */
    protected function registerCoreSingletons(): void
    {
        // Core services
        $this->app->singleton(ModuleManager::class);
        $this->app->alias(ModuleManager::class, 'modules');
        $this->app->singleton(ModuleRegistrationService::class);
        $this->app->singleton(MarketplaceService::class);
        $this->app->singleton(ModuleLoader::class);

        // Named singletons with proper error handling
        $this->app->singleton('rcv.core.module_metrics', function ($app) {
            return new ModuleMetrics;
        });

        $this->app->singleton('rcv.core.security', function ($app) {
            return new AccessManager;
        });

        $this->app->singleton('rcv.core.rpc', function ($app) {
            return new RpcManager;
        });

        $this->app->scoped('rcv.core.rbac', function ($app) {
            $rbac = new RbacManager;
            $roles = config('security.rbac.roles', []);

            if (! is_array($roles)) {
                Log::warning('RBAC roles configuration is not an array, skipping role definitions');

                return $rbac;
            }

            foreach ($roles as $role => $perms) {
                try {
                    $rbac->defineRole($role, (array) $perms);
                } catch (\Throwable $e) {
                    Log::error("Failed to define RBAC role '{$role}': ".$e->getMessage());
                    report($e);
                }
            }

            return $rbac;
        });

        $this->app->singleton('rcv.core.abac', function ($app) {
            return new AbacEvaluator;
        });

        $this->app->singleton('rcv.core.message_bus', function ($app) {
            return new MessageBus;
        });
    }

    /**
     * Register additional commands from config and other sources.
     */
    protected function registerAdditionalCommands(): void
    {
        // Register commands from config file
        $configPath = __DIR__.'/../Config/config.php';
        if (File::exists($configPath)) {
            $config = require $configPath;
            if (! empty($config['commands']) && is_array($config['commands'])) {
                $this->commands($config['commands']);
            }
        }

        // Register commands only in console
        if ($this->app->runningInConsole()) {
            // Register seeder listener only once using singleton
            $this->app->singleton('rcv.seeder.listener.registered', function () {
                Event::listen(CommandFinished::class, function ($event) {
                    static $rcvSeedingModules = false;
                    if ($rcvSeedingModules) {
                        return;
                    }

                    if ($event->command === 'db:seed') {
                        if (! config('core.seed_modules', false)) {
                            return;
                        }

                        // Laravel supplies the root DatabaseSeeder as the default
                        // option even when the user runs plain `db:seed`.
                        $input = $event->input ?? null;
                        if ($input && $input->getOption('class') !== 'Database\\Seeders\\DatabaseSeeder') {
                            return;
                        }

                        $modulesPath = base_path('Modules');
                        if (! File::exists($modulesPath)) {
                            return;
                        }

                        $rcvSeedingModules = true;
                        try {
                            foreach (File::directories($modulesPath) as $moduleDir) {
                                $moduleName = basename($moduleDir);
                                $moduleManager = $this->app->make(ModuleManager::class);
                                if (! $moduleManager->isModuleEnabled($moduleName)) {
                                    continue;
                                }

                                $seederClass = "Modules\\{$moduleName}\\Database\\Seeders\\{$moduleName}DatabaseSeeder";
                                ModuleAutoloader::register($moduleName);
                                if (class_exists($seederClass)) {
                                    try {
                                        $exitCode = Artisan::call('db:seed', [
                                            '--class' => $seederClass,
                                            '--force' => true,
                                        ]);
                                        if ($exitCode !== 0) {
                                            $this->app['log']->error("Module seeder failed for {$moduleName}: ".Artisan::output());
                                            continue;
                                        }

                                    } catch (\Throwable $t) {
                                        $this->app['log']->error("Failed seeding module {$moduleName}: ".$t->getMessage());
                                    }
                                } else {
                                    $this->app['log']->warning("Module seeder class not found: {$seederClass}");
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
     * Register config files with improved error handling.
     */
    protected function registerConfig(): void
    {
        $configs = [
            'core' => __DIR__.'/../Config/config.php',
            'marketplace' => __DIR__.'/../Config/marketplace.php',
            'metrics' => __DIR__.'/../Config/metrics.php',
            'security' => __DIR__.'/../Config/security.php',
            'communication' => __DIR__.'/../Config/communication.php',
        ];

        foreach ($configs as $key => $path) {
            if (File::exists($path)) {
                try {
                    $this->mergeConfigFrom($path, $key);
                } catch (\Throwable $e) {
                    Log::error("Failed to load config file {$path}: ".$e->getMessage());
                    report($e);
                }
            } elseif (config('core.debug_config_loading', false)) {
                Log::debug("Config file not found: {$path}");
            }
        }

        // Publish config files
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('core.php'),
            __DIR__.'/../Config/marketplace.php' => config_path('marketplace.php'),
            __DIR__.'/../Config/metrics.php' => config_path('metrics.php'),
            __DIR__.'/../Config/security.php' => config_path('security.php'),
            __DIR__.'/../Config/communication.php' => config_path('communication.php'),
        ], 'rcv-core-config');
    }

    /**
     * Register routes with improved structure.
     */
    protected function registerRoutes(): void
    {
        // Web routes
        $webRoutePath = __DIR__.'/../Routes/web.php';
        if (File::exists($webRoutePath)) {
            Route::middleware('web')->group($webRoutePath);
        }

        // API routes
        $apiRoutePath = __DIR__.'/../Routes/api.php';
        if (File::exists($apiRoutePath)) {
            Route::middleware('api')->group($apiRoutePath);
        }
    }

    /**
     * Register views with fallback paths.
     */
    protected function registerViews(): void
    {
        $viewPaths = [
            base_path('Modules/Core/src/Resources/views'),
            __DIR__.'/../Resources/views',
        ];

        foreach ($viewPaths as $path) {
            if (File::exists($path)) {
                $this->loadViewsFrom($path, 'core');
                break;
            }
        }
    }

    /**
     * Register translations.
     */
    protected function registerTranslations(): void
    {
        $langPath = __DIR__.'/../Resources/lang';
        if (File::exists($langPath)) {
            $this->loadTranslationsFrom($langPath, 'core');
        }
    }

    /**
     * Register migrations with proper path handling.
     */
    protected function registerMigrations(): void
    {
        // FIXED: Corrected migration path concatenation
        $migrationsPath = __DIR__.'/../Database/Migrations';

        $this->publishes([
            $migrationsPath => database_path('migrations/'),
        ], 'rcv-core-migrations');

        if (File::exists($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }

        $this->registerModuleMigrationPathsForConsoleMigrations();
    }

    /**
     * Register every module migration path for Laravel's migration commands.
     */
    protected function registerModuleMigrationPathsForConsoleMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $command = $_SERVER['argv'][1] ?? null;
        $migrationCommands = [
            'migrate',
            'migrate:fresh',
            'migrate:refresh',
            'migrate:rollback',
            'migrate:reset',
            'migrate:status',
        ];

        if (! in_array($command, $migrationCommands, true)) {
            return;
        }

        $modulesPath = base_path('Modules');
        if (! File::isDirectory($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $modulePath) {
            foreach ([
                $modulePath.'/src/Database/Migrations',
                $modulePath.'/src/database/migrations',
                $modulePath.'/database/migrations',
            ] as $migrationPath) {
                if (File::isDirectory($migrationPath)) {
                    $this->loadMigrationsFrom($migrationPath);
                }
            }
        }
    }

    /**
     * Register module service providers (called during register phase - no caching).
     */
    protected function registerModuleProviders(): void
    {
        if ($this->shouldDeferModuleProviderRegistration()) {
            return;
        }

        try {
            $startedAt = hrtime(true);
            $this->moduleProviderRegistrationFailures = 0;
            $compiledPath = base_path('bootstrap/cache/modules.php');
            if (! config('core.hot_reload', false) && File::exists($compiledPath)) {
                $compiledProviders = require $compiledPath;
                if (is_array($compiledProviders)) {
                    $enabledModules = $this->app->make(ModuleManager::class)->getLoadableEnabledModules();
                    $compiledModules = [];
                    foreach ($compiledProviders as $module => $providerClass) {
                        if (! is_string($module) || ! is_string($providerClass)) {
                            continue;
                        }
                        $compiledModules[] = $module;
                        if (! in_array($module, $enabledModules, true)) {
                            continue;
                        }
                        $this->registerModuleProviderClass($module, $providerClass);
                        if (! $this->app->getProvider($providerClass)) {
                            $this->moduleProviderRegistrationFailures++;
                        }
                    }

                    foreach ($enabledModules as $module) {
                        if (! in_array($module, $compiledModules, true)) {
                            $this->registerSingleModuleProvider($module);
                        }
                    }

                    return;
                }
            }

            // Don't use Cache during register() - it's not available yet
            $modules = $this->app->make(ModuleManager::class)->getLoadableEnabledModules();

            if (empty($modules) || ! is_array($modules)) {
                return;
            }

            foreach ($modules as $module) {
                $this->registerSingleModuleProvider($module);
            }

            if (config('core.performance_monitoring', false)) {
                Log::debug('RCV Core module providers registered.', [
                    'module_count' => count($modules),
                    'boot_failures' => $this->moduleProviderRegistrationFailures,
                    'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Critical error in registerModuleProviders: '.$e->getMessage());
            report($e);

            if (config('core.strict_mode', false) || config('core.fail_fast_on_error', false)) {
                throw $e;
            }
        }
    }

    protected function shouldDeferModuleProviderRegistration(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }

        $command = $_SERVER['argv'][1] ?? null;

        return in_array($command, [
            'package:discover',
            'package:manifest',
            'migrate',
            'migrate:fresh',
            'migrate:install',
            'migrate:refresh',
            'migrate:reset',
            'migrate:rollback',
            'migrate:status',
            'db:wipe',
        ], true);
    }

    /**
     * Register a single module provider.
     */
    protected function registerSingleModuleProvider(string $module): void
    {
        $studly = Str::studly($module);
        $providerClass = "Modules\\{$studly}\\Providers\\{$studly}ServiceProvider";
        $this->registerModuleProviderClass($module, $providerClass);
    }

    protected function registerModuleProviderClass(string $module, string $providerClass): void
    {
        if (! ModuleNameValidator::isValid($module)) {
            return;
        }

        ModuleAutoloader::register($module);

        if (! class_exists($providerClass)) {
            Log::warning("Module provider class not found: {$providerClass}");
            $this->moduleProviderRegistrationFailures++;
            $this->enforceProviderFailureLimit();
            return;
        }

        // Skip if already registered
        if ($this->app->getProvider($providerClass)) {
            return;
        }

        try {
            $provider = $this->app->resolveProvider($providerClass);
            $this->app->register($provider);

        } catch (\Throwable $e) {
            Log::error("Failed to register provider {$providerClass}: ".$e->getMessage());
            report($e);
            $this->moduleProviderRegistrationFailures++;
            $this->enforceProviderFailureLimit();
            if (config('core.strict_mode', false) || config('core.fail_fast_on_error', false)) {
                throw $e;
            }
        }
    }

    protected function enforceProviderFailureLimit(): void
    {
        $limit = max(0, (int) config('core.max_boot_failures', 3));
        if ($limit > 0 && $this->moduleProviderRegistrationFailures > $limit) {
            throw new \RuntimeException("Module provider registration failure limit exceeded ({$limit}).");
        }
    }
}
