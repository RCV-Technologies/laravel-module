<?php

namespace RCV\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class ComposerDependencyManager
{
    protected $composerPath;
    protected $command;

    public function __construct(Command $command = null)
    {
        $this->composerPath = base_path('composer.json');
        $this->command = $command;
    }

    /**
     * Install dependencies for a module
     *
     * @param string $moduleName
     * @return bool
     */
    public function installModuleDependencies(string $moduleName): bool
    {
        $dependencies = $this->getModuleDependencies($moduleName);
        
        if (empty($dependencies)) {
            $this->log("No dependencies found for module [{$moduleName}]", 'info');
            return true;
        }

        $this->log("Installing dependencies for module [{$moduleName}]: " . implode(', ', array_keys($dependencies)), 'info');

        try {
            // Use composer require to add and install packages
            $this->runComposerRequire($dependencies);
            
            $this->log("Dependency installation process completed for module [{$moduleName}]", 'info');
            return true;
        } catch (\Exception $e) {
            $this->log("Some dependencies failed to install for module [{$moduleName}]: " . $e->getMessage(), 'warning');
            // Return true to continue with module enable even if some dependencies failed
            return true;
        }
    }

    /**
     * Remove dependencies for a module
     *
     * @param string $moduleName
     * @return bool
     */
    public function removeModuleDependencies(string $moduleName): bool
    {
        $dependencies = $this->getModuleDependencies($moduleName);
        
        if (empty($dependencies)) {
            $this->log("No dependencies to remove for module [{$moduleName}]", 'info');
            return true;
        }

        $this->log("Removing dependencies for module [{$moduleName}]: " . implode(', ', array_keys($dependencies)), 'info');

        try {
            // Check if dependencies are used by other enabled modules
            $dependenciesToRemove = $this->filterUnusedDependencies($dependencies);
            
            if (empty($dependenciesToRemove)) {
                $this->log("All dependencies are still used by other modules, skipping removal", 'info');
                return true;
            }

            // Run composer remove for unused packages
            $this->runComposerRemove(array_keys($dependenciesToRemove));
            
            $this->log("Successfully removed dependencies for module [{$moduleName}]", 'info');
            return true;
        } catch (\Exception $e) {
            $this->log("Failed to remove dependencies for module [{$moduleName}]: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get module dependencies from module.json
     *
     * @param string $moduleName
     * @return array
     */
    protected function getModuleDependencies(string $moduleName): array
    {
        $moduleJsonPath = base_path("Modules/{$moduleName}/module.json");
        
        if (!File::exists($moduleJsonPath)) {
            return [];
        }

        $moduleData = json_decode(File::get($moduleJsonPath), true);
        $dependencies = $moduleData['dependencies'] ?? [];
        
        // Parse dependencies array - support both "package:version" and "package" formats
        $parsedDependencies = [];
        foreach ($dependencies as $dependency) {
            if (is_string($dependency)) {
                if (strpos($dependency, ':') !== false) {
                    [$package, $version] = explode(':', $dependency, 2);
                    $parsedDependencies[trim($package)] = trim($version);
                } else {
                    $parsedDependencies[trim($dependency)] = '*';
                }
            }
        }

        return $parsedDependencies;
    }

    /**
     * Filter dependencies that are not used by other enabled modules
     *
     * @param array $dependencies
     * @return array
     */
    protected function filterUnusedDependencies(array $dependencies): array
    {
        $enabledModules = $this->getEnabledModules();
        $usedDependencies = [];

        // Collect all dependencies from enabled modules
        foreach ($enabledModules as $moduleName) {
            $moduleDependencies = $this->getModuleDependencies($moduleName);
            $usedDependencies = array_merge($usedDependencies, array_keys($moduleDependencies));
        }

        // Filter out dependencies that are still used
        $dependenciesToRemove = [];
        foreach ($dependencies as $package => $version) {
            if (!in_array($package, $usedDependencies)) {
                $dependenciesToRemove[$package] = $version;
            }
        }

        return $dependenciesToRemove;
    }

    /**
     * Get list of enabled modules
     *
     * @return array
     */
    protected function getEnabledModules(): array
    {
        $modules = [];
        $modulesPath = base_path('Modules');
        
        if (!File::exists($modulesPath)) {
            return $modules;
        }

        $directories = File::directories($modulesPath);
        foreach ($directories as $dir) {
            $moduleName = basename($dir);
            $moduleJsonPath = "{$dir}/module.json";
            
            if (File::exists($moduleJsonPath)) {
                $moduleData = json_decode(File::get($moduleJsonPath), true);
                if (isset($moduleData['enabled']) && $moduleData['enabled']) {
                    $modules[] = $moduleName;
                }
            }
        }

        return $modules;
    }

    /**
     * Run composer require to add and install packages
     *
     * @param array $dependencies
     * @return void
     */
    protected function runComposerRequire(array $dependencies): void
    {
        if (empty($dependencies)) {
            return;
        }

        // Process packages one by one to avoid issues with batch operations
        foreach ($dependencies as $package => $version) {
            $packageSpec = "{$package}:{$version}";
            $this->log("Running composer require for package: {$packageSpec}", 'info');
            
            $output = [];
            $exitCode = 0;
            
            $escapedPackage = escapeshellarg($packageSpec);
            exec("composer require {$escapedPackage} --optimize-autoloader --no-interaction 2>&1", $output, $exitCode);
            
            if ($exitCode !== 0) {
                $this->log("Failed to install package {$packageSpec}: " . implode("\n", $output), 'warning');
                // Continue with other packages instead of failing completely
                continue;
            }
            
            $this->log("Successfully installed package: {$packageSpec}", 'info');
        }
        
        $this->log("Composer require process completed", 'info');
    }

    /**
     * Run composer install (kept for backward compatibility)
     *
     * @return void
     */
    protected function runComposerInstall(): void
    {
        $this->log("Running composer install...", 'info');
        
        $output = [];
        $exitCode = 0;
        
        exec('composer install --no-dev --optimize-autoloader 2>&1', $output, $exitCode);
        
        if ($exitCode !== 0) {
            throw new \Exception("Composer install failed with exit code {$exitCode}: " . implode("\n", $output));
        }
        
        $this->log("Composer install completed successfully", 'info');
    }

    /**
     * Run composer remove
     *
     * @param array $packages
     * @return void
     */
    protected function runComposerRemove(array $packages): void
    {
        if (empty($packages)) {
            return;
        }

        // Process packages one by one to avoid issues with batch operations
        foreach ($packages as $package) {
            $this->log("Running composer remove for package: {$package}", 'info');
            
            $output = [];
            $exitCode = 0;
            
            $escapedPackage = escapeshellarg($package);
            exec("composer remove {$escapedPackage} --no-interaction 2>&1", $output, $exitCode);
            
            if ($exitCode !== 0) {
                $this->log("Failed to remove package {$package}: " . implode("\n", $output), 'warning');
                // Continue with other packages instead of failing completely
                continue;
            }
            
            $this->log("Successfully removed package: {$package}", 'info');
        }
        
        $this->log("Composer remove process completed", 'info');
    }

    /**
     * Log message to console and/or log file
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    protected function log(string $message, string $level = 'info'): void
    {
        // Log to Laravel log
        Log::{$level}($message);
        
        // Log to console if command is available
        if ($this->command) {
            switch ($level) {
                case 'error':
                    $this->command->error($message);
                    break;
                case 'warn':
                case 'warning':
                    $this->command->warn($message);
                    break;
                default:
                    $this->command->info($message);
                    break;
            }
        }
    }
}