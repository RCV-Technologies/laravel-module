<?php

namespace Rcv\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleMigrateCommand extends Command
{
    protected $signature = 'module:migrate 
                            {module? : The name of the module} 
                            {--migration= : Run a specific migration file} 
                            {--force : Force the migration even if it was already run}';

    protected $description = 'Run migrations for all modules, a specific module, or a single migration file within a module';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $migration = $this->option('migration');
        $force = $this->option('force');

        if ($migration && $moduleName) {
            $this->migrateSingleMigration($moduleName, $migration, $force);
        } elseif ($moduleName) {
            $this->migrateModule($moduleName, $force);
        } else {
            $this->migrateAllModules($force); // <-- this was missing before
        }
    }

    protected function migrateAllModules(bool $force = false): void
    {
        $this->info("Running migrations for all modules...");

        $modulesPath = base_path('Modules');

        if (!File::exists($modulesPath)) {
            $this->warn("Modules directory not found: {$modulesPath}");
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            $this->migrateModule($moduleName, $force);
        }

        $this->info("All module migrations completed.");
    }

    protected function migrateModule(string $moduleName, bool $force = false): void
    {
        $this->info("Migrating module [{$moduleName}]...");

        $migrationPath = base_path("Modules/{$moduleName}/src/Database/Migrations");

        if (!File::exists($migrationPath)) {
            $this->warn("No migration files found in [{$migrationPath}]");
            return;
        }

        $this->ensureMigrationsTableExists();

        $files = File::glob("{$migrationPath}/*.php");

        foreach ($files as $migration) {
            $migrationName = pathinfo($migration, PATHINFO_FILENAME);

            $exists = DB::table('migrations')->where('migration', $migrationName)->exists();

            if (!$exists || $force) {
                $this->runMigration($migration, $migrationName, $force);
            } else {
                $this->info("Migration {$migrationName} already exists");
            }
        }
    }

    protected function migrateSingleMigration(string $moduleName, string $migrationFileName, bool $force = false): void
    {
        $this->info("Migrating single migration [{$migrationFileName}] in module [{$moduleName}]...");

        $migrationPath = base_path("Modules/{$moduleName}/src/Database/Migrations/{$migrationFileName}.php");

        if (!File::exists($migrationPath)) {
            $this->error("Migration file not found: {$migrationPath}");
            return;
        }

        $this->ensureMigrationsTableExists();

        $migrationName = pathinfo($migrationPath, PATHINFO_FILENAME);

        $exists = DB::table('migrations')->where('migration', $migrationName)->exists();

        if (!$exists || $force) {
            $this->runMigration($migrationPath, $migrationName, $force);
        } else {
            $this->info("Migration {$migrationName} already exists");
        }
    }

    protected function ensureMigrationsTableExists(): void
    {
        if (!Schema::hasTable('migrations')) {
            Schema::create('migrations', function ($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    protected function runMigration(string $migrationPath, string $migrationName, bool $force = false): void
    {
        try {
            require_once $migrationPath;

            $migrationClass = require $migrationPath;

            if (!is_object($migrationClass)) {
                throw new \Exception("Invalid migration class in file: {$migrationPath}");
            }

            $migrationClass->up();

            DB::table('migrations')->insert([
                'migration' => $migrationName,
                'batch' => DB::table('migrations')->max('batch') + 1
            ]);

            $this->info("Migration {$migrationName} completed successfully");
        } catch (\Exception $e) {
            $this->error("Error running migration {$migrationName}: " . $e->getMessage());
            if (!$force) {
                throw $e;
            }
        }
    }
}
