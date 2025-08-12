<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeMultipleCommand extends Command
{
    protected $signature = 'module:make-multiple {names}';
    protected $description = 'Generate multiple modules';

    public function handle()
    {
        $names = explode(',', $this->argument('names'));
        $names = array_map(fn($name) => trim($name), $names);

        foreach ($names as $name) {
            if (empty($name)) {
                $this->warn(" Skipping empty name.");
                continue;
            }

            $moduleName = Str::studly($name);
            $moduleLower = strtolower($moduleName);
            $this->info(" Creating module: {$moduleName}");

            // 1. Create module using existing logic
            $exitCode = Artisan::call('module:make', [
                'name' => $moduleName,
            ]);

            if ($exitCode !== 0) {
                $this->error(" Failed to create module [$moduleName].");
                continue;
            }

            $this->info(" Module [$moduleName] created.");

            // 2. Add to marketplace
            $this->addToMarketplace($moduleName);

            // 3. Ask to enable/disable
            $enabled = $this->confirm(" Do you want to enable module [$moduleName] now?", true);
            $this->updateModuleStatus($moduleLower, $enabled);
        }

        $this->info(" Done. All modules processed.");
    }

    protected function addToMarketplace(string $moduleName): void
    {
        $marketplaceFile = base_path('Modules/Core/marketplace.json');
        $marketplace = File::exists($marketplaceFile)
            ? json_decode(File::get($marketplaceFile), true)
            : ['modules' => []];

        if (!in_array($moduleName, $marketplace['modules'])) {
            $marketplace['modules'][] = $moduleName;
            File::put($marketplaceFile, json_encode($marketplace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info(" [$moduleName] added to marketplace.");
        } else {
            $this->line(" [$moduleName] already exists in marketplace.");
        }
    }

    protected function updateModuleStatus(string $moduleLower, bool $enabled): void
    {
        $filePath = base_path("Modules/{$moduleLower}/module.json");

        if (!File::exists($filePath)) {
            $this->error(" module.json not found for [$moduleLower].");
            return;
        }

        $state = json_decode(File::get($filePath), true);
        $state['enabled'] = $enabled;
        $timestamp = now()->toDateTimeString();
        $state[$enabled ? 'last_enabled_at' : 'last_disabled_at'] = $timestamp;

        File::put($filePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info(" Module [$moduleLower] " . ($enabled ? "enabled" : "disabled") . ".");
    }
}
