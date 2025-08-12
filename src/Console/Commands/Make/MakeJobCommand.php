<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeJobCommand extends Command
{
    protected $signature = 'module:make-job {name : The name of the Job class} {module : The name of the module}';
    protected $description = 'Create a new Job class inside src/Users/Jobs folder of the module';

    public function handle()
    {
        $name = $this->argument('name');
        $module = Str::studly($this->argument('module')); // Ensures proper casing

        $className = class_basename($name);
        $namespace = "Modules\\$module\\User\\Jobs";

        $path = base_path("Modules/{$module}/src/User/Jobs/{$className}.php");

        if (file_exists($path)) {
            $this->error("Job class {$className} already exists at {$path}.");
            return;
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $stubPath = __DIR__ . '/../stubs/job.stub';
        if (!file_exists($stubPath)) {
            $this->error("Stub file not found at {$stubPath}");
            return;
        }

        $stub = file_get_contents($stubPath);
        $stub = str_replace(
            ['DummyNamespace', 'DummyClass'],
            [$namespace, $className],
            $stub
        );

        file_put_contents($path, $stub);

        $this->info("Job class {$className} created successfully at {$path}.");
    }
}
