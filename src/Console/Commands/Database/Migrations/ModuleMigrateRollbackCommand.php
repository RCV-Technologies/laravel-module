<?php

namespace Rcv\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ModuleMigrateRollbackCommand extends Command
{
    protected $signature = 'module:migrate-rollback {--module= : The name of the module to rollback} {--step=1 : Number of migrations to rollback} {--force : Force rollback without confirmation}';

    protected $description = 'Rollback migrations for a specific module or all modules';

    public function handle()
    {
        $module = $this->option('module');

        if ($module) {
            $this->rollbackModule($module);
        } else {
            if (!$this->option('force') && !$this->confirm('Are you sure you want to rollback migrations for ALL modules?')) {
                $this->info('Operation cancelled.');
                return;
            }
            $this->rollbackAllModules();
        }
    }

    protected function rollbackModule(string $module)
    {
        // Check both possible path cases
        $pathLower = base_path("Modules/{$module}/src/database/migrations");
        $pathUpper = base_path("Modules/{$module}/src/Database/Migrations");
        
        $migrationPath = null;
        if (File::exists($pathLower)) {
            $migrationPath = "Modules/{$module}/src/database/migrations";
        } elseif (File::exists($pathUpper)) {
            $migrationPath = "Modules/{$module}/src/Database/migrations";
        }

        if (!$migrationPath) {
            $this->error("Migrations path not found for module: {$module}");
            $this->line("Checked paths:");
            $this->line("- {$pathLower}");
            $this->line("- {$pathUpper}");
            return false;
        }

        // Check if there are any migration files
        $migrationFiles = File::glob(base_path($migrationPath) . '/*.php');
        if (empty($migrationFiles)) {
            $this->warn("No migration files found for module: {$module}");
            return true;
        }

        $this->info("Rolling back migrations for module: {$module}");
        $this->line("Migration path: {$migrationPath}");

        try {
            $exitCode = Artisan::call('migrate:rollback', [
                '--path' => $migrationPath,
                '--step' => $this->option('step'),
                '--force' => true, // Prevent interactive prompts in commands
            ]);

            $output = Artisan::output();
            
            if ($exitCode === 0) {
                $this->line($output);
                $this->info("✓ Successfully rolled back migrations for module: {$module}");
            } else {
                $this->error("✗ Failed to rollback migrations for module: {$module}");
                $this->line($output);
            }

            return $exitCode === 0;
        } catch (\Exception $e) {
            $this->error("Exception occurred while rolling back module {$module}: " . $e->getMessage());
            return false;
        }
    }

    protected function rollbackAllModules()
    {
        $modulesPath = base_path('Modules');

        if (!File::exists($modulesPath)) {
            $this->error("Modules directory not found: {$modulesPath}");
            return;
        }

        $modules = File::directories($modulesPath);

        if (empty($modules)) {
            $this->warn("No modules found in: {$modulesPath}");
            return;
        }

        $this->info("Found " . count($modules) . " modules to process...");
        
        $successful = 0;
        $failed = 0;

        foreach ($modules as $modulePath) {
            $module = basename($modulePath);
            $this->newLine();
            
            if ($this->rollbackModule($module)) {
                $successful++;
            } else {
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Rollback summary:");
        $this->line("✓ Successful: {$successful}");
        if ($failed > 0) {
            $this->line("✗ Failed: {$failed}");
        }
    }
}

