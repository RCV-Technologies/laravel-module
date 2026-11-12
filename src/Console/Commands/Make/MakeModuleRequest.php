<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleRequest extends Command
{
    protected $signature = 'module:make-request 
                            {name : The name of the request class (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new form request class inside module/src/Http/Requests';

    public function handle()
    {
        // --- Module ---
        $moduleInput = $this->argument('module');
        $module = Str::studly($moduleInput);

        // --- Request class ---
        $requestInput = $this->argument('name');
        $requestParts = preg_split('/[\/\\\\]+/', $requestInput);
        $className = Str::studly(array_pop($requestParts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $requestParts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $requestParts));

        // --- Paths & Namespace ---
        $basePath = module_path($module, 'src/Http/Requests') . ($subPath ? "/{$subPath}" : '');
        $namespace = "Modules\\{$module}\\Http\\Requests" . ($subNamespace ? "\\{$subNamespace}" : '');
        $filePath = "{$basePath}/{$className}.php";

        // --- Check for existing file ---
        if (File::exists($filePath)) {
            $this->error("Request class {$className} already exists in module {$module}.");
            return Command::FAILURE;
        }

        // --- Ensure directory exists ---
        File::ensureDirectoryExists($basePath);

        // --- Load stub ---
        $stubPath = __DIR__ . '/../stubs/module-request.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);

        // --- Replace placeholders ---
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $className],
            $stub
        );

        // --- Write file ---
        File::put($filePath, $content);

        $this->info("Request class {$className} created successfully in module {$module}.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
