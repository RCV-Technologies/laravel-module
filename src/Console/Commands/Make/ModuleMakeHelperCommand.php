<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeHelperCommand extends Command
{
    protected $signature = 'module:make-helper 
                            {name : The helper class name (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new helper class inside the specified module (in src/Helpers)';

    public function handle()
    {
        // --- Module ---
        $moduleInput = $this->argument('module');
        $module = Str::studly($moduleInput);
        $modulePath = $module;

        // --- Helper ---
        $helperInput = $this->argument('name');
        $helperParts = preg_split('/[\/\\\\]+/', $helperInput);
        $className = Str::studly(array_pop($helperParts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $helperParts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $helperParts));

        // --- Paths & Namespace ---
        $basePath = module_path($modulePath, 'src/Helpers') . ($subPath ? "/{$subPath}" : '');
        $namespace = "Modules\\{$module}\\Helpers" . ($subNamespace ? "\\{$subNamespace}" : '');
        $filePath = "{$basePath}/{$className}.php";

        // --- Stub ---
        $stubPath = __DIR__ . '/../stubs/helper.stub';
        if (!File::exists($stubPath)) {
            $this->error("Missing stub file at: {$stubPath}");
            return Command::FAILURE;
        }
        $stub = File::get($stubPath);

        // --- Replace placeholders ---
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $className],
            $stub
        );

        // --- Create directories if missing ---
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

        // --- Check for existing file ---
        if (File::exists($filePath)) {
            $this->error("Helper {$className} already exists in module {$module}.");
            return Command::FAILURE;
        }

        File::put($filePath, $content);

        $this->info("Helper {$className} created successfully in module {$module}.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
