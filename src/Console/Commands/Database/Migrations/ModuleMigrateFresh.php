<?php

namespace RCV\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RCV\Core\Services\CacheManager;
use RCV\Core\Services\ModuleManager;

class ModuleMigrateFresh extends Command
{
    use ConfirmableTrait;

    protected $signature = 'module:migrate-fresh
                            {--force : Force the operation to run when in production}';

    protected $description = 'Drop ALL tables in the default database connection, then re-run module and application migrations';

    public function handle(): int
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $this->warn("This will drop ALL tables in the [{$connection}] connection ({$database}), not only module tables.");

        if (! $this->confirmToProceed('Every table and all data in this database will be deleted.')) {
            return self::FAILURE;
        }

        $this->info('Dropping all tables...');
        Schema::dropAllTables();
        $this->info('All tables dropped.');

        // Application and package migrations first (includes the module_states table),
        // so module migrations can rely on core tables such as users.
        $this->info('Running application migrations...');
        $appExitCode = Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());

        $this->info('Running module migrations...');
        $moduleExitCode = Artisan::call('module:migrate', ['--force' => true]);
        $this->line(Artisan::output());

        app(CacheManager::class)->invalidateAll();
        app(ModuleManager::class)->refreshEnabledModulesCache();

        if ($moduleExitCode !== self::SUCCESS || $appExitCode !== self::SUCCESS) {
            $this->error('One or more migration steps failed. Check the output above.');

            return self::FAILURE;
        }

        $this->info('Module migrations completed successfully.');

        return self::SUCCESS;
    }
}
