<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleComponent extends Command
{
    protected $signature = 'module:make-component {name} {module}';
    protected $description = 'Create a new component class and blade view inside a module';

  public function handle()
    {
        $componentInput = $this->argument('name'); // e.g., folder/componentname
        $module = $this->argument('module'); // e.g., Blog

        $componentPath = str_replace('\\', '/', $componentInput);
        $pathParts = explode('/', $componentPath);
        $className = array_pop($pathParts);
        $folderPath = implode('/', $pathParts);

        $moduleNamespace = 'Modules\\' . $module;
        $basePath = base_path("Modules/{$module}");

        // Check if module exists
        if (!File::exists($basePath)) {
            $this->error("Module [{$module}] not found at path: Modules/{$module}");
            return Command::FAILURE;
        }

        // Component class directory and file
        $componentDir = $basePath . '/src/View/Components' . ($folderPath ? '/' . $folderPath : '');
        $componentFile = $componentDir . '/' . $className . '.php';

        // View file directory and file
    // Correct view file directory inside src
    $viewDir = $basePath . '/src/Resources/views/components' . ($folderPath ? '/' . $folderPath : '');
    $viewFile = $viewDir . '/' . \Illuminate\Support\Str::kebab($className) . '.blade.php';


        // Ensure component directory exists
        if (!File::exists($componentDir)) {
            File::makeDirectory($componentDir, 0755, true);
            $this->info("Created directory: {$componentDir}");
        }

        // Ensure view directory exists
        if (!File::exists($viewDir)) {
            File::makeDirectory($viewDir, 0755, true);
            $this->info("Created directory: {$viewDir}");
        }

        // Component class content
        $classNamespace = $moduleNamespace . '\\View\\Components' . ($folderPath ? '\\' . str_replace('/', '\\', $folderPath) : '');
        $viewReference = strtolower($module) . '::components' . ($folderPath ? '.' . str_replace('/', '.', $folderPath) : '') . '.' . \Illuminate\Support\Str::kebab($className);

        $stub = File::get(__DIR__ . '/../stubs/component.stub');
            $classContent = str_replace(
            ['{{ module_name }}', '{{ class_name }}', '{{ view }}'],
                [$classNamespace, $className, $viewReference],
                $stub
            );

        // Handle component class file
        if (!File::exists($componentFile)) {
            File::put($componentFile, $classContent);
            $this->info("✅ Component class created at: {$componentFile}");
        } else {
            $this->warn("⚠️ Component class already exists at: {$componentFile}");
        }

        // Handle blade view file
        if (!File::exists($viewFile)) {
            File::put($viewFile, "<!-- Blade view for {$className} component -->");
            $this->info("✅ Blade view created at: {$viewFile}");
        } else {
            $this->warn("⚠️ Blade view already exists at: {$viewFile}");
        }

        return Command::SUCCESS;
    }


}
