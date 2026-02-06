<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RCV\Core\Services\ComposerDependencyManager;

class ModuleEnableCommand extends Command
{
    // Accept multiple names (1 or more)
    protected $signature = 'module:enable {module* : Module name(s) to enable}';
    protected $description = 'Enable one or more modules from Modules/ or vendor/rcv/';

    public function handle()
    {
        $names = $this->argument('module');
        $success = true;
        $dependencyManager = new ComposerDependencyManager($this);

        foreach ($names as $name) {
            try {
                $modulePath = base_path("Modules/{$name}");
                $isVendor = false;

                if (!File::exists($modulePath)) {
                    $vendorPath = base_path("vendor/rcv/{$name}");
                    if (File::exists($vendorPath)) {
                        $modulePath = $vendorPath;
                        $isVendor = true;
                    } else {
                        $this->error("Module [{$name}] not found.");
                        $success = false;
                        continue;
                    }
                }

                // Defaults
                $version = '1.0.0';
                $description = "{$name} module for the application";

                $moduleJsonPath = "{$modulePath}/module.json";
                if (File::exists($moduleJsonPath)) {
                    $json = json_decode(File::get($moduleJsonPath), true);
                    $version = $json['version'] ?? $version;
                    $description = $json['description'] ?? $description;
                }

                // Check and handle dependent modules BEFORE enabling this module
                $this->info("Checking dependencies for module [{$name}]...");
                $this->handleDependentModules($name, $dependencyManager);

                // Now we can proceed with enabling
                $this->info("Enabling module [{$name}]...");

                // Install module dependencies before enabling
                $this->info("Installing dependencies for module [{$name}]...");
                if (!$dependencyManager->installModuleDependencies($name)) {
                    $this->warn("Failed to install some dependencies for module [{$name}], but continuing with enable...");
                }

                // Update or insert module state
                $existing = DB::table('module_states')->where('name', $name)->first();
                if ($existing) {
                    Log::info("Calling of the enable");

                    DB::table('module_states')->where('name', $name)->update([
                        'enabled' => true,
                        'status' => 'enabled',
                        'last_enabled_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('module_states')->insert([
                        'name' => $name,
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
                if (File::exists($moduleJsonPath)) {
                    $json['enabled'] = true;
                    $json['last_enabled_at'] = now()->toIso8601String();
                    File::put($moduleJsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }

                // ✅ Dispatch event AFTER successful enable
                event(new \RCV\Core\Events\ModuleEnabled($name));

                $this->info("Module [{$name}] enabled.");

            } catch (\Exception $e) {
                $this->error("Error enabling module [{$name}]: " . $e->getMessage());
                $success = false;
            }
        }

        // Composer and discovery only once
        $this->info("Running composer dump-autoload...");
        exec('composer dump-autoload');

        $this->info("Running package discovery...");
        $this->call('package:discover');

        // Update autoload configuration for enabled modules
        $this->info("Updating module autoload configuration...");
        $this->call('module:autoload');

        return $success ? 0 : 1;
    }

    /**
     * Handle dependent modules (modules that this module DEPENDS ON)
     * When enabling a module, check if required modules exist and enable them first
     *
     * @param string $moduleName
     * @param ComposerDependencyManager $dependencyManager
     * @return void
     */
    protected function handleDependentModules(string $moduleName, ComposerDependencyManager $dependencyManager): void
    {
        $modulePath = base_path("Modules/{$moduleName}");
        $moduleJsonPath = "{$modulePath}/module.json";

        if (!File::exists($moduleJsonPath)) {
            return;
        }

        $json = json_decode(File::get($moduleJsonPath), true);
        $dependents = $json['dependents'] ?? [];

        if (empty($dependents)) {
            return;
        }

        $this->newLine();
        $this->info("Module [{$moduleName}] depends on: " . implode(', ', $dependents));

        $missingModules = [];
        $modulesToEnable = [];

        // First, check if all required modules exist
        foreach ($dependents as $requiredModule) {
            $requiredPath = base_path("Modules/{$requiredModule}");

            if (!File::exists($requiredPath)) {
                $missingModules[] = $requiredModule;
            } else {
                // Check if already enabled
                $requiredState = DB::table('module_states')->where('name', $requiredModule)->first();
                if (!$requiredState || !$requiredState->enabled) {
                    $modulesToEnable[] = $requiredModule;
                }
            }
        }

        // If any required module is missing, ask user
        if (!empty($missingModules)) {
            $this->error("⚠️  Required module(s) not found: " . implode(', ', $missingModules));
            
            if (!$this->confirm("Module [{$moduleName}] requires these modules to work properly. Do you want to continue anyway?", false)) {
                $this->error("Operation cancelled. Module [{$moduleName}] was NOT enabled.");
                throw new \Exception("Required module(s) not found: " . implode(', ', $missingModules));
            }
            
            $this->warn("Continuing without required modules. Module [{$moduleName}] may not work correctly.");
        }

        // Enable required modules
        foreach ($modulesToEnable as $requiredModule) {
            if ($this->confirm("Module [{$moduleName}] requires [{$requiredModule}]. Enable it now?", true)) {
                $this->info("Enabling required module [{$requiredModule}]...");

                try {
                    $requiredPath = base_path("Modules/{$requiredModule}");
                    
                    // Install dependencies for the required module
                    if (!$dependencyManager->installModuleDependencies($requiredModule)) {
                        $this->warn("Failed to install some dependencies for module [{$requiredModule}], but continuing...");
                    }

                    // Enable the required module
                    $requiredJsonPath = "{$requiredPath}/module.json";
                    $requiredJson = json_decode(File::get($requiredJsonPath), true);
                    $version = $requiredJson['version'] ?? '1.0.0';
                    $description = $requiredJson['description'] ?? "{$requiredModule} module";

                    $requiredState = DB::table('module_states')->where('name', $requiredModule)->first();
                    
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

                    $this->info("✅ Required module [{$requiredModule}] enabled successfully.");
                } catch (\Exception $e) {
                    $this->error("Failed to enable required module [{$requiredModule}]: " . $e->getMessage());
                    
                    if (!$this->confirm("Continue enabling [{$moduleName}] without [{$requiredModule}]?", false)) {
                        throw new \Exception("Failed to enable required module [{$requiredModule}]");
                    }
                }
            } else {
                $this->warn("Skipping required module [{$requiredModule}]. Module [{$moduleName}] may not work correctly.");
            }
        }
    }
}
