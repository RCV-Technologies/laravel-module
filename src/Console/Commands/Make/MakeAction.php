<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeAction extends Command
{
    protected $signature = 'module:make-action
                            {name : The name of the action class (with optional subdirectory, e.g. User/CreateUserAction)}
                            {module : The name of the module}';

    protected $description = 'Create a new action class for the specified module';

    protected Filesystem $files;
    protected string $namespace;
    protected string $className;

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem();
    }

    public function handle(): int
    {
        $module = $this->getModuleName();
        $nameInput = str_replace('\\', '/', $this->argument('name')); // normalize slashes

        // Extract class name + subdirectory
        $this->className = Str::studly(class_basename($nameInput));
        $subPath = trim(dirname($nameInput), '.');

        // Build namespace
        $this->namespace = "Modules\\{$module}\\Actions" . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');

        // Destination path
        $destinationPath = $this->getDestinationFilePath($module, $nameInput);

        // Prevent overwrite
        if ($this->files->exists($destinationPath)) {
            $this->error("Action class '{$this->className}' already exists in module '{$module}' at [{$destinationPath}]!");
            return static::FAILURE;
        }

        $this->files->ensureDirectoryExists(dirname($destinationPath));
        $this->files->put($destinationPath, $this->getTemplateContents());

        $this->info("Action class '{$this->className}' created successfully in module '{$module}'");
        $this->line("→ Path: {$destinationPath}");
        $this->line("→ Namespace: {$this->namespace}");

        return static::SUCCESS;
    }

    protected function getTemplateContents(): string
    {
        $stubPath = __DIR__ . '/../stubs/action.stub';

        if (! $this->files->exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            exit(1);
        }

        $stub = $this->files->get($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$this->namespace, $this->className],
            $stub
        );
    }

    protected function getDestinationFilePath(string $module, string $nameInput): string
    {
        $subPath = trim(dirname($nameInput), '.');
        $className = Str::studly(class_basename($nameInput));

        $directory = module_path($module, 'src/Actions') . ($subPath ? '/' . $subPath : '');

        return "{$directory}/{$className}.php";
    }

    protected function getModuleName(): string
    {
        return Str::studly($this->argument('module'));
    }
}
