<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleMakeScopeCommand extends Command
{
    protected $signature = 'module:make-scope {name} {module}';

    protected $description = 'Create a new scope class for the specified module';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $module = Str::studly($this->argument('module'));

        $namespace = "Modules\\{$module}\\src\\Scopes";
        $path = module_path($module, 'src/Scopes');
        $filePath = "{$path}/{$name}.php";
        $stubPath = __DIR__.'/../stubs/scope.stub';

        if (! file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");

            return self::FAILURE;
        }

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        if (file_exists($filePath)) {
            $this->error("Scope {$name} already exists in module {$module}.");

            return self::FAILURE;
        }

        $stub = file_get_contents($stubPath);
        $content = str_replace(
            ['{{ module_name }}', '{{ class_name }}'],
            [$namespace, $name],
            $stub
        );

        file_put_contents($filePath, $content);

        $this->info(" Scope {$name} created in module {$module}.");
        $this->info("Path: {$filePath}");

        return self::SUCCESS;
    }
}
