<?php

namespace RCV\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RCV\Core\Services\ModuleNameValidator;

class DiscoverModulesCommand extends Command
{
    protected $signature = 'module:discover';
    protected $description = 'Compile a class that registers all discovered modules.';

    public function handle()
    {
        $modulesPath = base_path('Modules');
        $compiledPath = base_path('bootstrap/cache/modules.php');

        $modules = [];

        foreach (File::directories($modulesPath) as $modulePath) {
            $moduleName = basename($modulePath);
            if (! ModuleNameValidator::isValid($moduleName)) {
                continue;
            }

            $studlyName = Str::studly($moduleName);
            $serviceProvider = "Modules\\{$studlyName}\\Providers\\{$studlyName}ServiceProvider";

            if (File::exists("$modulePath/Providers/{$studlyName}ServiceProvider.php")
                || File::exists("$modulePath/src/Providers/{$studlyName}ServiceProvider.php")) {
                $modules[$moduleName] = $serviceProvider;
            }
        }

        $stub = File::get(__DIR__.'/stubs/module_compiled.stub');

        $compiledClass = str_replace(
            '{{modules}}',
            var_export($modules, true),
            $stub
        );

        File::ensureDirectoryExists(dirname($compiledPath));
        $temporaryPath = $compiledPath.'.tmp.'.getmypid();
        if (File::put($temporaryPath, $compiledClass) === false) {
            throw new \RuntimeException('Unable to write the compiled module manifest.');
        }
        if (! @rename($temporaryPath, $compiledPath)) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to publish the compiled module manifest.');
        }

        $this->info('Modules discovered and compiled successfully!');
    }
}
