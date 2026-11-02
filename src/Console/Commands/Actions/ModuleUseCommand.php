<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use RCV\Core\Services\ModuleNameValidator;

class ModuleUseCommand extends Command
{
    protected $signature = 'module:use {module : The name of the module to set as active}';

    protected $description = 'Set the specified module as the active module for the current CLI session';

    public function handle()
    {
        $moduleName = $this->argument('module');
        if (! ModuleNameValidator::isValid($moduleName)) {
            $this->error('Invalid module name. Use letters, numbers, hyphens, and underscores; start with a letter.');

            return 1;
        }

        $modulesPath = base_path('Modules');

        if (! File::exists("{$modulesPath}/{$moduleName}")) {
            $this->error("Module '{$moduleName}' does not exist.");

            return 1;
        }

        $this->setActiveModule($moduleName);
        $this->info("Module '{$moduleName}' is now set as the active module.");

        return 0;
    }

    protected function setActiveModule(string $moduleName): void
    {
        Config::set('app.active_module', $moduleName);
    }
}
