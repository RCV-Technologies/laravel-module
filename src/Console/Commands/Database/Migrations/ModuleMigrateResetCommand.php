<?php

namespace RCV\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RCV\Core\Console\Commands\Concerns\ConfirmsProduction;

class ModuleMigrateResetCommand extends Command
{
    use ConfirmsProduction;

    protected $signature = 'module:migrate-reset {--force : Force the operation to run when in production}';
    protected $description = 'Reset the modules migrations.';

    public function handle()
    {
        if (! $this->confirmToRunInProduction()) {
            return Command::FAILURE;
        }

        $modulesPath = base_path('Modules');
        $modules = File::directories($modulesPath);

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            $migrationPath = $modulePath . '/Database/Migrations';

            $this->line("Checking: $migrationPath");

            if (File::exists($migrationPath) && count(File::files($migrationPath)) > 0) {
                $this->info("Resetting migrations for module: {$moduleName}");

                
                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $migrationPath);
                $relativePath = str_replace('\\', '/', $relativePath);

                Artisan::call('migrate:reset', [
                    '--path' => $relativePath,
                    '--force' => true,
                ]);

                $this->line(Artisan::output());
            } else {
                $this->warn("No migrations found for module: {$moduleName}");
            }
        }

        $this->info('All module migrations have been reset.');

        return Command::SUCCESS;
    }
}
