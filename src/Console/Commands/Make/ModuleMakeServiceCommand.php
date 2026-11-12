<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ModuleMakeServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-service {name : The name of the service} {module : The name of the module}';

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
        $serviceName = $this->argument('name'); // Can be nested like "User/Profile"
        $module = $this->argument('module');

        // Ensure module exists
        $modulePath = module_path($module);
        if (!File::exists($modulePath)) {
            $this->error("Module [{$module}] does not exist.");
            return 1;
        }

        // Extract subdirectory path and class name
        $parts = explode('/', $serviceName);
        $className = array_pop($parts); // Actual class name
        $subDir = implode('/', $parts); // Subdirectory path if exists

        // Build full service path
        $servicePath = "{$modulePath}/src/Services";
        if ($subDir) {
            $servicePath .= "/{$subDir}";
        }

        // Ensure directory exists
        if (!File::exists($servicePath)) {
            File::makeDirectory($servicePath, 0755, true);
        }

        // Full path for the service file
        $serviceFile = "{$servicePath}/{$className}Service.php";

        // Check if service already exists
        if (File::exists($serviceFile)) {
            $this->error("Service [{$className}] already exists in module [{$module}].");
            return 1;
        }

        // Get stub and replace placeholders
        $stub = File::get(__DIR__ . '/../stubs/service.stub');
        $stub = str_replace(
            ['{{ module_name }}', '{{ class_name }}'],
            [$module, $className],
            $stub
        );

        // Create service file
        File::put($serviceFile, $stub);

        $this->info("Service [{$className}] created successfully.");
        $this->info("Path: {$serviceFile}");

        return 0;
    }
}
