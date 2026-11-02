<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleRule extends Command
{
    protected $signature = 'module:make-rule 
                            {name : The validation rule class name (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new validation rule for the specified module';

    public function handle()
    {
        // --- Module ---
        $moduleInput = $this->argument('module');
        $module = Str::studly($moduleInput);

        // --- Rule class ---
        $ruleInput = $this->argument('name');
        $ruleParts = preg_split('/[\/\\\\]+/', $ruleInput);
        $className = Str::studly(array_pop($ruleParts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $ruleParts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $ruleParts));

        // --- Paths & Namespace ---
        $basePath = module_path($module, 'src/Rules') . ($subPath ? "/{$subPath}" : '');
        $namespace = "Modules\\{$module}\\Rules" . ($subNamespace ? "\\{$subNamespace}" : '');
        $filePath = "{$basePath}/{$className}.php";

        // --- Stub ---
        $stubPath = __DIR__ . '/../stubs/rule.stub';
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

        // --- Create directories if missing ---
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

        // --- Check for existing file ---
        if (File::exists($filePath)) {
            $this->error("Validation rule {$className} already exists in module {$module}.");
            return Command::FAILURE;
        }

        File::put($filePath, $content);

        $this->info("Validation rule {$className} created successfully in module {$module}.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
