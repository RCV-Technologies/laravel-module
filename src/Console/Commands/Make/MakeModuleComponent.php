<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleComponent extends Command
{
    protected $signature = 'module:make-component
        {names : One or multiple component class names (comma-separated, e.g. Organization/Sidebar,Dashboard/Header)}
        {module : The module name}
        {--stub-class= : Path to custom component class stub}
        {--stub-view= : Path to custom blade view stub}';

    protected $description = 'Create one or more component classes and blade views for a module and register them in the ServiceProvider';

    public function handle(): int
    {
        $names = explode(',', $this->argument('names'));
        $module = $this->argument('module');
        $success = true;

        foreach ($names as $name) {
            $name = trim($name);
            if (! $this->generateComponent($module, $name)) {
                $success = false;
            }
        }

        return $success ? static::SUCCESS : static::FAILURE;
    }

    protected function generateComponent(string $module, string $name): bool
    {
        [$classPath, $viewPath, $namespace, $className, $viewName] = $this->preparePaths($module, $name);

        if (File::exists($classPath) || File::exists($viewPath)) {
            $existingPath = File::exists($classPath) ? $classPath : $viewPath;
            $this->error("Component output already exists: {$existingPath}");

            return false;
        }

        // Generate Component Class
        File::ensureDirectoryExists(dirname($classPath));
        File::put($classPath, $this->getClassTemplate($namespace, $className, $viewName));
        $this->info("✅ Component class created: {$classPath}");

        File::ensureDirectoryExists(dirname($viewPath));
        File::put($viewPath, $this->getViewTemplate());
        $this->info("✅ Component view created: {$viewPath}");

        // Register in ServiceProvider
        $this->registerInServiceProvider($module, '\\'.$namespace.'\\'.$className, $viewName);

        return true;
    }

    protected function preparePaths(string $module, string $name): array
    {
        // Example input: "Organization/Sidebar"
        $parts = explode('/', $name);
        $className = array_pop($parts); // Sidebar
        $subNamespace = implode('\\', $parts); // Organization
        $subPath = implode('/', $parts); // Organization

        $namespace = "Modules\\{$module}\\View\\Components"
            .($subNamespace ? "\\{$subNamespace}" : '');

        // Class file
        $classPath = module_path($module, 'src/View/Components')
            .($subPath ? "/{$subPath}" : '')
            ."/{$className}.php";

        // Blade file (kebab-case)
        $viewRelative = strtolower(implode('/', array_filter([
            $subPath,
            Str::kebab($className),
        ])));

        $viewPath = module_path($module, "src/Resources/views/components/{$viewRelative}.blade.php");

        $viewName = strtolower($module).'::components.'.str_replace('/', '.', $viewRelative);

        return [$classPath, $viewPath, $namespace, $className, $viewName];
    }

    protected function getClassTemplate(string $namespace, string $className, string $viewName): string
    {
        $stubPath = $this->option('stub-class') ?? __DIR__.'/../stubs/component.stub';
        $stub = File::get($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ class_name }}', '{{ view_path }}'],
            [$namespace, $className, $viewName],
            $stub
        );
    }

    protected function getViewTemplate(): string
    {
        $stubPath = $this->option('stub-view') ?? __DIR__.'/../stubs/component-view.stub';

        return File::get($stubPath);
    }

    protected function registerInServiceProvider(string $module, string $classNamespace, string $viewName): void
    {
        $providerPath = module_path($module, "src/Providers/{$module}ServiceProvider.php");

        if (! File::exists($providerPath)) {
            $this->error("⚠️ ServiceProvider not found for module: {$module}");

            return;
        }

        $contents = File::get($providerPath);

        $registrationLine = "        \\Illuminate\\Support\\Facades\\Blade::component('{$viewName}', {$classNamespace}::class);";

        if (strpos($contents, $registrationLine) !== false) {
            $this->info("🔁 Component already registered in {$module}ServiceProvider");

            return;
        }

        $contents = preg_replace(
            '/(protected function registerComponents\(\): void\s*\{\s*)([\s\S]*?)(\})/',
            "$1$2\n$registrationLine\n    $3",
            $contents
        );

        File::put($providerPath, $contents);

        $this->info("✅ Component registered in {$module}ServiceProvider");
    }
}
