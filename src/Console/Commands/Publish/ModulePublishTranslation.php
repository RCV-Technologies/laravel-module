<?php

namespace RCV\Core\Console\Commands\Publish;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModulePublishTranslation extends Command
{
    protected $signature = 'module:publish-translation {module} {--debug : Show debug information}';
    protected $description = 'Publish a module\'s translations to the application';

    public function handle()
    {
        $module = $this->argument('module');
        $debug = $this->option('debug');
        
        $modulePath = module_path($module);
        $translationsPath = "{$modulePath}/Resources/lang";

        if ($debug) {
            $this->line("Debug Information:");
            $this->line("- Module path: {$modulePath}");
            $this->line("- Translations path: {$translationsPath}");
            $this->line("- Module exists: " . (File::exists($modulePath) ? 'YES' : 'NO'));
            $this->line("- Translations path exists: " . (File::exists($translationsPath) ? 'YES' : 'NO'));
            
            if (File::exists($modulePath)) {
                $directories = File::directories($modulePath);
                $this->line("- Available directories in module:");
                foreach ($directories as $dir) {
                    $this->line("  * " . basename($dir));
                }
                
                // Check Resources directory
                $resourcesPath = "{$modulePath}/Resources";
                if (File::exists($resourcesPath)) {
                    $resourceDirs = File::directories($resourcesPath);
                    $this->line("- Available directories in Resources:");
                    foreach ($resourceDirs as $dir) {
                        $this->line("  * " . basename($dir));
                    }
                }
            }
        }

        // Check for alternative translation paths
        $alternativePaths = [
            "{$modulePath}/resources/lang",
            "{$modulePath}/lang",
            "{$modulePath}/translations",
            "{$modulePath}/Resources/translations",
        ];

        $foundPath = null;
        if (!File::exists($translationsPath)) {
            foreach ($alternativePaths as $altPath) {
                if (File::exists($altPath)) {
                    $foundPath = $altPath;
                    $this->info("Found translations in alternative path: {$altPath}");
                    break;
                }
            }
        } else {
            $foundPath = $translationsPath;
        }

        if (!$foundPath) {
            $this->error("No translations found for module: {$module}");
            $this->line("Searched in:");
            $this->line("- {$translationsPath}");
            foreach ($alternativePaths as $path) {
                $this->line("- {$path}");
            }
            return;
        }

        $destinationPath = resource_path("lang/vendor/{$module}");

        // Ensure the destination directory exists
        File::ensureDirectoryExists($destinationPath);

        // Copy translation files
        $files = File::allFiles($foundPath);
        
        if (empty($files)) {
            $this->warning("Translation directory exists but contains no files for module: {$module}");
            return;
        }

        foreach ($files as $file) {
            $relativePath = str_replace($foundPath . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            $destination = $destinationPath . DIRECTORY_SEPARATOR . $relativePath;
            
            // Ensure subdirectories exist for nested translation files
            File::ensureDirectoryExists(dirname($destination));
            
            File::copy($file->getRealPath(), $destination);
            $this->info("Published: {$relativePath}");
        }

        $this->info("Translations for module '{$module}' have been published.");
    }
}
