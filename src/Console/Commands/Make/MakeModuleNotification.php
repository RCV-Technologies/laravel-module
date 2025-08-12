<?php

namespace Rcv\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleNotification extends Command
{
    protected $signature = 'module:make-notification {module} {name}';
    protected $description = 'Create a new notification class for the specified module';

    public function handle()
    {
        $module = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));

        $moduleBasePath = base_path("Modules/{$module}");

        // Validate module existence
        if (!File::exists($moduleBasePath)) {
            $this->error("Module '{$module}' does not exist.");
            return Command::FAILURE;
        }

        $notificationsPath = "{$moduleBasePath}/src/Notifications";
        $filePath = "{$notificationsPath}/{$name}.php";

        if (File::exists($filePath)) {
            $this->error("Notification {$name} already exists at: {$filePath}");
            return Command::FAILURE;
        }

        // Ensure notifications directory exists
        File::ensureDirectoryExists($notificationsPath);

        // Load stub from Core module
         $stubPath = __DIR__ . '/../stubs/module-notification.stub';

        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);

        $content = str_replace(
            ['{{ class }}', '{{ module }}'],
            [$name, $module],
            $stub
        );

        File::put($filePath, $content);

        $this->info("Notification '{$name}' created in: {$filePath}");
        return Command::SUCCESS;
    }
}
