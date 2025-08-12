<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Rcv\Core\Services\ModuleMiddlewareManager;

use Illuminate\Support\Facades\File;

class ModuleMiddlewareCommand extends Command
{
     protected $signature = 'module:make-middleware 
        {name : The name of the middleware class} 
        {module : The name of the module}';

    protected $description = 'Create a new middleware class in the specified module';

    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->argument('module');

        // Correct path to the middleware folder inside module
        $middlewarePath = base_path("Modules/{$module}/src/Http/Middleware");
        $filePath = "{$middlewarePath}/{$name}.php";

        if (!is_dir($middlewarePath)) {
            mkdir($middlewarePath, 0755, true);
        }

        if (file_exists($filePath)) {
            $this->error("Middleware already exists at: {$filePath}");
            return 1;
        }

        // Path to the stub file within the package
        $stubPath = __DIR__ . '/../stubs/middleware.stub';
        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return 1;
        }

        // PSR-4 namespace for module middleware
        $namespace = "Modules\\{$module}\\Http\\Middleware";

        // Load and replace stub content
        $stub = file_get_contents($stubPath);
        $stub = str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, $name], $stub);
        file_put_contents($filePath, $stub);

        $this->info("Middleware created at: {$filePath}");
        return 0;
    }
} 