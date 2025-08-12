<?php

namespace Rcv\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class ModuleEnableCommand extends Command
{
    // Accept multiple names (1 or more) 
    protected $signature = 'module:enable {module* : Module name(s) to enable}';
    protected $description = 'Enable one or more modules from modules/ or vendor/rcv/';

    public function handle()
    {
        $names = $this->argument('module');
        $success = true;

        foreach ($names as $name) {
            $this->info("Enabling module [{$name}]...");

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

                // Update or insert module state
                $existing = DB::table('module_states')->where('name', $name)->first();
                if ($existing) {
                    \Log::info("Calling of the enable");

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

        return $success ? 0 : 1;
    }
}