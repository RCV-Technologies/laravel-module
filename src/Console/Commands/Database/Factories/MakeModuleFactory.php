<?php

namespace Rcv\Core\Console\Commands\Database\Factories;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeModuleFactory extends Command
{
    protected $signature = 'module:make-factory 
                            {name : The name of the factory}
                            {module : The name of the module}';

    protected $description = 'Create a new model factory inside a module\'s src/Database/Factories directory';

    protected Filesystem $files;

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
    }

    public function handle()
    {
        $module = $this->argument('module');
        $name = $this->argument('name');

        // Updated path to match your structure
        $factoryPath = base_path("Modules/{$module}/src/Database/Factories");

        if (! $this->files->isDirectory($factoryPath)) {
            $this->files->makeDirectory($factoryPath, 0755, true);
        }

        $factoryClassName = $this->qualifyClassName($name);
        $filePath = $factoryPath . '/' . $factoryClassName . '.php';

        if ($this->files->exists($filePath)) {
            $this->error("Factory '{$factoryClassName}' already exists in module '{$module}'.");
            return 1;
        }

        $stub = $this->getStub();

        $stub = str_replace(
            ['{{ class_name }}', '{{ model_namespace }}', '{{ module_name }}'],
            [$factoryClassName, $this->guessModelName($factoryClassName), $module],
            $stub
        );

        $this->files->put($filePath, $stub);
        $this->info("Factory created successfully: {$filePath}");
        return 0;
    }

protected function getStub(): string
{
    $stubPath = base_path('vendor/rcv/core/src/Console/Commands/stubs/factory.stub');

    if (!file_exists($stubPath)) {
        throw new \RuntimeException("Stub file not found at: {$stubPath}");
    }

    return file_get_contents($stubPath);
}




    protected function qualifyClassName(string $name): string
    {
        return ucfirst($name);
    }

    protected function guessModelName(string $factoryName): string
    {
        return str_ends_with($factoryName, 'Factory')
            ? substr($factoryName, 0, -7)
            : $factoryName;
    }
}
