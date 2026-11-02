<?php

namespace RCV\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class MigrateSingleModuleMigration extends Command
{
    protected $signature = 'module:migrate-one {migration_name} {module_name}';
    protected $description = 'Run a specific migration file from a specific module';

    public function handle()
    {
        $migrationName = $this->argument('migration_name');
        $moduleName = $this->argument('module_name');

        $migrationPath = module_path($moduleName, 'src/Database/Migrations');

        
        if (!File::exists($migrationPath)) {
            $this->error(" Module '{$moduleName}' does not exist or has no migrations at: {$migrationPath}");
            return 1;
        }

        $migrationFile = collect(File::files($migrationPath))->first(function ($file) use ($migrationName) {
            return str_contains($file->getFilename(), $migrationName);
        });

        if (!$migrationFile) {
            $this->error(" Migration '{$migrationName}' not found in module '{$moduleName}'.");
            return 1;
        }

        $this->info(" Running migration: {$migrationFile->getFilename()}");


        $fullPath = str_replace('\\', '/', $migrationFile->getPathname());
        $relativePath = str_replace(str_replace('\\', '/', base_path()) . '/', '', $fullPath);


        Artisan::call('migrate', [
            '--path' => $relativePath,
        ]);

  
        $this->line(Artisan::output());

        return 0;
    }
}
