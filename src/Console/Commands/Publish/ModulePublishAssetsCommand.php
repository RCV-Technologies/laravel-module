<?php

namespace RCV\Core\Console\Commands\Publish;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Exception;

class ModulePublishAssetsCommand extends Command
{
    protected $signature = 'module:publish-assets {module? : The name of the module} {--force : Overwrite existing assets} {--debug : Show debug information}';
    protected $description = 'Publish module assets to the public directory';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $force = $this->option('force');
        $debug = $this->option('debug');

        try {
            if ($moduleName) {
                $this->publishModuleAssets($moduleName, $force, $debug);
            } else {
                $this->publishAllModulesAssets($force, $debug);
            }
        } catch (Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            if ($debug) {
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
            return 1;
        }

        return 0;
    }

    public function publishModuleAssets($moduleName, $force = false, $debug = false)
    {
        // Validate module name
        if (empty($moduleName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $moduleName)) {
            $this->error("Invalid module name: {$moduleName}");
            return false;
        }

        $this->info("Publishing assets for module: {$moduleName}");

        $sourcePath = module_path($moduleName, 'Resources/assets');
        $destinationPath = public_path("Modules/" . strtolower($moduleName));

        if ($debug) {
            $this->showDebugInfo($moduleName, $sourcePath, $destinationPath);
        }

        // Check for alternative asset paths
        $alternativePaths = [
            module_path($moduleName, 'Resources/assets/'),
            module_path($moduleName, 'resources/assets'),
            module_path($moduleName, 'Assets'),
            module_path($moduleName, 'assets'),
            module_path($moduleName, 'public'),
        ];

        $foundPath = $this->findAssetsPath($alternativePaths);

        if (!$foundPath) {
            $this->error("No assets found for module: {$moduleName}");
            $this->line("Searched in:");
            foreach ($alternativePaths as $path) {
                $this->line("- {$path}");
            }
            return false;
        }

        return $this->copyAssets($foundPath, $destinationPath, $moduleName, $force);
    }

    public function publishAllModulesAssets($force = false, $debug = false)
    {
        $modulesPath = base_path('Modules');
        
        if (!File::exists($modulesPath) || !File::isDirectory($modulesPath)) {
            $this->error("Modules directory does not exist or is not a directory: {$modulesPath}");
            return false;
        }

        $modules = File::directories($modulesPath);

        if (empty($modules)) {
            $this->warning("No modules found in: {$modulesPath}");
            return false;
        }

        $successCount = 0;
        $totalCount = count($modules);

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            
            if ($this->publishModuleAssets($moduleName, $force, $debug)) {
                $successCount++;
            }
            
            $this->newLine();
        }

        $this->info("Published assets for {$successCount}/{$totalCount} modules.");
        return $successCount > 0;
    }

    private function showDebugInfo($moduleName, $sourcePath, $destinationPath)
    {
        $this->line("Debug Information:");
        $this->line("- Module base path: " . module_path($moduleName));
        $this->line("- Source path: {$sourcePath}");
        $this->line("- Destination path: {$destinationPath}");
        $this->line("- Source exists: " . (File::exists($sourcePath) ? 'YES' : 'NO'));
        
        // Show what directories exist in the module
        $moduleBasePath = module_path($moduleName);
        if (File::exists($moduleBasePath)) {
            $this->line("- Module directory exists");
            $directories = File::directories($moduleBasePath);
            $this->line("- Available directories in module:");
            foreach ($directories as $dir) {
                $this->line("  * " . basename($dir));
            }
            
            // Check for Resources directory
            $resourcesPath = "{$moduleBasePath}/Resources";
            if (File::exists($resourcesPath)) {
                $this->line("- Resources directory exists");
                $resourceDirs = File::directories($resourcesPath);
                $this->line("- Available directories in Resources:");
                foreach ($resourceDirs as $dir) {
                    $this->line("  * " . basename($dir));
                }
            } else {
                $this->line("- Resources directory does NOT exist");
            }
        } else {
            $this->line("- Module directory does NOT exist");
        }
    }

    private function findAssetsPath(array $paths)
    {
        foreach ($paths as $path) {
            if (File::exists($path) && File::isDirectory($path)) {
                $this->info("Found assets in: {$path}");
                return $path;
            }
        }
        
        return null;
    }

    private function copyAssets($sourcePath, $destinationPath, $moduleName, $force = false)
    {
        try {
            // Ensure the destination directory exists
            if (!File::ensureDirectoryExists($destinationPath)) {
                $this->error("Failed to create destination directory: {$destinationPath}");
                return false;
            }

            $files = File::allFiles($sourcePath);
            
            if (empty($files)) {
                $this->warning("Assets directory exists but contains no files for module: {$moduleName}");
                return false;
            }

            $copiedFiles = 0;
            $skippedFiles = 0;

            foreach ($files as $file) {
                $relativePath = $this->getRelativePath($sourcePath, $file->getRealPath());
                $destination = $destinationPath . DIRECTORY_SEPARATOR . $relativePath;
                
                // Ensure subdirectories exist
                $destinationDir = dirname($destination);
                if (!File::ensureDirectoryExists($destinationDir)) {
                    $this->error("Failed to create directory: {$destinationDir}");
                    continue;
                }
                
                if (File::exists($destination) && !$force) {
                    $this->line("Asset already exists (use --force to overwrite): {$relativePath}");
                    $skippedFiles++;
                    continue;
                }

                if (!File::copy($file->getRealPath(), $destination)) {
                    $this->error("Failed to copy: {$relativePath}");
                    continue;
                }

                $this->info("Published: {$relativePath}");
                $copiedFiles++;
            }

            $this->info("Assets for module {$moduleName} published successfully. Copied: {$copiedFiles}, Skipped: {$skippedFiles}");
            return $copiedFiles > 0;

        } catch (Exception $e) {
            $this->error("Error copying assets for module {$moduleName}: " . $e->getMessage());
            return false;
        }
    }

    private function getRelativePath($basePath, $filePath)
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_replace($basePath, '', $filePath);
    }
}
