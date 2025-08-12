<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleArtisanCommand extends Command
{
    protected $signature = 'module:make-command {module} {name}';
    protected $description = 'Generate a new Artisan command for the specified module';

    public function handle()
    {
        $module = $this->argument('module'); // e.g. Blog
        $commandName = $this->argument('name'); // e.g. SyncPosts

        $className = Str::studly(class_basename($commandName));
        $directory = base_path("Modules/{$module}/src/Console/Commands");
        $filePath = "{$directory}/{$className}.php";

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($filePath)) {
            $this->error("Command already exists: {$filePath}");
            return 1;
        }

                 $stubPath = __DIR__ . '/../stubs/console-command.stub';

        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return;
        }

        $stub = file_get_contents($stubPath);

        $namespace = "Modules\\{$module}\\Console\\Commands";

        $content = str_replace(
            ['{{namespace}}', '{{class}}', '{{signature}}'],
            [$namespace, $className, Str::kebab($className)],
            $stub
        );

        File::put($filePath, $content);
        $this->info("Command created: {$filePath}");
        return 0;
    }
}
