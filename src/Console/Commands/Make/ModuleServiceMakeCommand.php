<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
 
class ModuleServiceMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-service {name : The name of the service} {module : The name of the module} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a service for a module';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rawName = str_replace('\\', '/', $this->argument('name'));
        $module = $this->argument('module');
        $serviceName = 
            \Illuminate\Support\Str::studly(basename($rawName));
        $subPath = trim(dirname($rawName), '.');

        // Ensure module exists
        if (!File::exists(base_path("Modules/{$module}"))) {
            $this->error("Module {$module} does not exist.");
            return 1;
        }

        // Create service directory if it doesn't exist
        $servicePath = base_path("Modules/{$module}/src/Services" . ($subPath !== '' ? "/{$subPath}" : ''));
        if (!File::exists($servicePath)) {
            File::makeDirectory($servicePath, 0755, true);
        }

        // Generate service file
        $serviceFile = "{$servicePath}/{$serviceName}Service.php";
        if (File::exists($serviceFile)) {
            $this->error("Service {$serviceName}Service already exists.");
            $this->info("Path: {$serviceFile}");
            return 1;
        }
        $stub = File::get(__DIR__ . '/../stubs/service.stub');

        // Replace placeholders
        $content = str_replace(
            ['{{ module_name }}', '{{ class_name }}'],
            [$module, $serviceName],
            $stub
        );

        $namespaceSuffix = $subPath !== '' ? '\\' . str_replace('/', '\\', $subPath) : '';
        $targetNamespace = "Modules\\{$module}\\Services{$namespaceSuffix}";
        // Replace any namespace line
        $content = preg_replace('/^namespace\s+[^;]+;$/m', "namespace {$targetNamespace};", $content);

        // Fix repository import to include subPath
        $repositoryImport = "Modules\\{$module}\\Repositories" . ($namespaceSuffix !== '' ? $namespaceSuffix : '') . "\\\\{$serviceName}Repository";
        $content = preg_replace('/^use\s+Modules\\\\[^\\]+\\\\Repositories\\\\[^;]+;$/m', "use {$repositoryImport};", $content);

        // Create service file
        File::put($serviceFile, $content);
        $this->info("Service {$serviceName} created successfully.");
        $this->info("Path: {$serviceFile}");

        return 0;
    }
} 