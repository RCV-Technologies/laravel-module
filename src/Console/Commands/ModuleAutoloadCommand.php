<?php

namespace RCV\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

/**
 * ModuleAutoloadCommand
 * 
 * This command maintains the application's module autoloading configuration by ensuring
 * that all enabled modules are properly registered in the composer.json autoload section.
 * It preserves the App namespace and Core module while adding/removing modules based on
 * their enabled status in the database.
 *
 * Key features:
 * - Preserves App namespace for Laravel application classes
 * - Ensures Core module is always registered
 * - Adds enabled modules to autoload configuration
 * - Removes disabled modules from autoload configuration
 * - Automatically updates autoload files
 *
 * Usage:
 * ```bash
 * php artisan module:autoload
 * ```
 *
 * This command should be run:
 * - After enabling/disabling modules
 * - When setting up the project
 * - When fixing autoload issues
 * - After running module:sync
 *
 * @package RCV\Core\Console\Commands
 */
class ModuleAutoloadCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'module:autoload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update module autoload configuration for enabled modules';

    /**
     * Execute the console command.
     *
     * This method:
     * 1. Reads the current composer.json
     * 2. Preserves App namespace and Core module
     * 3. Adds all enabled modules to autoload
     * 4. Updates composer.json
     * 5. Runs composer dump-autoload
     *
     * @return void
     */
    public function handle()
    {
        $composerJson = json_decode(File::get(base_path('composer.json')), true);
        $autoload = $composerJson['autoload']['psr-4'] ?? [];
        
        // Always preserve App namespace
        if (!isset($autoload['App\\'])) {
            $autoload['App\\'] = 'app/';
        }
        
        // Always preserve Core module
        if (!isset($autoload['Modules\\Core\\'])) {
            $autoload['Modules\\Core\\'] = 'Modules/Core/src/';
        }
        
        // Get enabled modules from ModuleManager
        try {
            $moduleManager = app(\RCV\Core\Services\ModuleManager::class);
            $enabledModules = $moduleManager->getEnabledModules();
            
            // Remove old module entries (except Core)
            $autoload = array_filter($autoload, function($path, $namespace) {
                return !str_starts_with($namespace, 'Modules\\') || $namespace === 'Modules\\Core\\';
            }, ARRAY_FILTER_USE_BOTH);
            
            // Add enabled modules
            foreach ($enabledModules as $module) {
                $namespace = "Modules\\{$module}\\";
                $path = "Modules/{$module}/src/";
                
                // Only add if module directory exists
                if (File::exists(base_path($path))) {
                    $autoload[$namespace] = $path;
                    $this->info("Added {$module} module to autoload");
                } else {
                    $this->warn("Module {$module} directory not found at {$path}");
                }
            }
            
        } catch (\Exception $e) {
            $this->warn("Could not load ModuleManager: " . $e->getMessage());
            $this->info("Keeping existing autoload configuration");
        }
        
        // Update composer.json
        $composerJson['autoload']['psr-4'] = $autoload;
        
        $this->info('Writing composer.json with ' . count($autoload) . ' autoload entries...');
        foreach ($autoload as $ns => $path) {
            $this->line("  {$ns} => {$path}");
        }
        
        File::put(
            base_path('composer.json'),
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        // Run composer dump-autoload
        $this->info('Updating autoload files...');
        exec('composer dump-autoload');
        
        $this->info('Module autoload configuration updated successfully.');
        
        // Show what was configured
        $this->newLine();
        $this->info('Current autoload configuration:');
        foreach ($autoload as $namespace => $path) {
            if (str_starts_with($namespace, 'Modules\\') || $namespace === 'App\\') {
                $this->line("  {$namespace} => {$path}");
            }
        }
    }
} 