<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModulePolicy extends Command
{
    protected $signature = 'module:make-policy 
                            {name : The policy class name (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new policy class for the specified module';

    public function handle()
    {
        $module = Str::studly($this->argument('module')); // e.g., Blog
        $nameInput = $this->argument('name');             // e.g., Admin/PostPolicy

        // Split class name for subdirectory handling
        $parts = preg_split('/[\/\\\\]+/', $nameInput);
        $className = Str::studly(array_pop($parts)); // Last segment is class
        $subPath = implode('/', array_map([Str::class, 'studly'], $parts)); // path under Policies
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $parts)); // namespace under Policies

        // Path & namespace
        $basePath = module_path($module, 'src/Policies') . ($subPath ? "/{$subPath}" : '');
        $namespace = "Modules\\{$module}\\Policies" . ($subNamespace ? "\\{$subNamespace}" : '');
        $filePath = "{$basePath}/{$className}.php";

        // Check existing file
        if (File::exists($filePath)) {
            $this->error("Policy class {$className} already exists in module {$module}.");
            return Command::FAILURE;
        }

        File::ensureDirectoryExists($basePath);

        // Load stub
        $stubPath = __DIR__ . '/../stubs/policy.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);

        // Replace placeholders
        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );

        File::put($filePath, $content);

        $this->info("Policy class {$className} created successfully in module {$module}.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
