<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeChannel extends Command
{
    protected $signature = 'module:make-channel {name : The name of the channel (with optional subdirectory, e.g. Notifications/UserChannel)} {module : The name of the module}';
    protected $description = 'Create a new channel class for the specified module';

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
        $module = Str::studly($this->argument('module'));
        $nameInput = str_replace('\\', '/', $this->argument('name'));

        // Class name + subpath
        $this->className = Str::studly(class_basename($nameInput));
        $subPath = trim(dirname($nameInput), '.');

        // Namespace always under Channels
        $this->namespace = "Modules\\{$module}\\Channels" . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');

        $destinationPath = $this->getDestinationFilePath();
        $contents = $this->getTemplateContents();

        // Prevent overwriting
        if ($this->files->exists($destinationPath)) {
            $this->error("Channel already exists at: {$destinationPath}");
            return static::FAILURE;
        }

        $this->files->ensureDirectoryExists(dirname($destinationPath));
        $this->files->put($destinationPath, $contents);

        $this->info("Channel created: {$destinationPath}");
        return static::SUCCESS;
    }

    protected function getTemplateContents(): string
    {
        $stubPath = __DIR__ . '/../stubs/channel.stub';

        if (! $this->files->exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return '';
        }

        $stub = $this->files->get($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$this->namespace, $this->className],
            $stub
        );
    }

    protected function getDestinationFilePath(): string
    {
        $module = Str::studly($this->argument('module'));
        $nameInput = str_replace('\\', '/', $this->argument('name'));
        $className = Str::studly(class_basename($nameInput));
        $subPath = trim(dirname($nameInput), '.');

        // Always under src/Channels
        $directory = module_path($module, 'src/Channels') . ($subPath ? '/' . $subPath : '');

        return "{$directory}/{$className}.php";
    }
}
