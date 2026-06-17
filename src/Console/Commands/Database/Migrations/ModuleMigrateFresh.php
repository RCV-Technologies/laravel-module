<?php

namespace RCV\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use RCV\Core\Console\Commands\Concerns\ConfirmsProduction;

class ModuleMigrateFresh extends Command
{
    use ConfirmsProduction;

    protected $signature = 'module:migrate-fresh {--force : Force the operation to run when in production}';
    protected $description = 'Force drop all tables and re-run all module migrations';

    public function handle()
    {
        if (! $this->confirmToRunInProduction()) {
            return Command::FAILURE;
        }

        $this->info('🔁 Disabling foreign key checks...');
        Schema::disableForeignKeyConstraints();

        $this->info('🗒️ Fetching all tables...');
        $dbName = env('DB_DATABASE');
        $key = "Tables_in_{$dbName}";

        $tables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        if (empty($tables)) {
            $this->info('ℹ️ No tables found to drop.');
        } else {
            foreach ($tables as $table) {
                $tableName = $table->$key;
                DB::statement("DROP TABLE IF EXISTS `$tableName`");
                $this->line("🗑️ Dropped table: $tableName");
            }
        }

        $this->info('✅ All tables dropped.');

        $this->info('🔁 Re-enabling foreign key checks...');
        Schema::enableForeignKeyConstraints();

        // Run module migrations first
        $this->info('🚀 Running module migrations...');
        Artisan::call('module:migrate', ['--force' => true]);
        $this->info(Artisan::output());

        // Optional: Run default Laravel migrations (if any)
        $this->info('📦 Running default Laravel migrations...');
        Artisan::call('migrate', [
            '--path' => 'database/migrations',
            '--force' => true,
        ]);
        $this->info(Artisan::output());

        // Clear caches after all migrations are completed
        $this->info('🧹 Clearing all caches...');
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        $this->info('✅ Module migrations completed successfully.');

        return Command::SUCCESS;
    }
}
