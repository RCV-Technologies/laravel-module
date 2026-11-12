<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeEventCommand extends Command
{
    protected $signature = 'module:make-event 
                            {name : The event class name (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new event class for the specified module';

    public function handle()
    {
        // --- Module ---
        $moduleInput = $this->argument('module');
        $module = Str::studly($moduleInput);
        $modulePath = $module;

        // --- Event class ---
        $eventInput = $this->argument('name');
        $eventParts = preg_split('/[\/\\\\]+/', $eventInput);
        $className = Str::studly(array_pop($eventParts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $eventParts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $eventParts));

        // --- Paths & Namespace ---
        $basePath = module_path($modulePath, 'src/Events') . ($subPath ? "/{$subPath}" : '');
        $namespace = "Modules\\{$module}\\Events" . ($subNamespace ? "\\{$subNamespace}" : '');
        $filePath = "{$basePath}/{$className}.php";

        // --- Stub ---
        $stubPath = __DIR__ . '/../stubs/event.stub';
        if (!File::exists($stubPath)) {
            $this->error("Missing stub file at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);

        // --- Replace placeholders ---
        $stub = str_replace(
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
            $this->error("Event {$className} already exists in module {$module}.");
            return Command::FAILURE;
        }

        File::put($filePath, $stub);

        $this->info("Event {$className} created successfully in module {$module}.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
