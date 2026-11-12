<?php

namespace RCV\Core\Console\Commands\Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RCV\Core\Services\ModuleAutoloader;
use RCV\Core\Services\ModuleNameValidator;

class ModuleSeedCommand extends Command
{
    protected $signature = 'module:seed {module : The name of the module} {--class= : The seeder class to run} {--fresh : Drop all tables and re-run all migrations}';

    protected $description = 'Seed the specific module\'s database seeds';

    public function handle(): int
    {
        $module = $this->argument('module');
        $seederClass = $this->option('class');
        $fresh = $this->option('fresh');

        if (! ModuleNameValidator::isValid($module)) {
            $this->error('Invalid module name. Use letters, numbers, hyphens, and underscores; start with a letter.');

            return self::FAILURE;
        }

        // Check if module exists
        $modulePath = module_path($module);
        if (! File::exists($modulePath)) {
            $this->error("Module '{$module}' does not exist.");

            return self::FAILURE;
        }

        if (! ModuleAutoloader::register($module)) {
            $this->error("Unable to register the autoloader for module [{$module}].");

            return self::FAILURE;
        }

        // Determine seeder class
        if ($seederClass) {
            $fullSeederClass = "Modules\\{$module}\\Database\\Seeders\\{$seederClass}";
        } else {
            $fullSeederClass = "Modules\\{$module}\\Database\\Seeders\\{$module}DatabaseSeeder";
        }

        // Check if seeder class exists
        if (! class_exists($fullSeederClass)) {
            $this->error("Seeder class {$fullSeederClass} not found.");

            return self::FAILURE;
        }

        // Handle fresh option - rollback and re-migrate
        if ($fresh) {
            $this->info("Rolling back migrations for module: {$module}");

            $migrationPath = "Modules/{$module}/Database/Migrations";
            // Check if migration path exists (try common variations)
            $possiblePaths = [
                "Modules/{$module}/Database/Migrations",
                "Modules/{$module}/src/database/migrations",
                "Modules/{$module}/database/migrations",
            ];

            $actualPath = null;
            foreach ($possiblePaths as $path) {
                if (File::exists(base_path($path))) {
                    $actualPath = $path;
                    break;
                }
            }

            if ($actualPath) {
                Artisan::call('migrate:rollback', [
                    '--path' => $actualPath,
                ]);
                $this->line(Artisan::output());

                $this->info("Re-running migrations for module: {$module}");
                Artisan::call('migrate', [
                    '--path' => $actualPath,
                ]);
                $this->line(Artisan::output());
            } else {
                $this->warn("Migration path not found for module: {$module}");
            }
        }

        // Run the seeder
        $this->info("Seeding: {$fullSeederClass}");

        try {
            $status = Artisan::call('db:seed', [
                '--class' => $fullSeederClass,
            ]);

            $output = Artisan::output();
            if ($output) {
                $this->line($output);
            }

            if ($status !== self::SUCCESS) {
                $this->error("Module '{$module}' seeding failed.");

                return self::FAILURE;
            }

            $this->info("Module '{$module}' seeded successfully!");
        } catch (\Throwable $e) {
            $this->error("Failed to seed module '{$module}': ".$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
