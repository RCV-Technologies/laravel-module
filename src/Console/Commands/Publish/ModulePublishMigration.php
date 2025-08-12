<?php

namespace Rcv\Core\Console\Commands\Publish;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputOption;

class ModulePublishMigration extends Command
{
    protected $signature = 'module:publish-migration {module}';
    protected $description = 'Publish the specified module\'s migration files to the application\'s migrations directory';

    public function handle()
    {
        $module = $this->argument('module');

        $moduleMigrationPath = base_path("Modules/{$module}/src/database/migrations");
        $appMigrationPath = database_path('migrations');

        if (!File::exists($moduleMigrationPath)) {
            $this->error("Migration directory for module '{$module}' does not exist.");
            return 1;
        }

        File::ensureDirectoryExists($appMigrationPath);

        foreach (File::files($moduleMigrationPath) as $file) {
            $destination = $appMigrationPath . '/' . $file->getFilename();
            if (File::exists($destination) && !$this->option('force')) {
                $this->warn("Migration file '{$file->getFilename()}' already exists and will not be overwritten.");
            } else {
                File::copy($file->getRealPath(), $destination);
                $this->info("Migration file '{$file->getFilename()}' published.");
            }
        }

        return 0;
    }

    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Force the publishing of migration files'],
        ];
    }
}
