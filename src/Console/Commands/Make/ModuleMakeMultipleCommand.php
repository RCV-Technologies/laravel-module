<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RCV\Core\Services\ModuleNameValidator;

class ModuleMakeMultipleCommand extends Command
{
    protected $signature = 'module:make-multiple {names}';

    protected $description = 'Generate multiple modules';

    public function handle(): int
    {
        $names = explode(',', $this->argument('names'));
        $names = array_map(fn ($name) => trim($name), $names);
        $normalizedNames = [];
        $success = true;
        $configPath = base_path('vendor/rcv/core/src/Config/config.php');
        $config = File::exists($configPath) ? require $configPath : [];
        $configuredModules = $config['modules'] ?? [];
        $registeredModules = is_array($configuredModules)
            ? array_values(array_filter($configuredModules, 'is_string'))
            : [];

        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }

            if (! ModuleNameValidator::isValid($name)) {
                $this->error("Invalid module name [{$name}].");

                return self::FAILURE;
            }

            $moduleName = Str::studly($name);
            $normalizedName = strtolower($moduleName);
            if (isset($normalizedNames[$normalizedName])) {
                $this->error("Module [{$moduleName}] was requested more than once.");

                return self::FAILURE;
            }

            $normalizedNames[$normalizedName] = true;

            if (File::isDirectory(module_path($moduleName))) {
                $this->error("Module [{$moduleName}] already exists. Choose a different name.");

                return self::FAILURE;
            }

            if (in_array($normalizedName, array_map('strtolower', $registeredModules), true)) {
                $this->error("Module [{$moduleName}] is already registered.");

                return self::FAILURE;
            }
        }

        if ($normalizedNames === []) {
            $this->error('Provide at least one module name.');

            return self::FAILURE;
        }

        foreach ($names as $name) {
            if (empty($name)) {
                $this->warn(' Skipping empty name.');

                continue;
            }

            $moduleName = Str::studly($name);
            $this->info(" Creating module: {$moduleName}");

            // 1. Create module using existing logic
            $exitCode = Artisan::call('module:make', [
                'name' => $moduleName,
            ]);

            if ($exitCode !== 0) {
                $this->error(" Failed to create module [$moduleName].");
                $this->output->write(Artisan::output());
                $success = false;

                continue;
            }

            $this->info(" Module [$moduleName] created.");

            // 2. Add to marketplace
            $this->addToMarketplace($moduleName);

            // 3. Ask to enable/disable
            $enabled = $this->confirm(" Do you want to enable module [$moduleName] now?", true);
            $command = $enabled ? 'module:enable' : 'module:disable';
            $exitCode = Artisan::call($command, ['module' => [$moduleName]]);
            $this->output->write(Artisan::output());

            if ($exitCode !== 0) {
                $this->error(" Failed to set module [$moduleName] state.");
                $success = false;
            }
        }

        $this->info(' Done. All modules processed.');

        return $success ? self::SUCCESS : self::FAILURE;
    }

    protected function addToMarketplace(string $moduleName): void
    {
        $marketplaceFile = base_path('Modules/Core/marketplace.json');
        $marketplace = File::exists($marketplaceFile)
            ? json_decode(File::get($marketplaceFile), true)
            : ['modules' => []];

        if (! in_array($moduleName, $marketplace['modules'])) {
            $marketplace['modules'][] = $moduleName;
            File::put($marketplaceFile, json_encode($marketplace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info(" [$moduleName] added to marketplace.");
        } else {
            $this->line(" [$moduleName] already exists in marketplace.");
        }
    }
}
