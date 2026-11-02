<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleObserver extends Command
{
    protected $signature = 'module:make-observer 
                            {name : The observer class name (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new observer for the specified module';

    public function handle()
    {
        $module = Str::studly($this->argument('module'));   // e.g., Blog
        $nameInput = $this->argument('name');               // e.g., Admin/PostObserver

        // Split name to handle subdirectories
        $parts = preg_split('/[\/\\\\]+/', $nameInput);
        $className = Str::studly(array_pop($parts));       // Last part is class
        $subPath = implode('/', array_map([Str::class, 'studly'], $parts));       // path under Observers
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $parts)); // namespace under Observers

        // Construct paths
        $basePath = module_path($module, 'src/Observers') . ($subPath ? "/{$subPath}" : '');
        $filePath = "{$basePath}/{$className}.php";
        $namespace = "Modules\\{$module}\\Observers" . ($subNamespace ? "\\{$subNamespace}" : '');

        // Check for existing file
        if (File::exists($filePath)) {
            $this->error("Observer {$className} already exists in module {$module}.");
            return Command::FAILURE;
        }

        File::ensureDirectoryExists($basePath);

        // Load stub
        $stubPath = __DIR__ . '/../stubs/observer.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);

        // Replace namespace & class placeholders
        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );

        File::put($filePath, $content);

        $this->info("Observer {$className} created successfully in module {$module}.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
