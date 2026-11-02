<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeResourceCommand extends Command
{
    protected $signature = 'module:make-resource {name : The name of the resource} {module : The name of the module} {--collection : Create a resource collection}';

    protected $description = 'Create a new resource class for the specified module';

    public function handle(): int
    {
        $path = $this->getDestinationFilePath();

        if (File::exists($path)) {
            $this->error("Resource already exists: {$path}");

            return self::FAILURE;
        }

        $contents = $this->getTemplateContents();

        // Ensure directory exists
        $dir = dirname($path);
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, $contents);

        $resourceType = $this->isCollection() ? 'Resource Collection' : 'Resource';
        $this->info("{$resourceType} created: {$path}");

        return self::SUCCESS;
    }

    protected function getTemplateContents(): string
    {
        $module = $this->getModuleName();

        // Handle subdirectory
        $nameParts = explode('/', $this->argument('name'));
        $className = Str::studly(array_pop($nameParts));

        $stubPath = $this->getStubPath();

        if (! File::exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            exit(1);
        }

        $stub = File::get($stubPath);

        return str_replace(
            ['{{ module }}', '{{ class }}'],
            [$module, $className],
            $stub
        );
    }

    protected function getDestinationFilePath(): string
    {
        $module = $this->getModuleName();

        // Handle subdirectory
        $nameParts = explode('/', $this->argument('name'));
        $className = Str::studly(array_pop($nameParts));
        $subDir = implode('/', $nameParts);

        $basePath = module_path($module, 'src/Http/Transformers');
        if ($subDir) {
            $basePath .= "/{$subDir}";
        }

        return "{$basePath}/{$className}.php";
    }

    protected function getModuleName(): string
    {
        return Str::studly($this->argument('module'));
    }

    protected function isCollection(): bool
    {
        return $this->option('collection') || Str::endsWith($this->argument('name'), 'Collection');
    }

    protected function getStubPath(): string
    {
        $stubName = $this->isCollection() ? 'resource-collection.stub' : 'resource.stub';

        return __DIR__."/../stubs/{$stubName}";
    }
}
