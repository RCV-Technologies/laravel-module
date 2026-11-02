<?php

namespace RCV\Core\Console\Commands;

use Illuminate\Console\Command;
use RCV\Core\Services\ModuleAutoloader;
use RCV\Core\Services\ModuleManager;

class ModuleAutoloadCommand extends Command
{
    protected $signature = 'module:autoload';

    protected $description = 'Register enabled module namespaces for the current application process';

    public function handle(): int
    {
        $modules = app(ModuleManager::class)->getEnabledModules();

        foreach ($modules as $module) {
            if (ModuleAutoloader::register($module)) {
                $this->info("Registered runtime autoloading for [{$module}].");
            }
        }

        $this->info('Enabled module namespaces are registered for this application process.');

        return self::SUCCESS;
    }
}
