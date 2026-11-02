<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeListener extends Command
{
    protected $signature = 'module:make-listener
                            {name : The listener class name (can include subdirectories)}
                            {event : The event class to listen for (relative to module Events folder)}
                            {module : The top-level module name}';


    protected $description = 'Create a new event listener class for the specified module';

    public function handle()
    {
        // --- Module ---
        $moduleInput = $this->argument('module'); // module is now last
        $module = Str::studly($moduleInput);
        $modulePath = $module; // top-level module, no extra dirs

        // --- Listener ---
        $listenerInput = $this->argument('name');
        $listenerParts = preg_split('/[\/\\\\]+/', $listenerInput);
        $listenerClass = Str::studly(array_pop($listenerParts));
        $listenerSubNamespace = implode('\\', array_map([Str::class, 'studly'], $listenerParts));
        $listenerSubPath = implode('/', array_map([Str::class, 'studly'], $listenerParts));

        $listenerNamespace = "Modules\\{$module}\\Listeners" . ($listenerSubNamespace ? "\\{$listenerSubNamespace}" : '');
        $listenerPath = module_path($modulePath, 'src/Listeners') . ($listenerSubPath ? "/{$listenerSubPath}" : '');

        // --- Event ---
        $eventInput = $this->argument('event');
        $eventParts = preg_split('/[\/\\\\]+/', $eventInput);
        $eventClassName = Str::studly(array_pop($eventParts));
        $eventSubNamespace = implode('\\', array_map([Str::class, 'studly'], $eventParts));
        $eventNamespace = "Modules\\{$module}\\Events" . ($eventSubNamespace ? "\\{$eventSubNamespace}" : '');
        $eventClass = "{$eventNamespace}\\{$eventClassName}";

        // --- File path ---
        $filePath = "{$listenerPath}/{$listenerClass}.php";
        $stubPath = __DIR__ . '/../stubs/listener.stub';

        if (!File::exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return Command::FAILURE;
        }

        if (!File::isDirectory($listenerPath)) {
            File::makeDirectory($listenerPath, 0755, true);
        }

        if (File::exists($filePath)) {
            $this->error("Listener {$listenerClass} already exists.");
            return Command::FAILURE;
        }

        $stub = File::get($stubPath);
        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ event }}', '{{ event_name }}'],
            [$listenerNamespace, $listenerClass, $eventClass, $eventClassName],
            $stub
        );

        File::put($filePath, $stub);

        $this->info("Listener {$listenerClass} created successfully.");
        $this->info("Path: {$filePath}");

        return Command::SUCCESS;
    }
}