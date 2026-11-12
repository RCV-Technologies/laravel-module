<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeInterfaceCommand extends Command
{
    protected $signature = 'module:make-interface 
                            {name : The name of the interface (e.g. UserRepositoryInterface)} 
                            {module : The name of the module}';

    protected $description = 'Create a new interface inside Repositories/Interfaces of the specified module';

    public function handle()
    {
        $path = $this->getDestinationFilePath();
        $contents = $this->getTemplateContents();

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if (file_exists($path)) {
            $this->error("Interface already exists at: {$path}");
            return Command::FAILURE;
        }

        file_put_contents($path, $contents);

        $this->info("Interface created at: {$path}");
        return Command::SUCCESS;
    }

    protected function getTemplateContents(): string
    {
        $namespace = $this->getNamespace();
        $className = Str::studly(class_basename($this->argument('name')));

        $stubPath = __DIR__ . '/../stubs/interface.stub';

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            exit(1);
        }

        $stub = file_get_contents($stubPath);

        return str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $className],
            $stub
        );
    }

    protected function getDestinationFilePath(): string
    {
        $module = $this->getModuleName();
        $className = Str::studly(class_basename($this->argument('name')));

        return module_path($module, "src/Repositories/Interfaces/{$className}.php");
    }

    protected function getNamespace(): string
    {
        $module = $this->getModuleName();
        return "Modules\\{$module}\\Repositories\\Interfaces";
    }

    protected function getModuleName(): string
    {
        return Str::studly($this->argument('module'));
    }
}
