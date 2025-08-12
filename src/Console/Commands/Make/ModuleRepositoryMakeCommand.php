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
    $rawName = str_replace('\\', '/', $this->argument('name'));
    $module = $this->argument('module');
    $className = \Illuminate\Support\Str::studly(basename($rawName));
    $subPath = trim(dirname($rawName), '.');

    if (!File::exists(base_path("Modules/{$module}"))) {
        $this->error("Module [{$module}] does not exist.");
        return 1;
    }

    $repositoryPath = base_path("Modules/{$module}/src/Repositories" . ($subPath !== '' ? "/{$subPath}" : ''));
    if (!File::exists($repositoryPath)) {
        File::makeDirectory($repositoryPath, 0755, true);
    }

    $repositoryFile = "{$repositoryPath}/{$className}Repository.php";

    if (File::exists($repositoryFile)) {
        $this->error("Repository [{$className}Repository] already exists.");
        $this->info("Path: {$repositoryFile}");
        return 1;
    }

    $stubPath = __DIR__ . '/../stubs/repository.stub';

    if (!File::exists($stubPath)) {
        $this->error("Stub file not found at {$stubPath}");
        return 1;
    }

    $stub = File::get($stubPath);

    $content = str_replace(
        ['{{ module_name }}', '{{ class_name }}'],
        [$module, $className],
        $stub
    );

    $namespaceSuffix = $subPath !== '' ? '\\' . str_replace('/', '\\', $subPath) : '';
    $targetNamespace = "Modules\\{$module}\\Repositories{$namespaceSuffix}";
    $content = preg_replace('/^namespace\s+Modules\\\\\{\{\s*module_name\s*\}\}\\\\Repositories;$/m', "namespace {$targetNamespace};", $content);

    File::put($repositoryFile, $content);

    $this->info("Repository [{$className}] created successfully.");
    $this->info("Path: {$repositoryFile}");

    return 0;
}

}
