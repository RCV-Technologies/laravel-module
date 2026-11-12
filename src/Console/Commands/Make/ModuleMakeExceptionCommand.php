<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeExceptionCommand extends Command
{
    protected $signature = 'module:make-exception 
                            {name : The exception class name (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new exception class for the specified module';

    public function handle()
    {
        // --- Module ---
        $moduleInput = $this->argument('module');
        $module = Str::studly($moduleInput);
        $modulePath = $module;

        // --- Exception class ---
        $exceptionInput = $this->argument('name');
        $exceptionParts = preg_split('/[\/\\\\]+/', $exceptionInput);
        $className = Str::studly(array_pop($exceptionParts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $exceptionParts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $exceptionParts));

        // --- Paths & Namespace ---
        $basePath = module_path($modulePath, 'src/Exceptions') . ($subPath ? "/{$subPath}" : '');
        $namespace = "Modules\\{$module}\\Exceptions" . ($subNamespace ? "\\{$subNamespace}" : '');
        $filePath = "{$basePath}/{$className}.php";

        // --- Stub ---
        $stubPath = __DIR__ . '/../stubs/exception.stub';
        if (!File::exists($stubPath)) {
            $this->error("Missing stub: {$stubPath}");
            return Command::FAILURE;
        }
        $stub = File::get($stubPath);

        // --- Replace placeholders ---
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $className],
            $stub
        );

        // --- Create directory if missing ---
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

        // --- Check for existing file ---
        if (File::exists($filePath)) {
            $this->error("Exception {$className} already exists in module {$module}.");
            return Command::FAILURE;
        }

        File::put($filePath, $content);

        $this->info("Exception {$className} created successfully in module {$module}.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
