<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleClass extends Command
{
    protected $signature = 'module:make-class {module} {name}';
    protected $description = 'Create a new class using a stub file inside the specified module';

    public function handle()
    {
        $module = $this->argument('module');     // e.g., Test
        $classInput = $this->argument('name');  // e.g., services/notificationservice

        $classPath = str_replace('\\', '/', $classInput);
        $className = Str::studly(class_basename($classPath));
        $subPath = dirname($classPath) !== '.' ? dirname($classPath) : '';
        $namespace = "Modules\\{$module}" . ($subPath ? '\\' . str_replace('/', '\\', $subPath) : '');

        $directory = base_path("Modules/{$module}/src" . ($subPath ? '/' . $subPath : ''));
        $fileName = $className . '.php';
        $filePath = "{$directory}/{$fileName}";

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($filePath)) {
            $this->error("Class already exists: {$filePath}");
            return 1;
        }

        // Use stub located inside the same directory as this command
         $stubPath = __DIR__ . '/../stubs/class.stub';

        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return;
        }

        $stub = file_get_contents($stubPath);

        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );

        File::put($filePath, $content);
        $this->info("Class created: {$filePath}");
        return 0;
    }
}
