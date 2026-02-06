<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ModuleMakeControllerCommand extends Command
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
     */
    public function handle(): int
    {
        $nameInput = str_replace('\\', '/', $this->argument('name'));
        $module = $this->argument('module');

        $className = Str::studly(class_basename($nameInput));
        $subPath = trim(dirname($nameInput), '.'); // Admin / Admin/User

        $isResource = $this->option('resource');
        $isApi = $this->option('api');

        // Check module exists
        $modulePath = base_path("Modules/{$module}");
        if (!File::exists($modulePath)) {
            $this->error("Module [{$module}] does not exist.");
            return 1;
        }

        // Ensure base controller exists
        $this->ensureBaseController($module);

        // Controller directory
        $controllerDir = "{$modulePath}/src/Http/Controllers";
        if (!empty($subPath)) {
            $controllerDir .= '/' . $subPath;
        }

        if (!File::exists($controllerDir)) {
            File::makeDirectory($controllerDir, 0755, true);
        }

        $controllerFile = "{$controllerDir}/{$className}.php";

        if (File::exists($controllerFile)) {
            $this->error("Controller [{$className}] already exists.");
            $this->info("Path: {$controllerFile}");
            return 1;
        }

        $stub = $this->getStub($isResource, $isApi);

        $this->createController(
            $stub,
            $className,
            $module,
            $subPath,
            $isResource,
            $controllerFile
        );

        $this->info("Controller [{$className}] created successfully.");
        $this->info("Created in [{$controllerFile}]");

        return 0;
    }

    /**
     * Ensure module base controller exists.
     */
    protected function ensureBaseController(string $module): void
    {
        $controllerDir = base_path("Modules/{$module}/src/Http/Controllers");
        $baseController = "{$controllerDir}/ModuleController.php";

        if (File::exists($baseController)) {
            return;
        }

        if (!File::exists($controllerDir)) {
            File::makeDirectory($controllerDir, 0755, true);
        }

        $stub = File::get(__DIR__ . '/../stubs/base-controller.stub');
        $stub = str_replace('{{ module_name }}', $module, $stub);

        File::put($baseController, $stub);
    }

    /**
     * Get stub name.
     */
    protected function getStub(bool $isResource, bool $isApi): string
    {
        if ($isResource) {
            return $isApi
                ? 'resource-api-controller.stub'
                : 'resource-controller.stub';
        }

        return 'controller.stub';
    }

    /**
     * Create controller file.
     */
    protected function createController(
        string $stubName,
        string $className,
        string $module,
        string $subPath,
        bool $isResource,
        string $destination
    ): void {
        $stub = File::get(__DIR__ . '/../stubs/' . $stubName);

        // Class name
        $stub = str_replace('{{ class_name }}', $className, $stub);

        // Resource placeholders
        if ($isResource) {
            $resource = Str::studly(Str::singular(str_replace('Controller', '', $className)));

            $stub = str_replace([
                '{{ resource_name }}',
                '{{ resource_name_lower }}',
            ], [
                $resource,
                Str::camel($resource),
            ], $stub);
        }

        // Namespace
        $namespace = "Modules\\{$module}\\Http\\Controllers";
        if (!empty($subPath)) {
            $namespace .= '\\' . str_replace('/', '\\', Str::studly($subPath));
        }

        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ module_name }}', $module, $stub);

        File::put($destination, $stub);
    }
}
