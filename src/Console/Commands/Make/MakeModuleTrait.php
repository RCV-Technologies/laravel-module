<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleTrait extends Command
{
    protected $signature = 'module:make-trait 
                            {name : The trait class name (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new trait class for the specified module';

    public function handle()
    {
        // --- Module ---
        $moduleInput = $this->argument('module');
        $module = Str::studly($moduleInput);

        // --- Trait class ---
        $traitInput = $this->argument('name');
        $traitParts = preg_split('/[\/\\\\]+/', $traitInput);
        $className = Str::studly(array_pop($traitParts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $traitParts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $traitParts));

        // --- Paths & Namespace ---
        $basePath = module_path($module, 'src/Traits') . ($subPath ? "/{$subPath}" : '');
        $namespace = "Modules\\{$module}\\Traits" . ($subNamespace ? "\\{$subNamespace}" : '');
        $filePath = "{$basePath}/{$className}.php";

        // --- Stub ---
        $stubPath = __DIR__ . '/../stubs/trait.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);

        // --- Replace placeholders ---
        $content = str_replace(
            ['{{namespace}}', '{{trait}}'],
            [$namespace, $className],
            $stub
        );

        // --- Create directories if missing ---
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

        // --- Check for existing file ---
        if (File::exists($filePath)) {
            $this->error("Trait {$className} already exists in module {$module}.");
            return Command::FAILURE;
        }

        File::put($filePath, $content);

        $this->info("Trait {$className} created successfully in module {$module}.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
