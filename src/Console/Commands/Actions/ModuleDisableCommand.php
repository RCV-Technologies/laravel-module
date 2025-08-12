<?php

namespace Rcv\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable 
        {module* : Module name(s)} 
        {--force : Force disable even if there are dependencies} 
        {--remove : Remove the module completely}';

    protected $description = 'Disable one or more modules';

    public function handle()
    {
        $names = $this->argument('module');
        $force = $this->option('force');
        $remove = $this->option('remove');

        $anyFailed = false;

        foreach ($names as $name) {
            $this->info("Disabling module [{$name}]...");
            $modulePath = base_path("Modules/{$name}");

            try {
                if (!File::exists($modulePath)) {
                    $this->error("Module [{$name}] not found in modules directory");
                    $anyFailed = true;
                    continue;
                }

                $moduleState = DB::table('module_states')->where('name', $name)->first();
                if (!$moduleState) {
                    $this->error("Module [{$name}] is not registered");
                    $anyFailed = true;
                    continue;
                }

                if (!$force) {
                    $dependentModules = $this->checkDependencies($name);
                    if (!empty($dependentModules)) {
                        $this->error("Cannot disable [{$name}]; required by: " . implode(', ', $dependentModules));
                        $this->info("Use --force to disable anyway");
                        $anyFailed = true;
                        continue;
                    }
                }

                DB::table('module_states')->where('name', $name)->update([
                    'enabled' => false,
                    'status' => 'disabled',
                    'last_disabled_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->updateModuleJsonState($name);

                if (File::exists("{$modulePath}/src/Database/Migrations")) {
                    $this->info("Rolling back migrations for [{$name}]...");

                    $appliedMigrations = json_decode($moduleState->applied_migrations ?? '[]', true);
                    $failedMigrations = json_decode($moduleState->failed_migrations ?? '[]', true);

                    $migrations = array_reverse($appliedMigrations);
                    $rolledBack = [];
                    $failedRollbacks = [];

                    foreach ($migrations as $migration) {
                        try {
                            $this->call('migrate:rollback', [
                                '--path' => "Modules/{$name}/src/Database/Migrations/{$migration}"
                            ]);
                            $rolledBack[] = $migration;
                        } catch (\Exception $e) {
                            if (!$force) throw $e;

                            $failedRollbacks[] = $migration;
                            $this->warn("Rollback failed but continuing (--force): {$migration}");
                        }
                    }

                    DB::table('module_states')->where('name', $name)->update([
                        'status' => 'disabled',
                        'applied_migrations' => json_encode(array_diff($appliedMigrations, $rolledBack)),
                        'failed_migrations' => json_encode(array_merge($failedMigrations, $failedRollbacks)),
                        'last_disabled_at' => now()
                    ]);
                }

                if ($remove) {
                    $this->info("Removing module [{$name}]...");
                    $this->removeFromModulesConfig($name);

                    if (File::exists($modulePath)) {
                        File::deleteDirectory($modulePath);
                    }

                    DB::table('module_states')->where('name', $name)->delete();
                    $this->removeFromComposer($name);

                    $this->info("Module [{$name}] removed completely.");
                } else {
                    $this->info("Module [{$name}] disabled.");
                }
            } catch (\Exception $e) {
                $this->error("Error disabling module [{$name}]: " . $e->getMessage());
                $anyFailed = true;
            }
        }

        $this->info('Running composer dump-autoload...');
        exec('composer dump-autoload');

        return $anyFailed ? 1 : 0;
    }

    protected function checkDependencies($name)
    {
        $dependentModules = [];
        $modules = File::directories(base_path('Modules'));

        foreach ($modules as $path) {
            $composerJson = "{$path}/composer.json";
            if (File::exists($composerJson)) {
                $config = json_decode(File::get($composerJson), true);
                if (isset($config['require']["modules/" . strtolower($name)])) {
                    $dependentModules[] = basename($path);
                }
            }
        }

        return $dependentModules;
    }

    protected function removeFromModulesConfig($name)
    {
        $configPath = base_path('Modules/Core/src/Config/modules.php');
        if (!File::exists($configPath)) return;

        $config = require $configPath;
        if (!isset($config['modules'])) return;

        $provider = "Modules\\{$name}\\Providers\\{$name}ServiceProvider::class";
        $config['modules'] = array_filter($config['modules'], fn($p) => $p !== $provider);

        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        File::put($configPath, $content);
    }

    protected function removeFromComposer($name)
    {
        $composerPath = base_path('composer.json');
        if (!File::exists($composerPath)) return;

        $composer = json_decode(File::get($composerPath), true);

        unset($composer['autoload']['psr-4']["Modules\\{$name}\\"]);

        if (isset($composer['repositories'])) {
            $composer['repositories'] = array_filter($composer['repositories'], fn($repo) =>
                !isset($repo['url']) || $repo['url'] !== "modules/{$name}"
            );
        }

        File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function updateModuleJsonState($name)
    {
        $path = base_path("Modules/{$name}/module.json");
        if (!File::exists($path)) return;

        $json = json_decode(File::get($path), true);
        $json['enabled'] = false;
        $json['last_enabled_at'] = now()->toIso8601String();

        File::put($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
