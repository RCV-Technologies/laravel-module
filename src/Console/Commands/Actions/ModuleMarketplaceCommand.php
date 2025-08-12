<?php

namespace Rcv\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Rcv\Core\Models\ModuleState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Rcv\Core\Services\MarketplaceService;

class ModuleMarketplaceCommand extends Command
{
    protected $signature = 'module:marketplace 
        {action : The action to perform (list|install|remove|update|cleanup)} 
        {module?* : One or more module names} 
        {--force : Force the action}';

    protected $description = 'Manage modules through the marketplace';
    protected $marketplaceService;

    public function __construct(MarketplaceService $marketplaceService)
    {
        parent::__construct();
        $this->marketplaceService = $marketplaceService;
    }

    public function handle()
    {
        $action = $this->argument('action');
        $names = $this->argument('module');
        $force = $this->option('force');

        switch ($action) {
            case 'list':
                return $this->listModules();
            case 'install':
                return $this->installModules($names);
            case 'remove':
                return $this->removeModules($names, $force);
            case 'update':
                if (empty($names)) {
                    $this->error("You must specify at least one module to update.");
                        return 1;
                }
                return $this->updateModules($names);
            case 'cleanup':
                return $this->cleanup();
            default:
                $this->error("Unknown action: {$action}");
                return 1;
        }
    }

    protected function listModules()
    {
        try {
            $modules = DB::table('module_states')->get();

            if ($modules->isEmpty()) {
                $this->info('No modules found.');
                return 0;
            }

            $headers = ['Name', 'Version', 'Description', 'Status'];
            $rows = [];

            foreach ($modules as $module) {
                $rows[] = [
                    $module->name,
                    $module->version,
                    $module->description,
                    $module->enabled ? 'enabled' : 'disabled'
                ];
            }

            $this->table($headers, $rows);
        } catch (\Exception $e) {
            $this->error("Failed to list modules: {$e->getMessage()}");
            return 1;
        }
    }

    protected function installModules(array $names): int
    {
        $success = true;
        foreach ($names as $name) {
            $result = $this->installModule($name);
            if (!$result) {
                $success = false;
            }
        }
        return $success ? 0 : 1;
    }

    protected function installModule($name)
    {
        try {
            $this->info("Installing module [{$name}]...");

            $this->info("Enabling module [{$name}]...");
            $this->call('module:enable', ['module' => [$name]]);

            $this->info('Running composer dump-autoload...');
            $this->runComposerDumpAutoload();

            $this->info('Running migrations...');
            $migrationsPath = base_path("Modules/{$name}/src/Database/Migrations");
            if (File::exists($migrationsPath)) {
                $migrationFiles = File::glob($migrationsPath . '/*.php');
                foreach ($migrationFiles as $file) {
                    $migrationName = pathinfo($file, PATHINFO_FILENAME);
                    if (!$this->migrationExists($migrationName)) {
                        try {
                            $this->runMigration($file);
                        } catch (\Exception $e) {
                            if (strpos($e->getMessage(), 'already exists') === false) {
                                throw $e;
                            }
                        }
                    }
                }
            }

            $this->info("Module [{$name}] installed successfully");
            return true;
        } catch (\Exception $e) {
            $this->error("Failed to install module [{$name}]: " . $e->getMessage());
            return false;
        }
    }

    protected function updateModules(array $names): int
    {
        $success = true;
        foreach ($names as $name) {
            if ($this->updateModule($name) !== 0) {
                $success = false;
            }
        }
        return $success ? 0 : 1;
    }

    protected function updateModule($name)
    {
        try {
            $moduleDetails = $this->marketplaceService->getModuleDetails($name);

            if ($moduleDetails['status'] !== 'enabled') {
                $this->error("Module [{$name}] is not installed");
                return 1;
            }

            $this->info("Updating module [{$name}]...");
            $this->call('module:disable', ['module' => [$name]]);
            $this->call('module:enable', ['module' => [$name]]);

            $this->info("Module [{$name}] updated successfully");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to update module: {$e->getMessage()}");
            return 1;
        }
    }

