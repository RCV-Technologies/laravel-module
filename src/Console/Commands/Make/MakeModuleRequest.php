<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleRequest extends Command
{
    protected $signature = 'module:make-request {name : The name of the request class} {module : The module name}';
    protected $description = 'Create a new form request class inside module/src/Http/Requests';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $module = Str::studly($this->argument('module'));

        $namespace = "Modules\\{$module}\\Http\\Requests";
        $path = base_path("Modules/{$module}/src/Http/Requests");

        $filePath = "{$path}/{$name}.php";

        if (File::exists($filePath)) {
            $this->error("Request class {$name} already exists in {$module} module.");
            return Command::FAILURE;
        }

        File::ensureDirectoryExists($path);

         $stubPath = __DIR__ . '/../stubs/module-request.stub';

        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);
        $stub = str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, $name], $stub);

        File::put($filePath, $stub);

        $this->info("Request class {$name} created successfully in {$module} module.");
        $this->info("created in: {$filePath}");
        return Command::SUCCESS;
    }
}