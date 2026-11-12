<?php

namespace RCV\Core\Console\Commands;

use Illuminate\Console\Command;
use RCV\Core\Services\ModuleManager;

class ModuleCommand extends Command
{
    protected $signature = 'module {action : The action to perform (list|enable|disable)} {name? : The name of the module}';

    protected $description = 'Manage modules';

    protected $moduleManager;

    public function __construct(ModuleManager $moduleManager)
    {
        parent::__construct();
        $this->moduleManager = $moduleManager;
    }

    public function handle()
    {
        $action = $this->argument('action');
        $name = $this->argument('name');

        switch ($action) {
            case 'list':
                $this->listModules();
                break;
            case 'enable':
                $this->enableModule($name);
                break;
            case 'disable':
                $this->disableModule($name);
                break;
            default:
                $this->error("Unknown action: {$action}");

                return 1;
        }

        return 0;
    }

    protected function listModules()
    {
        $modules = $this->moduleManager->getAvailableModules();

        $headers = ['Name', 'Title', 'Description', 'Version', 'Status'];
        $rows = [];

        foreach ($modules as $module) {
            $rows[] = [
                $module['name'],
                $module['title'],
                $module['description'],
                $module['version'],
                $module['enabled'] ? 'Enabled' : 'Disabled',
            ];
        }

        $this->table($headers, $rows);
    }

    protected function enableModule($name)
    {
        if (! $name) {
            $this->error('Module name is required');

            return 1;
        }

        if ($this->moduleManager->enableModule($name)) {
            $this->info("Module [{$name}] enabled successfully");
        } else {
            $this->error("Failed to enable module [{$name}]");

            return 1;
        }
    }

    protected function disableModule($name)
    {
        if (! $name) {
            $this->error('Module name is required');

            return 1;
        }

        if ($this->moduleManager->disableModule($name)) {
            $this->info("Module [{$name}] disabled successfully");
        } else {
            $this->error("Failed to disable module [{$name}]");

            return 1;
        }
    }
}