    protected function removeModules(array $names, bool $force): int
    {
        $success = true;
        foreach ($names as $name) {
            if ($this->removeModule($name, $force) !== 0) {
                $success = false;
            }
        }
        return $success ? 0 : 1;
    }

    protected function removeModule($name, $force = false)
    {
        try {
            $this->info("Removing module [{$name}]...");

            $moduleState = DB::table('module_states')->where('name', $name)->first();
            if ($moduleState && $moduleState->enabled) {
                $this->call('module:disable', ['module' => [$name], '--remove' => true]);
            }

            $modulePath = base_path("Modules/{$name}");
            if (File::exists($modulePath)) {
                File::deleteDirectory($modulePath);
            }

            $this->removeFromModulesConfig($name);
            $this->removeFromComposer($name);
            $this->removeFromCoreConfig($name);

            $this->info('Running composer dump-autoload...');
            exec('composer dump-autoload -o');

            DB::table('module_states')->where('name', $name)->delete();

            $this->info("Module [{$name}] has been completely removed from the system");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to remove module: {$e->getMessage()}");
            return 1;
        }
    }

    protected function migrationExists(string $migration): bool
    {
        return DB::table('migrations')
            ->where('migration', $migration)
            ->exists();
    }

    protected function runMigration(string $file): void
    {
        $migration = include $file;
        if (is_object($migration) && method_exists($migration, 'up')) {
            $migration->up();
            DB::table('migrations')->insert([
                'migration' => pathinfo($file, PATHINFO_FILENAME),
                'batch' => DB::table('migrations')->max('batch') + 1,
            ]);
        }
    }

    protected function runComposerDumpAutoload()
    {
        exec('composer dump-autoload');
    }

    protected function removeFromModulesConfig($name)
    {
        $configPath = base_path('Modules/Core/src/Config/modules.php');
        if (File::exists($configPath)) {
            $config = require $configPath;
            if (isset($config['modules'])) {
                $providerClass = "Modules\\{$name}\\Providers\\{$name}ServiceProvider::class";
                $config['modules'] = array_values(array_filter($config['modules'], function ($provider) use ($providerClass) {
                    return $provider !== $providerClass;
                }));
                $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
                File::put($configPath, $content);
            }
        }
    }

    protected function removeFromComposer($name)
    {
        $composerPath = base_path('composer.json');
        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);

            if (isset($composer['autoload']['psr-4']["Modules\\{$name}\\"])) {
                unset($composer['autoload']['psr-4']["Modules\\{$name}\\"]);
            }

            if (isset($composer['repositories'])) {
                $composer['repositories'] = array_values(array_filter($composer['repositories'], function ($repo) use ($name) {
                    return !isset($repo['url']) || $repo['url'] !== "modules/{$name}";
                }));
            }

            File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    protected function removeFromCoreConfig($name)
    {
        $configPath = base_path('Modules/Core/src/Config/config.php');
        if (File::exists($configPath)) {
            $config = require $configPath;

            if (isset($config['modules']) && is_array($config['modules'])) {
                $config['modules'] = array_values(array_filter($config['modules'], function ($module) use ($name) {
                    return $module !== $name;
                }));
            }

            $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
            File::put($configPath, $content);
        }
    }

    protected function cleanup()
    {
        try {
            $this->info('Cleaning up orphaned module states...');

            $modulePath = base_path('Modules');
            $states = ModuleState::all();
            $removedCount = 0;

            foreach ($states as $state) {
                if (!File::exists("{$modulePath}/{$state->name}")) {
                    $this->info("Removing orphaned state for module [{$state->name}]...");
                    $state->delete();
                    $removedCount++;
                }
            }

            if ($removedCount > 0) {
                $this->info("Successfully removed {$removedCount} orphaned module state(s)");
            } else {
                $this->info('No orphaned module states found');
            }
        } catch (\Exception $e) {
            $this->error("Failed to cleanup module states: {$e->getMessage()}");
            return 1;
        }
    }
}
