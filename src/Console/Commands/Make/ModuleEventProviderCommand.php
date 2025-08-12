<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleEventProviderCommand extends Command
{
    
    protected $signature = 'module:make-event-provider {name : The name of the event} {module : The name of the module} ';

   
    protected $description = 'Create a event provider for a module';

    public function handle()
    {
        $eventName = $this->argument('name');
        $module = $this->argument('module');

        // Ensure module exists
        if (!File::exists(base_path("Modules/{$module}"))) {
            $this->error("Module {$module} does not exist.");
            return 1;
        }

        // Create provider directory if it doesn't exist
        $providerPath = base_path("Modules/{$module}/src/Providers");
        if (!File::exists($providerPath)) {
            File::makeDirectory($providerPath, 0755, true);
        }

        // Generate provider file
        $providerFile = "{$providerPath}/{$eventName}EventProvider.php";
       $stub = File::get(__DIR__ . '/../stubs/event-provider.stub');



        // Replace placeholders
        $content = str_replace(
            ['{{ module_name }}', '{{ name }}'],
            [$module, $eventName],
            $stub
        );

        // Create provider file
        File::put($providerFile, $content);
        $this->info("Provider {$eventName} created successfully.");
        $this->info("Path: {$providerFile}");

        return 0;
    }
} 
