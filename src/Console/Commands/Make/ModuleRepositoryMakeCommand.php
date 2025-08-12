<?php

namespace Rcv\Core\Console\Commands\Make;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModuleRepositoryMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-repository {name} {module}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository for a module';

    /**jdkzfefas
     * Execute the console command.
     */
    public function handle()
    {
        // Normalize slashes in the name argument
        $rawName = str_replace('\\', '/', $this->argument('name'));
        $module = $this->argument('module');
        $className = \Illuminate\Support\Str::studly(basename($rawName));
        $subPath = trim(dirname($rawName), '.');
    
        // Ensure module exists
        if (!File::exists(base_path("Modules/{$module}"))) {
            $this->error("Module [{$module}] does not exist.");
            return 1;
        }
    
        // Build repository path
        $repositoryPath = base_path("Modules/{$module}/src/Repositories" . ($subPath !== '' ? "/{$subPath}" : ''));
        if (!File::exists($repositoryPath)) {
            File::makeDirectory($repositoryPath, 0755, true);
        }
    
        // Full file path
        $repositoryFile = "{$repositoryPath}/{$className}Repository.php";
    
        // Check if file already exists
        if (File::exists($repositoryFile)) {
            $this->error("Repository [{$className}Repository] already exists.");
            $this->info("Path: {$repositoryFile}");
            return 1;
        }
    
        // Load stub
        $stubPath = __DIR__ . '/../stubs/repository.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at {$stubPath}");
            return 1;
        }
    
        $stub = File::get($stubPath);
    
        // Build namespace (handles nested subdirectories)
        $namespaceSuffix = $subPath !== '' ? '\\' . str_replace('/', '\\', $subPath) : '';
        $namespace = "Modules\\{$module}\\Repositories{$namespaceSuffix}";
    
        // Replace placeholders
        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class_name }}', $className . 'Repository', $stub);
    
        // Write file
        File::put($repositoryFile, $stub);
    
        // Success message
        $this->info("Repository [{$className}Repository] created successfully.");
        $this->info("Path: {$repositoryFile}");
    
        return 0;
    }
    

}
