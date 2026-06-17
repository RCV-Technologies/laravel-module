<?php

namespace RCV\Core\Console\Commands\Database\Migrations;

use Illuminate\Console\Command;
use RCV\Core\Console\Commands\Concerns\ConfirmsProduction;

class MigrateRefresh extends Command
{
    use ConfirmsProduction;

    protected $signature = 'module:migrate-refresh {--force : Force the operation to run when in production}';

    protected $description = 'Rollback and re-run all module migrations';

    public function handle()
    {
        if (! $this->confirmToRunInProduction()) {
            return Command::FAILURE;
        }

        $this->info('Rolling back all module migrations...');
        $this->call('module:migrate-reset', ['--force' => true]);

        $this->info('Re-running all module migrations...');
        $this->call('module:migrate', ['--force' => true]);

        $this->info('Module migrations refreshed successfully.');

        return Command::SUCCESS;
    }
}
