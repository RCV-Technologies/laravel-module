<?php

namespace Rcv\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

/**
 * ModuleAutoloadCommand
 * 
 * This command maintains the application's module autoloading configuration by ensuring
 * that only the Core module is registered in the main composer.json file. This approach
 * keeps the autoloading configuration clean and prevents issues with removed modules
 * leaving behind their autoload entries.
 *
 * Key features:
 * - Removes non-Core module entries from composer.json
 * - Ensures Core module is properly registered
 * - Automatically updates autoload files
 *
 * Usage:
 * ```bash
 * php artisan module:autoload
 * ```
 *
 * This command should be run:
 * - After removing a module
 * - When cleaning up module configurations
 * - When fixing autoload issues
 *
 * @package Rcv\Core\Console\Commands
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
    protected $description = 'Update module autoload configuration';

    /**
     * Execute the console command.
     *
     * This method:
     * 1. Reads the current composer.json
     * 2. Filters out non-Core module autoload entries
     * 3. Ensures Core module is properly registered
     * 4. Updates composer.json
     * 5. Runs composer dump-autoload
     *
     * @return void
     */
    public function handle()
    {
        $modulesPath = base_path('Modules');
        $modules = File::directories($modulesPath);
        
        $composerJson = json_decode(File::get(base_path('composer.json')), true);
        $autoload = $composerJson['autoload']['psr-4'] ?? [];
        
        // Keep only Core module in main composer.json
        $autoload = array_filter($autoload, function($path, $namespace) {
            return $namespace === 'Modules\\Core\\';
        }, ARRAY_FILTER_USE_BOTH);
        
        // Add Core module back if not present
        if (!isset($autoload['Modules\\Core\\'])) {
            $autoload['Modules\\Core\\'] = 'Modules/Core/src/';
        }
        
        // Update composer.json
        $composerJson['autoload']['psr-4'] = $autoload;
        File::put(
            base_path('composer.json'),
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        // Run composer dump-autoload
        $this->info('Updating autoload files...');
        exec('composer dump-autoload');
        
        $this->info('Module autoload configuration updated successfully.');
    }
} 