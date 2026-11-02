<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeJobCommand extends Command
{
    protected $signature = 'module:make-job {name : The name of the Job class (with optional subdirectory e.g. Billing/ProcessInvoice)} {module : The name of the module}';

    protected $description = 'Create a new Job class inside src/Jobs folder of the module';

    public function handle(): int
    {
        $nameInput = $this->argument('name');
        $module = Str::studly($this->argument('module')); // Ensures proper casing

        // Handle nested directories (e.g. Billing/ProcessInvoice)
        $nameParts = preg_split('/[\/\\\\]/', $nameInput);
        $className = array_pop($nameParts);
        $subPath = implode('/', $nameParts);
        $subNamespace = implode('\\', array_map('Str::studly', $nameParts));

        // Namespace should NOT include "src"
        $namespace = "Modules\\{$module}\\Jobs".($subNamespace ? "\\{$subNamespace}" : '');

        // File path should include "src"
        $path = module_path($module, 'src/Jobs').($subPath ? "/{$subPath}" : '')."/{$className}.php";

        if (file_exists($path)) {
            $this->error("Job class {$className} already exists at {$path}.");

            return self::FAILURE;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $stubPath = __DIR__.'/../stubs/job.stub';
        if (! file_exists($stubPath)) {
            $this->error("Stub file not found at {$stubPath}");

            return self::FAILURE;
        }

        $stub = file_get_contents($stubPath);
        $stub = str_replace(
            ['DummyNamespace', 'DummyClass'],
            [$namespace, $className],
            $stub
        );

        file_put_contents($path, $stub);

        $this->info("Job class {$className} created successfully at {$path}.");

        return self::SUCCESS;
    }
}
