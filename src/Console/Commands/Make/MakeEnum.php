<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeEnum extends Command
{
    protected $signature = 'module:make-enum {name : The name of the enum (with optional subdirectory, e.g. Statuses/UserRole)} {module : The name of the module}';
    protected $description = 'Create a new enum class inside the specified module';

    protected string $namespace;
    protected string $enumName;

    public function handle(): int
    {
        $module = Str::studly($this->argument('module'));
        $nameInput = str_replace('\\', '/', $this->argument('name'));

        // Extract class name
        $this->enumName = Str::studly(class_basename($nameInput));

        // Subdirectories under Enums
        $subPath = trim(dirname($nameInput), '.');
        $this->namespace = "Modules\\{$module}\\Enums" . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');

        $destinationPath = $this->getDestinationFilePath();
        $contents = $this->getTemplateContents();

        if (File::exists($destinationPath)) {
            $this->error("Enum already exists at: {$destinationPath}");
            return static::FAILURE;
        }

        File::ensureDirectoryExists(dirname($destinationPath));
        File::put($destinationPath, $contents);

        $this->info("Enum created at: {$destinationPath}");
        return static::SUCCESS;
    }

    protected function getTemplateContents(): string
    {
        $stubPath = __DIR__ . '/../stubs/enum.stub';

        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return '';
        }

        $stub = File::get($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$this->namespace, $this->enumName],
            $stub
        );
    }

    protected function getDestinationFilePath(): string
    {
        $module = Str::studly($this->argument('module'));
        $nameInput = str_replace('\\', '/', $this->argument('name'));

        $enumName = Str::studly(class_basename($nameInput));
        $subPath = trim(dirname($nameInput), '.');

        // Always put enums inside `src/Enums/...`
        $directory = module_path($module, 'src/Enums') . ($subPath ? '/' . $subPath : '');

        return "{$directory}/{$enumName}.php";
    }
}
