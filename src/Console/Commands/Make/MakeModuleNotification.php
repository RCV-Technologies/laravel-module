<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleNotification extends Command
{
    protected $signature = 'module:make-notification 
                            {name : The notification class name (can include subdirectories)} 
                            {module : The module name}';

    protected $description = 'Create a new notification class for the specified module';

    public function handle()
    {
        $module = Str::studly($this->argument('module'));   // e.g., Blog
        $nameInput = $this->argument('name');               // e.g., Admin/NewUserNotification

        // --- Split name to handle subdirectories ---
        $parts = preg_split('/[\/\\\\]+/', $nameInput);
        $className = Str::studly(array_pop($parts));
        $subPath = implode('/', array_map([Str::class, 'studly'], $parts));
        $subNamespace = implode('\\', array_map([Str::class, 'studly'], $parts));

        // --- Paths & Namespace ---
        $moduleBasePath = module_path($module);
        if (!File::exists($moduleBasePath)) {
            $this->error("Module '{$module}' does not exist.");
            return Command::FAILURE;
        }

        $notificationsPath = $moduleBasePath . "/src/Notifications" . ($subPath ? "/{$subPath}" : '');
        $filePath = "{$notificationsPath}/{$className}.php";
        $namespace = "Modules\\{$module}\\Notifications" . ($subNamespace ? "\\{$subNamespace}" : '');

        if (File::exists($filePath)) {
            $this->error("Notification {$className} already exists at: {$filePath}");
            return Command::FAILURE;
        }

        File::ensureDirectoryExists($notificationsPath);

        // --- Load stub ---
        $stubPath = __DIR__ . '/../stubs/module-notification.stub';
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);

        // --- Replace placeholders ---
        $content = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ module }}'],
            [$namespace, $className, $module],
            $stub
        );

        File::put($filePath, $content);

        $this->info("Notification '{$className}' created successfully in module '{$module}'.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}
