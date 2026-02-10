<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-model
                            {name : The name of the model}
                            {module : The name of the module}
                            {--migration : Create a new migration file for the model}
                            {--factory : Create a new factory for the model}
                            {--seed : Create a new seeder for the model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model for a module';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $module = $this->getModuleName();

        // Ensure module exists
        if (!File::exists(base_path("Modules/{$module}"))) {
            $this->error("Module [{$module}] does not exist.");
            return 1;
        }

        // Handle subdirectory in model name
        $nameParts = explode('/', $name);
        $className = Str::studly(array_pop($nameParts));
        $subDir = implode('/', $nameParts);

        // Create model directory if it doesn't exist
        $modelBasePath = base_path("Modules/{$module}/src/Models");
        $modelPath = $subDir ? "{$modelBasePath}/{$subDir}" : $modelBasePath;

        if (!File::exists($modelPath)) {
            File::makeDirectory($modelPath, 0755, true);
        }

        // Generate model file
        $modelFile = "{$modelPath}/{$className}.php";

        // Check if model already exists
        if (File::exists($modelFile)) {
            $this->error("Model [{$className}] already exists.");
            $this->info("Path: {$modelFile}");
            return 1;
        }

        $stub = File::get(__DIR__ . '/../stubs/model.stub');

        // Build namespace with subdirectories
        $namespace = "Modules\\{$module}\\Models";
        if ($subDir) {
            $namespaceParts = explode('/', $subDir);
            $namespaceParts = array_map(fn($part) => Str::studly($part), $namespaceParts);
            $namespace .= '\\' . implode('\\', $namespaceParts);
        }

        // Replace placeholders
        $content = str_replace(
            ['{{ namespace }}', '{{ class_name }}'],
            [$namespace, $className],
            $stub
        );

        File::put($modelFile, $content);
        $this->info("Model [{$className}] created successfully.");
        $this->info("Path: {$modelFile}");

        // Create migration if requested
        if ($this->option('migration')) {
            $table = Str::snake(Str::pluralStudly($className));
            $this->call('module:make-migration', [
                'name' => "create_{$table}_table",
                'module' => $module
            ]);
        }

        // Create factory if requested
        if ($this->option('factory')) {
            $this->call('module:make-factory', [
                'name' => "{$className}Factory",
                'module' => $module
            ]);
        }

        // Create seeder if requested
        if ($this->option('seed')) {
            $this->call('module:make-seeder', [
                'name' => "{$className}Seeder",
                'module' => $module
            ]);
        }

        return 0;
    }

    /**
     * Get the formatted module name.
     *
     * @return string
     */
    protected function getModuleName(): string
    {
        return Str::studly($this->argument('module'));
    }
}
