<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RCV\Core\Events\ModuleEnabled;
use RCV\Core\Services\ModuleManifestValidator;
use RCV\Core\Services\ModuleNameValidator;

class ModuleEnableCommand extends Command
{
    // Accept multiple names, or use --all to enable every module in Modules/.
    protected $signature = 'module:enable {module?* : Module name(s) to enable} {--all : Enable all modules in Modules/}';

    protected $description = 'Enable one or more modules from Modules/ or vendor/rcv/';

    public function handle(): int
    {
        $names = $this->argument('module') ?? [];

        if ($this->option('all')) {
            $names = [];

            foreach (File::directories(base_path('Modules')) as $modulePath) {
                $moduleName = basename($modulePath);

                if (ModuleNameValidator::isValid($moduleName) && File::exists("{$modulePath}/module.json")) {
                    $names[] = $moduleName;
                }
            }

            if ($names === []) {
                $this->warn('No modules with a module.json manifest were found in Modules/.');

                return self::SUCCESS;
            }

            $this->info('Enabling all discovered modules: '.implode(', ', $names));
        } elseif ($names === []) {
            $this->error('Provide one or more module names, or use --all.');

            return self::FAILURE;
        }

        $success = true;
        foreach ($names as $name) {
            try {
                if (! ModuleNameValidator::isValid($name)) {
                    $this->error("Invalid module name [{$name}]. Use letters, numbers, hyphens, and underscores; start with a letter.");
                    $success = false;

                    continue;
                }

                $modulePath = module_path($name);
                $isVendor = false;

                if (! File::exists($modulePath)) {
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

                $moduleJsonPath = "{$modulePath}/module.json";
                $json = ModuleManifestValidator::read($name, $moduleJsonPath);
                $version = $json['version'];
                $description = $json['description'] ?? "{$name} module for the application";

                // Check and handle dependent modules BEFORE enabling this module
                $this->info("Checking module requirements for [{$name}]...");
                $this->handleDependentModules($name);

                // Now we can proceed with enabling
                $this->info("Enabling module [{$name}]...");

                // Update or insert module state
                $existing = DB::table('module_states')->where('name', $name)->first();
                if ($existing) {
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

                // ✅ Dispatch event AFTER successful enable
                event(new ModuleEnabled($name));

                $this->info("Module [{$name}] enabled.");

            } catch (\Exception $e) {
                $this->error("Error enabling module [{$name}]: ".$e->getMessage());
                $success = false;
            }
        }

        return $success ? 0 : 1;
    }

    /**
     * Handle dependent modules (modules that this module DEPENDS ON)
     * When enabling a module, check if required modules exist and enable them first
     */
    protected function handleDependentModules(string $moduleName): void
    {
        $modulePath = module_path($moduleName);
        $moduleJsonPath = "{$modulePath}/module.json";

        if (! File::exists($moduleJsonPath)) {
            return;
        }

        $json = ModuleManifestValidator::read($moduleName, $moduleJsonPath);
        $dependents = $json['dependents'] ?? [];

        if (empty($dependents)) {
            return;
        }

        $this->newLine();
        $this->info("Module [{$moduleName}] depends on: ".implode(', ', $dependents));

        $missingModules = [];
        $modulesToEnable = [];

        // First, check if all required modules exist
        foreach ($dependents as $requiredModule) {
            if (! ModuleNameValidator::isValid($requiredModule)) {
                throw new \InvalidArgumentException('Module manifest contains an invalid dependency name.');
            }

            $requiredPath = module_path($requiredModule);

            if (! File::exists($requiredPath)) {
                $missingModules[] = $requiredModule;
            } else {
                // Check if already enabled
                $requiredState = DB::table('module_states')->where('name', $requiredModule)->first();
                if (! $requiredState || ! $requiredState->enabled) {
                    $modulesToEnable[] = $requiredModule;
                }
            }
        }

        // If any required module is missing, ask user
        if (! empty($missingModules)) {
            $this->error('⚠️  Required module(s) not found: '.implode(', ', $missingModules));

            if (! $this->confirm("Module [{$moduleName}] requires these modules to work properly. Do you want to continue anyway?", false)) {
                $this->error("Operation cancelled. Module [{$moduleName}] was NOT enabled.");
                throw new \Exception('Required module(s) not found: '.implode(', ', $missingModules));
            }

            $this->warn("Continuing without required modules. Module [{$moduleName}] may not work correctly.");
        }

        // Enable required modules
        foreach ($modulesToEnable as $requiredModule) {
            if ($this->confirm("Module [{$moduleName}] requires [{$requiredModule}]. Enable it now?", true)) {
                $this->info("Enabling required module [{$requiredModule}]...");

                try {
                    $requiredPath = module_path($requiredModule);

                    // Enable the required module
                    $requiredJsonPath = "{$requiredPath}/module.json";
                    $requiredJson = ModuleManifestValidator::read($requiredModule, $requiredJsonPath);
                    $version = $requiredJson['version'];
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

                    event(new ModuleEnabled($requiredModule));

                    $this->info("✅ Required module [{$requiredModule}] enabled successfully.");
                } catch (\Exception $e) {
                    $this->error("Failed to enable required module [{$requiredModule}]: ".$e->getMessage());

                    if (! $this->confirm("Continue enabling [{$moduleName}] without [{$requiredModule}]?", false)) {
                        throw new \Exception("Failed to enable required module [{$requiredModule}]");
                    }
                }
            } else {
                $this->warn("Skipping required module [{$requiredModule}]. Module [{$moduleName}] may not work correctly.");
            }
        }
    }
}
