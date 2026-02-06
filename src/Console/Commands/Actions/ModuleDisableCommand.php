<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use RCV\Core\Services\ComposerDependencyManager;

class ModuleDisableCommand extends Command
{
    /**
     * NOTE:
     * - By default this command is NON-DESTRUCTIVE: it marks modules disabled but DOES NOT rollback migrations or delete files.
     * - Destructive actions (rollback / remove) must be explicitly requested via flags.
     */
    protected $signature = 'module:disable
                            {module* : Module name(s)}
                            {--force : Force disable even if other modules depend on it}
                            {--remove : Remove module files and registration (destructive)}
                            {--rollback : Rollback module migrations (destructive)}
                            {--dry-run : Show actions without making changes}
                            {--no-autoload : Do not run composer dump-autoload after operation}';

    protected $description = 'Disable one or more modules (non-destructive by default). Use --rollback or --remove to perform destructive cleanup.';

    public function handle(): int
    {
        $modules = $this->argument('module');
        $force = $this->option('force');
        $remove = $this->option('remove');
        $rollback = $this->option('rollback');
        $dryRun = $this->option('dry-run');
        $noAutoload = $this->option('no-autoload');

        $dependencyManager = new ComposerDependencyManager($this);

        $summary = [
            'processed' => [],
            'disabled' => [],
            'skipped' => [],
            'removed' => [],
            'rolled_back' => [],
            'errors' => [],
        ];

        foreach ($modules as $name) {
            $name = trim($name);
            $this->info("Processing module: {$name}");

            $modulePath = base_path("Modules/{$name}");

            if (! File::exists($modulePath)) {
                $msg = "Module [{$name}] not found in Modules directory.";
                $this->error($msg);
                $summary['errors'][] = $msg;
                $summary['skipped'][] = $name;
                continue;
            }

            $moduleState = DB::table('module_states')->where('name', $name)->first();
            if (! $moduleState) {
                $this->warn("Module [{$name}] is not registered in module_states. Creating disabled entry (dry-run will not persist).");
            }

            // Check dependencies unless forced
            if (! $force) {
                // Check if OTHER modules depend on this module (have this module in their "dependents" array)
                $modulesUsingThis = $this->findModulesThatDependOnThis($name);
                if (! empty($modulesUsingThis)) {
                    $this->error("⚠️  Cannot disable [{$name}] because the following modules depend on it:");
                    foreach ($modulesUsingThis as $usingModule) {
                        $this->line("   - {$usingModule}");
                    }
                    $this->comment("Use --force to override and proceed anyway (may break dependent modules).");
                    $summary['skipped'][] = $name;
                    continue;
                }

                // Also check composer.json dependencies (existing logic)
                $dependentModules = $this->findDependentModules($name);
                if (! empty($dependentModules)) {
                    $this->error("Cannot disable [{$name}] because it is required by: " . implode(', ', $dependentModules));
                    $this->comment("Use --force to override and proceed anyway.");
                    $summary['skipped'][] = $name;
                    continue;
                }
            } else {
                $this->comment("--force provided: skipping dependency checks for {$name}.");
            }

            // If remove is requested, confirm unless force is provided
            if ($remove && ! $force && ! $dryRun) {
                if (! $this->confirm("You requested --remove which will delete module files and DB state for [{$name}]. Are you sure?")) {
                    $this->warn("Skipping remove for {$name} by user decision.");
                    $remove = false; // treat as not removing for this module
                }
            }

            // Prepare actions to be performed
            $actions = [
                'disable' => true,
                'rollback' => $rollback,
                'remove' => $remove,
            ];

            $this->line("Actions for {$name}: " . implode(', ', array_keys(array_filter($actions))) );

            // Dry-run: just show what would happen
            if ($dryRun) {
                $this->info("[dry-run] Would perform actions for {$name}: " . json_encode($actions));
                $summary['processed'][] = $name;
                continue;
            }

            // Execute within DB transaction: mark disabled first
            try {
                DB::beginTransaction();

                // Create module_state row if missing
                if (! $moduleState) {
                    DB::table('module_states')->insert([
                        'name' => $name,
                        'version' => $this->readModuleVersion($name),
                        'description' => $this->readModuleDescription($name),
                        'enabled' => false,
                        'status' => 'disabled',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $moduleState = DB::table('module_states')->where('name', $name)->first();
                } else {
                    DB::table('module_states')->where('name', $name)->update([
                        'enabled' => false,
                        'status' => 'disabled',
                        'last_disabled_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Update module.json if present
                $this->updateModuleJsonState($name, false);

                DB::commit();
                $summary['disabled'][] = $name;
                $this->info("Module [{$name}] marked disabled.");
                
                // Remove module dependencies after successful disable
                $this->info("Removing dependencies for module [{$name}]...");
                if (!$dependencyManager->removeModuleDependencies($name)) {
                    $this->warn("Failed to remove some dependencies for module [{$name}], but continuing...");
                }
                
                // event AFTER successful disable
                event(new \RCV\Core\Events\ModuleDisabled($name));
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Failed to mark module {$name} disabled: " . $e->getMessage());
                Log::error("ModuleDisableCommand: failed to update state for {$name}", ['exception' => $e]);
                $summary['errors'][] = "Failed to mark disabled: {$name}: {$e->getMessage()}";
                continue;
            }

            // Rollback migrations only when requested
            if ($rollback) {
                try {
                    $rolled = $this->rollbackModuleMigrations($name, $force);
                    $summary['rolled_back'][$name] = $rolled;
                    $this->info("Rollback complete for {$name}: " . count($rolled) . " migration(s) rolled back.");
                } catch (\Throwable $e) {
                    $this->error("Rollback failed for {$name}: " . $e->getMessage());
                    Log::error("ModuleDisableCommand rollback failed", ['module' => $name, 'exception' => $e]);
                    $summary['errors'][] = "Rollback failed: {$name}: {$e->getMessage()}";
                }
            }

            // Remove module files/state only when requested
            if ($remove) {
                try {
                    $this->removeModuleFilesAndState($name);
                    $summary['removed'][] = $name;
                    $this->info("Module [{$name}] removed successfully.");
                } catch (\Throwable $e) {
                    $this->error("Failed to remove module [{$name}]: " . $e->getMessage());
                    Log::error("ModuleDisableCommand remove failed", ['module' => $name, 'exception' => $e]);
                    $summary['errors'][] = "Remove failed: {$name}: {$e->getMessage()}";
                }
            }

            $summary['processed'][] = $name;
        } // end foreach modules

        // Composer autoload unless explicitly disabled or if nothing destructive happened
        if (! $noAutoload && ! $dryRun) {
            $this->info('Refreshing Composer autoload files...');
            exec('composer dump-autoload -o', $outputLines, $exitCode);
            if ($exitCode !== 0) {
                $this->warn('composer dump-autoload returned non-zero exit code.');
            }
            
            // Update autoload configuration for enabled modules
            $this->info('Updating module autoload configuration...');
            $this->call('module:autoload');
        } else {
            $this->comment('Skipping composer dump-autoload (--no-autoload or dry-run).');
        }

        // Summary report
        $this->newLine();
        $this->info('=== Module Disable Summary ===');
        $this->line('Processed: ' . implode(', ', $summary['processed'] ?: ['<none>']));
        $this->line('Disabled: ' . implode(', ', $summary['disabled'] ?: ['<none>']));
        $this->line('Removed: ' . implode(', ', $summary['removed'] ?: ['<none>']));
        $this->line('Rolled back: ' . implode(', ', array_keys($summary['rolled_back'] ?: []) ?: ['<none>']));
        if (! empty($summary['errors'])) {
            $this->warn('Errors:');
            foreach ($summary['errors'] as $err) {
                $this->line(' - ' . $err);
            }
        } else {
            $this->info('No errors reported.');
        }
        $this->newLine();

        return empty($summary['errors']) ? 0 : 1;
    }

    /**
     * Find modules that have the given module in their "dependents" array
     * (i.e., modules that DEPEND ON the given module)
     *
     * @param string $name
     * @return array
     */
    protected function findModulesThatDependOnThis(string $name): array
    {
        $modulesUsingThis = [];
        $modules = File::directories(base_path('Modules'));

        foreach ($modules as $path) {
            $moduleJsonPath = "{$path}/module.json";
            if (! File::exists($moduleJsonPath)) {
                continue;
            }

            $data = json_decode(File::get($moduleJsonPath), true);
            if (! is_array($data)) {
                continue;
            }

            // Check if this module lists the target module in its dependents array
            $dependents = $data['dependents'] ?? [];
            if (in_array($name, $dependents)) {
                $modulesUsingThis[] = basename($path);
            }
        }

        return array_values(array_unique($modulesUsingThis));
    }

    /**
     * Find modules that depend on the given module (checks composer.json require entries starting with "Modules/").
     *
     * @param string $name
     * @return array
     */
    protected function findDependentModules(string $name): array
    {
        $dependent = [];
        $modules = File::directories(base_path('Modules'));

        foreach ($modules as $path) {
            $composerJson = "{$path}/composer.json";
            if (! File::exists($composerJson)) {
                continue;
            }

            $data = json_decode(File::get($composerJson), true);
            if (! is_array($data)) {
                continue;
            }

            // Normalise package key
            $lower = strtolower($name);
            foreach ($data['require'] ?? [] as $pkg => $version) {
                if (Str::startsWith($pkg, 'Modules/')) {
                    $dep = Str::after($pkg, 'Modules/');
                    if (strtolower($dep) === $lower) {
                        $dependent[] = basename($path);
                    }
                }
            }
        }

        return array_values(array_unique($dependent));
    }

    /**
     * Read human-friendly module version from module composer.json (graceful fallback).
     */
    protected function readModuleVersion(string $name): string
    {
        $composer = base_path("Modules/{$name}/composer.json");
        if (! File::exists($composer)) return '1.0.0';
        $data = json_decode(File::get($composer), true);
        return $data['version'] ?? '1.0.0';
    }

    /**
     * Read module description or fallback to name.
     */
    protected function readModuleDescription(string $name): string
    {
        $composer = base_path("Modules/{$name}/composer.json");
        if (! File::exists($composer)) return "{$name} module";
        $data = json_decode(File::get($composer), true);
        return $data['description'] ?? "{$name} module";
    }

    /**
     * Rollback migrations for the given module.
     *
     * CAUTION: destructive. This method only runs when user explicitly asks (--rollback).
     *
     * @param string $name
     * @param bool $force
     * @return array List of rolled-back migration filenames
     */
    protected function rollbackModuleMigrations(string $name, bool $force = false): array
    {
        $rolledBack = [];
        $modulePath = base_path("Modules/{$name}/src/Database/Migrations");
        if (! File::exists($modulePath)) {
            $this->comment("No migrations directory for {$name} (skipping rollback).");
            return $rolledBack;
        }

        // Attempt to read applied migrations from module_states where recorded
        $state = DB::table('module_states')->where('name', $name)->first();
        $applied = [];
        if ($state && ! empty($state->applied_migrations)) {
            $applied = is_array($state->applied_migrations) ? $state->applied_migrations : json_decode($state->applied_migrations, true);
        }

        // If we have applied migration filenames, rollback in reverse order
        if (! empty($applied)) {
            $applied = array_reverse($applied);
            foreach ($applied as $m) {
                try {
                    $this->call('migrate:rollback', [
                        '--path' => "Modules/{$name}/src/Database/Migrations/{$m}",
                        '--force' => true,
                    ]);
                    $rolledBack[] = $m;
                } catch (\Throwable $e) {
                    if (! $force) {
                        throw $e;
                    }
                    $this->warn("Rollback failed for {$m} but continuing due to --force: {$e->getMessage()}");
                }
            }

            // update module_states applied/failed migrations
            DB::table('module_states')->where('name', $name)->update([
                'applied_migrations' => json_encode(array_values(array_diff($state->applied_migrations ?? [], $rolledBack))),
            ]);
        } else {
            // If we don't have module states, attempt a single migrate:rollback on the module path
            $this->call('migrate:rollback', [
                '--path' => "Modules/{$name}/src/Database/Migrations",
                '--force' => true,
            ]);
            // no reliable list of rolled back migrations in this branch
            $rolledBack[] = 'migrate:rollback on path';
        }

        return $rolledBack;
    }

    /**
     * Remove module files and DB state.
     *
     * @param string $name
     * @return void
     */
    protected function removeModuleFilesAndState(string $name): void
    {
        // Remove provider registration from Modules/Core/src/Config/modules.php if present
        $configPath = base_path('Modules/Core/src/Config/modules.php');
        if (File::exists($configPath)) {
            $config = require $configPath;
            if (isset($config['modules']) && is_array($config['modules'])) {
                $providerClass = "Modules\\{$name}\\Providers\\{$name}ServiceProvider::class";
                $config['modules'] = array_values(array_filter($config['modules'], fn($p) => $p !== $providerClass));
                File::put($configPath, "<?php\n\nreturn " . var_export($config, true) . ";\n");
            }
        }

        // Remove composer json autoload entry and repository reference
        $composerPath = base_path('composer.json');
        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            if (isset($composer['autoload']['psr-4']["Modules\\{$name}\\"])) {
                unset($composer['autoload']['psr-4']["Modules\\{$name}\\"]);
            }

            if (isset($composer['repositories'])) {
                $composer['repositories'] = array_values(array_filter($composer['repositories'], fn($repo) => ! (isset($repo['url']) && $repo['url'] === "Modules/{$name}")));
            }

            File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // Delete module directory
        $modulePath = base_path("Modules/{$name}");
        if (File::exists($modulePath)) {
            File::deleteDirectory($modulePath);
        }

        // Remove DB state
        DB::table('module_states')->where('name', $name)->delete();
    }

    /**
     * Update module.json 'enabled' flag if module.json exists
     *
     * @param string $name
     * @param bool $enabled
     */
    protected function updateModuleJsonState(string $name, bool $enabled = false): void
    {
        $path = base_path("Modules/{$name}/module.json");
        if (! File::exists($path)) {
            return;
        }

        $json = json_decode(File::get($path), true);
        $json['enabled'] = $enabled;
        $json['last_disabled_at'] = now()->toIso8601String();
        File::put($path, json_encode($json, JSON_PRETTY_PRINT));
    }
}
