<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleObserver extends Command
{
    protected $signature = 'module:make-observer {module} {name}';
    protected $description = 'Create a new observer for the specified module';

    public function handle()
    {
        $module = $this->argument('module'); // e.g. Blog
        $name = $this->argument('name');     // e.g. PostObserver

        $className = Str::studly(class_basename($name));
        $directory = base_path("Modules/{$module}/src/Observers");
        $filePath = "{$directory}/{$className}.php";

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($filePath)) {
            $this->error("Observer already exists: {$filePath}");
            return 1;
        }

         $stubPath = __DIR__ . '/../stubs/observer.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return;
        }

        $stub = file_get_contents($stubPath);
        $namespace = "Modules\\{$module}\\Observers";

        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );

        File::put($filePath, $content);
        $this->info("Observer created: {$filePath}");
        return 0;
    }
}
