<?php

namespace Rcv\Core\Console\Commands\Actions;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class ModuleUseCommand extends Command
{
    protected $signature = 'module:use {module : The name of the module to set as active}';
    protected $description = 'Set the specified module as the active module for the current CLI session';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $modulesPath = base_path('Modules');

        if (!File::exists("{$modulesPath}/{$moduleName}")) {
            $this->error("Module '{$moduleName}' does not exist.");
            return 1;
        }

        $this->setActiveModule($moduleName);
        $this->info("Module '{$moduleName}' is now set as the active module.");
        return 0;
    }

    protected function setActiveModule(string $moduleName)
    {
        // Store the active module in the configuration
        Config::set('app.active_module', $moduleName);

        // Update the .env file to persist the active module
        $this->updateEnvFile('ACTIVE_MODULE', $moduleName);
    }

    protected function updateEnvFile($key, $value)
    {
        $path = base_path('.env');
        if (!file_exists($path)) {
            touch($path);
        }

        $oldValue = env($key);
        if (isset($oldValue)) {
            file_put_contents($path, str_replace(
                "{$key}=" . env($key),
                "{$key}={$value}",
                file_get_contents($path)
            ));
        } else {
            file_put_contents($path, file_get_contents($path) . "\n{$key}={$value}");
        }
    }
}
