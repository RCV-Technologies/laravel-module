<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleClass extends Command
{
    protected $signature = 'module:make-class 
                            {name : The class name (can include subdirectories)} 
                            {module : The module name}';
    protected $description = 'Create a new class using a stub file inside the specified module';

    public function handle()
    {
        $module = Str::studly($this->argument('module'));    // e.g., Test
        $nameInput = $this->argument('name');                // e.g., services/NotificationService

        // --- Handle subdirectories in class name ---
        $parts = preg_split('/[\/\\\\]+/', $nameInput);
        $className = Str::studly(array_pop($parts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $parts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $parts));

        // --- Paths & Namespace ---
        $moduleBasePath = module_path($module);
        if (!File::exists($moduleBasePath)) {
            $this->error("Module '{$module}' does not exist.");
            return Command::FAILURE;
        }

        $directory = $moduleBasePath . "/src/Class" . ($subPath ? "/{$subPath}" : '');
        $filePath = "{$directory}/{$className}.php";
        $namespace = "Modules\\{$module}\\Class" . ($subNamespace ? "\\{$subNamespace}" : '');

        if (File::exists($filePath)) {
            $this->error("Class already exists: {$filePath}");
            return Command::FAILURE;
        }

        File::ensureDirectoryExists($directory);

        // --- Load stub ---
        $stubPath = __DIR__ . '/../stubs/class.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);

        // --- Replace placeholders ---
        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );

        File::put($filePath, $content);

        $this->info("Class '{$className}' created successfully in module '{$module}'.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
