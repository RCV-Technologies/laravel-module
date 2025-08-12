<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
class ModuleControllerMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-controller {name} {module} {--resource} {--api}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new controller for the specified module';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $nameInput = str_replace('\\', '/', $this->argument('name'));
        $module = $this->argument('module');
        $className = Str::studly(class_basename($nameInput));
        $subPath = trim(dirname($nameInput), '.');
        $isResource = $this->option('resource');
        $isApi = $this->option('api');

        // Check if module exists
        $modulePath = base_path("Modules/{$module}");
        if (!File::exists($modulePath)) {
            $this->error("Module [{$module}] does not exist.");
            return 1;
        }

        // Create base controller if it doesn't exist
        $baseControllerPath = "{$modulePath}/src/Http/Controllers/ModuleController.php";
        if (!File::exists($baseControllerPath)) {
            $this->createBaseController($module);
        }

        // Create controller
        $controllerPath = "{$modulePath}/src/Http/Controllers" . ($subPath !== '' ? '/' . $subPath : '');
        if (!File::exists($controllerPath)) {
            File::makeDirectory($controllerPath, 0755, true);
        }

        $stub = $this->getStub($isResource, $isApi);
        $controllerFile = "{$controllerPath}/{$className}.php";

        if (File::exists($controllerFile)) {
            $this->error("Controller [{$className}] already exists.");
            $this->info("Path: {$controllerFile}");
            return 1;
        }

        $this->createController($stub, $className, $module, $isResource, $isApi, $subPath);

        $this->info("Controller [{$name}] created successfully.");
        $this->info("Created in [{$controllerFile}]");
        return 0;
    }

    /**
     * Create the base controller for the module.
     *
     * @param string $module
     * @return void
     */
    protected function createBaseController($module)
    {
        $stub = File::get(__DIR__ . '/../stubs/base-controller.stub');
        $stub = str_replace('{{ module_name }}', $module, $stub);

        $controllerPath = base_path("Modules/{$module}/src/Http/Controllers");
        if (!File::exists($controllerPath)) {
            File::makeDirectory($controllerPath, 0755, true);
        }

        File::put("{$controllerPath}/ModuleController.php", $stub);
    }

    /**
     * Get the controller stub file.
     *
     * @param bool $isResource
     * @param bool $isApi
     * @return string
     */
    protected function getStub($isResource, $isApi)
    {
        if ($isResource) {
            return $isApi ? 'resource-api-controller.stub' : 'resource-controller.stub';
        }

        return 'controller.stub';
    }

    /**
     * Create the controller file.
     *
     * @param string $stub
     * @param string $name
     * @param string $module
     * @param bool $isResource
     * @param bool $isApi
     * @return void
     */
    protected function createController($stub, $name, $module, $isResource, $isApi, $subPath = '')
    {
        $stub = File::get(__DIR__ . '/../stubs/' . $stub);
        $stub = str_replace('{{ module_name }}', $module, $stub);
        $stub = str_replace('{{ class_name }}', $name, $stub);

        $namespaceSuffix = $subPath !== '' ? '\\' . str_replace('/', '\\', $subPath) : '';
        $namespace = "Modules\\{$module}\\Http\\Controllers{$namespaceSuffix}";
        $stub = preg_replace('/^namespace\s+Modules\\\\\{\{\s*module_name\s*\}\}\\\\Http\\\\Controllers;$/m', "namespace {$namespace};", $stub);

        if ($isResource) {
            $resourceName = Str::studly(Str::singular($name));
            $stub = str_replace('{{ resource_name }}', $resourceName, $stub);
            $stub = str_replace('{{ resource_name_lower }}', Str::camel($resourceName), $stub);
        }

        $targetDir = base_path("Modules/{$module}/src/Http/Controllers" . ($subPath !== '' ? '/' . $subPath : ''));
        File::ensureDirectoryExists($targetDir, 0755, true);
        File::put(
            $targetDir . "/{$name}.php",
            $stub
        );
    }
} 
