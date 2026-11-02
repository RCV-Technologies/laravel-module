<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ModuleRouteProviderMakeCommand extends Command
{
    protected $signature = 'module:make-route-provider {module} {--force}';

    protected $description = 'Create a RouteServiceProvider for a given module.';

    public function handle(): int
    {
        $module = Str::studly($this->argument('module'));

        $filePath = module_path($module, 'src/Providers/RouteServiceProvider.php');

        if (File::exists($filePath) && ! $this->option('force')) {
            $this->error("RouteServiceProvider already exists at: {$filePath}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($filePath));

        $stub = File::get(__DIR__.'/../stubs/route-provider.stub');
        $content = str_replace(
            ['{{ module }}'],
            [$module],
            $stub
        );
        File::put($filePath, $content);

        $this->info("RouteServiceProvider created at: {$filePath}");

        return self::SUCCESS;
    }

    protected function getArguments()
    {
        return [
            ['module', InputArgument::REQUIRED, 'The name of the module.'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Force overwrite if the file already exists.'],
        ];
    }
}
