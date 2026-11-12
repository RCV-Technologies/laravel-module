<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeViewCommand extends Command
{
    protected $signature = 'module:make-view 
                            {name : The name of the view (e.g. users.index)} 
                            {module : The name of the module}';

    protected $description = 'Create a new view file for the specified module';

    public function handle(): int
    {
        $view = $this->argument('name');
        $module = $this->getModuleName();

        $path = $this->getDestinationFilePath();
        $directory = dirname($path);

        // Check if view already exists
        if (File::exists($path)) {
            $this->error("View file already exists: {$path}");
            return Command::FAILURE;
        }

        // Ensure directory exists
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $stub = $this->getTemplateContents();

        File::put($path, $stub);

        $this->info("View created: {$path}");
        return Command::SUCCESS;
    }

    /**
     * Get the stub contents with placeholders replaced.
     */
    protected function getTemplateContents(): string
    {
        $view = $this->argument('name');
        $module = $this->getModuleName();

        $stubPath = __DIR__ . '/../stubs/view.stub';

        if (!File::exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            exit(Command::FAILURE);
        }

        $stub = File::get($stubPath);

        return str_replace(
            ['{{ view_name }}', '{{ module_name }}'],
            [$view, $module],
            $stub
        );
    }

    /**
     * Get the full path where the view file should be created.
     */
    protected function getDestinationFilePath(): string
    {
        $view = $this->argument('name');
        $module = $this->getModuleName();

        // Convert dot notation to directory structure
        $viewPath = str_replace('.', '/', $view) . '.blade.php';

        return module_path($module, "src/Resources/views/{$viewPath}");
    }

    /**
     * Get the formatted module name.
     */
    protected function getModuleName(): string
    {
        return Str::studly($this->argument('module'));
    }
}
