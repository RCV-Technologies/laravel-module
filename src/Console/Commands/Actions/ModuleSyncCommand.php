<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RCV\Core\Services\ComposerDependencyManager;

class ModuleSyncCommand extends Command
{
    protected $signature = 'module:sync {module?* : Module name(s) to sync. If none provided, syncs all modules}
                           {--force : Force sync even if there are conflicts}
                           {--db-priority : Give database state priority over JSON file}
                           {--json-priority : Give JSON file priority over database}
                           {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Synchronize module enabled status between module.json files and database';

    public function handle()
    {
        $modules = $this->argument('module');
        $force = $this->option('force');
        $dbPriority = $this->option('db-priority');
        $jsonPriority = $this->option('json-priority');
        $dryRun = $this->option('dry-run');

        $dependencyManager = new ComposerDependencyManager($this);

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // If no specific modules provided, sync all modules
        if (empty($modules)) {
            $modules = $this->getAllModules();
        }

        if (empty($modules)) {
            $this->warn('No modules found to sync.');
            return 0;
        }

        $this->info('🔄 Starting module synchronization...');
        $this->newLine();

        $syncResults = [];
        $conflicts = [];

        foreach ($modules as $moduleName) {
            $result = $this->syncModule($moduleName, $force, $dbPriority, $jsonPriority, $dryRun, $dependencyManager);

            if ($result['status'] === 'conflict') {
                $conflicts[] = $result;
            } else {
                $syncResults[] = $result;
            }
        }

        // Display results
        $this->displayResults($syncResults, $conflicts, $dryRun);

        // Handle conflicts
        if (!empty($conflicts) && !$force && !$dbPriority && !$jsonPriority) {
            $this->handleConflicts($conflicts, $dryRun);
        }

        if (!$dryRun) {
            $this->info('🔄 Running composer dump-autoload...');
            exec('composer dump-autoload');

            $this->info('🔍 Running package discovery...');
            $this->call('package:discover');
        }


        $this->newLine();
        $this->call('module:autoload');
        $this->info('✅ Module synchronization completed!');

        return 0;
    }

    private function getAllModules(): array
    {
        $modules = [];

        // Get modules from Modules directory
        $modulesPath = base_path('Modules');
        if (File::exists($modulesPath)) {
            $directories = File::directories($modulesPath);
            foreach ($directories as $dir) {
                $modules[] = basename($dir);
            }
        }

        // Get modules from vendor/rcv directory
        $vendorPath = base_path('vendor/rcv');
        if (File::exists($vendorPath)) {
            $directories = File::directories($vendorPath);
            foreach ($directories as $dir) {
                $moduleName = basename($dir);
                if (!in_array($moduleName, $modules)) {
                    $modules[] = $moduleName;
                }
            }
        }

        return $modules;
    }

    private function syncModule(string $moduleName, bool $force, bool $dbPriority, bool $jsonPriority, bool $dryRun, ComposerDependencyManager $dependencyManager = null): array
    {
        $result = [
            'module' => $moduleName,
            'status' => 'unknown',
            'action' => 'none',
            'message' => '',
            'json_enabled' => null,
            'db_enabled' => null,
        ];

        // Find module path
        $modulePath = base_path("Modules/{$moduleName}");
        $isVendor = false;

        if (!File::exists($modulePath)) {
            $vendorPath = base_path("vendor/rcv/{$moduleName}");
            if (File::exists($vendorPath)) {
                $modulePath = $vendorPath;
                $isVendor = true;
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Module not found';
                return $result;
            }
        }

        // Read JSON file
        $moduleJsonPath = "{$modulePath}/module.json";
        $jsonEnabled = null;
        $jsonData = null;

        if (File::exists($moduleJsonPath)) {
            try {
                $jsonData = json_decode(File::get($moduleJsonPath), true);
                $jsonEnabled = $jsonData['enabled'] ?? false;
                $result['json_enabled'] = $jsonEnabled;
            } catch (\Exception $e) {
                $result['status'] = 'error';
                $result['message'] = 'Invalid JSON file: ' . $e->getMessage();
                return $result;
            }
        } else {
            $result['status'] = 'error';
            $result['message'] = 'module.json not found';
            return $result;
        }

        // Read database state
        $dbState = DB::table('module_states')->where('name', $moduleName)->first();
        $dbEnabled = $dbState ? (bool) $dbState->enabled : null;
        $result['db_enabled'] = $dbEnabled;

        // Determine sync action
        if ($dbEnabled === null && $jsonEnabled === true) {
            // JSON enabled but no DB entry - create DB entry to match JSON
            $result['status'] = 'needs_sync';
            $result['action'] = 'create_db_entry';
            $result['message'] = 'JSON shows enabled but no DB entry - will create enabled DB entry';

            if (!$dryRun) {
                $this->updateDbEnabled($moduleName, true, $jsonData, $dependencyManager);
            }
            return $result;
        }

        if ($dbEnabled === null && $jsonEnabled === false) {
            // JSON disabled but no DB entry - create DB entry to match JSON
            $result['status'] = 'needs_sync';
            $result['action'] = 'create_db_entry';
            $result['message'] = 'JSON shows disabled but no DB entry - will create disabled DB entry';

            if (!$dryRun) {
                $this->updateDbEnabled($moduleName, false, $jsonData, $dependencyManager);
            }
            return $result;
        }

        if ($dbEnabled !== null && $jsonEnabled === $dbEnabled) {
            // Already synced - but check if dependencies need to be installed/removed
            $result['status'] = 'synced';
            $result['message'] = 'JSON and DB are already synchronized';
            
            // Handle dependencies for already synced modules
            if ($dependencyManager && !$dryRun) {
                if ($jsonEnabled) {
                    $this->info("Ensuring dependencies are installed for already enabled module [{$moduleName}]...");
                    $dependencyManager->installModuleDependencies($moduleName);
                } else {
                    $this->info("Ensuring unused dependencies are removed for already disabled module [{$moduleName}]...");
                    $dependencyManager->removeModuleDependencies($moduleName);
                }
            }
            
            return $result;
        }

        if ($dbEnabled !== null && $jsonEnabled !== $dbEnabled) {
            // Conflict - different states
            $result['status'] = 'conflict';
            $result['message'] = "Conflict: JSON={$jsonEnabled}, DB={$dbEnabled}";

            // Handle priority options
            if ($dbPriority || ($force && !$jsonPriority)) {
                $result['action'] = 'update_json_from_db';
                $result['message'] .= ' - Will update JSON to match DB';

                if (!$dryRun) {
                    $this->updateJsonEnabled($moduleJsonPath, $jsonData, $dbEnabled, $dependencyManager);
                }
                $result['status'] = 'synced';
            } elseif ($jsonPriority) {
                $result['action'] = 'update_db_from_json';
                $result['message'] .= ' - Will update DB to match JSON';

                if (!$dryRun) {
                    $this->updateDbEnabled($moduleName, $jsonEnabled, $jsonData, $dependencyManager);
                }
                $result['status'] = 'synced';
            }

            return $result;
        }

        return $result;
    }

    private function updateJsonEnabled(string $jsonPath, array $jsonData, bool $enabled, ComposerDependencyManager $dependencyManager = null): void
    {
        $jsonData['enabled'] = $enabled;

        if ($enabled) {
            $jsonData['last_enabled_at'] = now()->toIso8601String();
        } else {
            $jsonData['last_disabled_at'] = now()->toIso8601String();
        }

        File::put($jsonPath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Handle dependencies if dependency manager is provided
        if ($dependencyManager) {
            // Extract module name from the JSON path
            $moduleName = $jsonData['name'] ?? basename(dirname($jsonPath));
            
            if ($enabled) {
                $dependencyManager->installModuleDependencies($moduleName);
            } else {
                $dependencyManager->removeModuleDependencies($moduleName);
            }
        }
    }

    private function updateDbEnabled(string $moduleName, bool $enabled, array $jsonData, ComposerDependencyManager $dependencyManager = null): void
    {
        $version = $jsonData['version'] ?? '1.0.0';
        $description = $jsonData['description'] ?? "{$moduleName} module for the application";

        $existing = DB::table('module_states')->where('name', $moduleName)->first();

        if ($existing) {
            DB::table('module_states')->where('name', $moduleName)->update([
                'enabled' => $enabled,
                'status' => $enabled ? 'enabled' : 'disabled',
                'last_enabled_at' => $enabled ? now() : $existing->last_enabled_at,
                'last_disabled_at' => !$enabled ? now() : $existing->last_disabled_at,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('module_states')->insert([
                'name' => $moduleName,
                'version' => $version,
                'description' => $description,
                'enabled' => $enabled,
                'status' => $enabled ? 'enabled' : 'disabled',
                'last_enabled_at' => $enabled ? now() : null,
                'last_disabled_at' => !$enabled ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Handle dependencies if dependency manager is provided
        if ($dependencyManager) {
            if ($enabled) {
                $dependencyManager->installModuleDependencies($moduleName);
            } else {
                $dependencyManager->removeModuleDependencies($moduleName);
            }
        }

        // Handle dependent modules when enabling
        if ($enabled) {
            $this->syncDependentModules($moduleName, $jsonData, $dependencyManager);
        }

        // Dispatch events
        if ($enabled) {
            event(new \RCV\Core\Events\ModuleEnabled($moduleName));
        } else {
            event(new \RCV\Core\Events\ModuleDisabled($moduleName));
        }
    }

    /**
     * Sync dependent modules when a module is enabled
     * (Enable modules that this module DEPENDS ON)
     *
     * @param string $moduleName
     * @param array $jsonData
     * @param ComposerDependencyManager|null $dependencyManager
     * @return void
     */
    private function syncDependentModules(string $moduleName, array $jsonData, ComposerDependencyManager $dependencyManager = null): void
    {
        $dependents = $jsonData['dependents'] ?? [];

        if (empty($dependents)) {
            return;
        }

        $this->info("Module [{$moduleName}] depends on: " . implode(', ', $dependents));

        foreach ($dependents as $requiredModule) {
            $requiredPath = base_path("Modules/{$requiredModule}");
            $requiredJsonPath = "{$requiredPath}/module.json";

            if (!File::exists($requiredJsonPath)) {
                $this->warn("⚠️  Required module [{$requiredModule}] not found. Module [{$moduleName}] may not work correctly.");
                continue;
            }

            // Check if required module is already enabled
            $requiredState = DB::table('module_states')->where('name', $requiredModule)->first();
            if ($requiredState && $requiredState->enabled) {
                $this->comment("Required module [{$requiredModule}] is already enabled.");
                continue;
            }

            $this->info("Auto-syncing required module [{$requiredModule}]...");

            try {
                $requiredJson = json_decode(File::get($requiredJsonPath), true);
                $version = $requiredJson['version'] ?? '1.0.0';
                $description = $requiredJson['description'] ?? "{$requiredModule} module";

                // Install dependencies
                if ($dependencyManager) {
                    $dependencyManager->installModuleDependencies($requiredModule);
                }

                // Update database
                if ($requiredState) {
                    DB::table('module_states')->where('name', $requiredModule)->update([
                        'enabled' => true,
                        'status' => 'enabled',
                        'last_enabled_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('module_states')->insert([
                        'name' => $requiredModule,
                        'version' => $version,
                        'description' => $description,
                        'enabled' => true,
                        'status' => 'enabled',
                        'last_enabled_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Update module.json
                $requiredJson['enabled'] = true;
                $requiredJson['last_enabled_at'] = now()->toIso8601String();
                File::put($requiredJsonPath, json_encode($requiredJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                event(new \RCV\Core\Events\ModuleEnabled($requiredModule));

                $this->info("✅ Required module [{$requiredModule}] synced successfully.");
            } catch (\Exception $e) {
                $this->error("Failed to sync required module [{$requiredModule}]: " . $e->getMessage());
            }
        }
    }

    private function displayResults(array $results, array $conflicts, bool $dryRun): void
    {
        if (empty($results) && empty($conflicts)) {
            return;
        }

        $tableData = [];
        foreach (array_merge($results, $conflicts) as $result) {
            $tableData[] = [
                $result['module'],
                $result['json_enabled'] === null ? 'N/A' : ($result['json_enabled'] ? 'true' : 'false'),
                $result['db_enabled'] === null ? 'N/A' : ($result['db_enabled'] ? 'true' : 'false'),
                $result['status'],
                $result['action'],
                $result['message']
            ];
        }

        $this->table(
            ['Module', 'JSON', 'DB', 'Status', 'Action', 'Message'],
            $tableData
        );
    }

    private function handleConflicts(array $conflicts, bool $dryRun): void
    {
        $this->newLine();
        $this->warn('⚠️  Conflicts detected! Use one of these options to resolve:');
        $this->newLine();

        $this->line('  --db-priority     : Update JSON files to match database state');
        $this->line('  --json-priority   : Update database to match JSON files');
        $this->line('  --force           : Same as --db-priority');
        $this->line('  --dry-run         : Preview changes without applying them');

        $this->newLine();
        $this->info('Example: php artisan module:sync --db-priority');
    }
}