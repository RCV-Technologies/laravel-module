<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModulePolicy extends Command
{
    protected $signature = 'module:make-policy {module} {name}';
    protected $description = 'Create a new policy class for the specified module';

    public function handle()
    {
        $module = $this->argument('module'); // e.g., Blog
        $name = $this->argument('name');     // e.g., PostPolicy

        $className = Str::studly(class_basename($name));
        $directory = base_path("Modules/{$module}/src/Policies");
        $filePath = "{$directory}/{$className}.php";

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($filePath)) {
            $this->error("Policy already exists: {$filePath}");
            return 1;
        }

         $stubPath = __DIR__ . '/../stubs/policy.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return;
        }

        $stub = file_get_contents($stubPath);
        $namespace = "Modules\\{$module}\\Policies";

        $content = str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );

        File::put($filePath, $content);
        $this->info("Policy class created: {$filePath}");
        return 0;
    }
}
