<?php

namespace RCV\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RCV\Core\Services\CacheManager;
use RCV\Core\Services\ModuleManager;

class ModuleMigrateFresh extends Command
{
    protected $signature = 'module:migrate-fresh';

    protected $description = 'Force drop all tables and re-run all module migrations';

    public function handle()
    {
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

        $this->info('🧹 Invalidating RCV Core cache entries...');
        app(CacheManager::class)->invalidateAll();
        app(ModuleManager::class)->refreshEnabledModulesCache();

        $this->info('✅ Module migrations completed successfully.');
    }
}
