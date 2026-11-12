<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeCastCommand extends Command
{
    protected $signature = 'module:make-cast 
                            {name : The name of the cast class (with optional subdirectory, e.g. Custom/FormatDate)} 
                            {module : The name of the module}';

    protected $description = 'Create a new Eloquent cast class for the specified module';

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
        $module = Str::studly($this->argument('module')); // e.g. Blog
        $nameInput = str_replace('\\', '/', $this->argument('name')); // normalize path

        // Extract class name + subdirectory
        $this->className = Str::studly(class_basename($nameInput));
        $subPath = trim(dirname($nameInput), '.');

        // Namespace always under Casts
        $this->namespace = "Modules\\{$module}\\Casts" . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');

        $destinationPath = $this->getDestinationFilePath($module, $nameInput);

        // Prevent overwriting
        if ($this->files->exists($destinationPath)) {
            $this->error("Cast class '{$this->className}' already exists in module '{$module}' at [{$destinationPath}]!");
            return static::FAILURE;
        }

        $this->files->ensureDirectoryExists(dirname($destinationPath));
        $this->files->put($destinationPath, $this->getStubContents());

        // ✅ Show exact path after generation
        $this->info("Cast class '{$this->className}' created successfully in module '{$module}' at [{$destinationPath}]");

        return static::SUCCESS;
    }

    protected function getStubContents(): string
    {
        $stubPath = __DIR__ . '/../stubs/cast.stub';

        if (! $this->files->exists($stubPath)) {
            $this->error("Stub file not found at {$stubPath}");
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

        $directory = module_path($module, 'src/Casts') . ($subPath ? '/' . $subPath : '');

        return "{$directory}/{$className}.php";
    }
}
