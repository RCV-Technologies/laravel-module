<?php

namespace Rcv\Core\Console\Commands\Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ModuleSeedCommand extends Command
{
    protected $signature = 'module:seed {module : The name of the module} {--class= : The seeder class to run} {--fresh : Drop all tables and re-run all migrations}';
    protected $description = 'Seed the specific module\'s database seeds';

    public function handle()
    {
        $module = $this->argument('module');
        $seederClass = $this->option('class');
        $fresh = $this->option('fresh');

        // Check if module exists
        $modulePath = base_path("Modules/{$module}");
        if (!File::exists($modulePath)) {
            $this->error("Module '{$module}' does not exist.");
            return 1;
        }

        // Determine seeder class
        if ($seederClass) {
            $fullSeederClass = "Modules\\{$module}\\Database\\Seeders\\{$seederClass}";
        } else {
            $fullSeederClass = "Modules\\{$module}\\Database\\Seeders\\{$module}DatabaseSeeder";
        }

        // Check if seeder class exists
        if (!class_exists($fullSeederClass)) {
            $this->error("Seeder class {$fullSeederClass} not found.");
            return 1;
        }

        // Handle fresh option - rollback and re-migrate
        if ($fresh) {
            $this->info("Rolling back migrations for module: {$module}");
            
            $migrationPath = "Modules/{$module}/Database/Migrations";
            // Check if migration path exists (try common variations)
            $possiblePaths = [
                "Modules/{$module}/Database/Migrations",
                "Modules/{$module}/src/database/migrations",
                "Modules/{$module}/database/migrations"
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
                    '--path' => $actualPath
                ]);
                $this->line(Artisan::output());
                
                $this->info("Re-running migrations for module: {$module}");
                Artisan::call('migrate', [
                    '--path' => $actualPath
                ]);
                $this->line(Artisan::output());
            } else {
                $this->warn("Migration path not found for module: {$module}");
            }
        }

        // Run the seeder
        $this->info("Seeding: {$fullSeederClass}");
        
        try {
            Artisan::call('db:seed', [
                '--class' => $fullSeederClass
            ]);
            
            $output = Artisan::output();
            if ($output) {
                $this->line($output);
            }
            
            $this->info("Module '{$module}' seeded successfully!");
            
        } catch (\Exception $e) {
            $this->error("Failed to seed module '{$module}': " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
