<?php


namespace RCV\Core\Console\Commands\Database\Seeders;


use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class MakeModuleSeeder extends Command
{
    protected $signature = 'module:make-seeder {name} {module}';
    protected $description = 'Create a new seeder inside a module folder';

    public function handle()
    {
        $seederName = Str::studly($this->argument('name'));
        $moduleName = Str::studly($this->argument('module'));

        $seederPath = module_path($moduleName, 'src/Database/Seeders');

        if (!File::exists($seederPath)) {
            File::makeDirectory($seederPath, 0755, true);
        }

        $seederFile = "{$seederPath}/{$seederName}Seeder.php";

        if (File::exists($seederFile)) {
            $this->error("Seeder '{$seederName}' already exists in module '{$moduleName}'.");
            return 1;
        }
        
       
        $stub = File::get(__DIR__.'/../../stubs/seeder.stub');
        $stub = str_replace(
        ['{{ module_name }}', '{{ class_name }}'],
        [$moduleName, $seederName],
        $stub);

        $this->info("Seeder '{$seederName}' created successfully in module '{$moduleName}'.");
        
        File::put($seederFile, $stub);
        $this->info("Path: Modules/{$moduleName}/src/Database/Seeders/{$seederName}Seeder.php");
        return 0;
    }
}
