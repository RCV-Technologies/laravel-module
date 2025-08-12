<?php

namespace Rcv\Core\Console\Commands\Publish;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;


class ModulePublishConfig extends Command
{
    protected $signature = 'module:publish-config {module}';
    protected $description = 'Publish the specified module\'s configuration files to the application\'s config directory';

    public function handle()
    {
        $module = $this->argument('module') ?? $this->ask('Please provide the module name');

        $moduleConfigPath = base_path("Modules/{$module}/src/Config");
        $appConfigPath = config_path("{$module}");

        if (!File::exists($moduleConfigPath)) {
            $this->error("Configuration directory for module '{$module}' does not exist.");
            return 1;
        }

        File::ensureDirectoryExists($appConfigPath);

        foreach (File::files($moduleConfigPath) as $file) {
            $destination = $appConfigPath . '/' . $file->getFilename();
            if (File::exists($destination) && !$this->option('force')) {
                $this->warn("Config file '{$file->getFilename()}' already exists and will not be overwritten.");
            } else {
                File::copy($file->getRealPath(), $destination);
                $this->info("Config file '{$file->getFilename()}' published.");
            }
        }

        return 0;
    }

    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Force the publishing of config files'],
        ];
    }
}
