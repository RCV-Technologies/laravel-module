<?php

namespace RCV\Core\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ComposerDependencyManager
{
    public function __construct(protected ?Command $command = null) {}

    public function installModuleDependencies(string $moduleName): bool
    {
        try {
            $dependencies = $this->getModuleDependencies($moduleName);
        } catch (\InvalidArgumentException $exception) {
            Log::warning($exception->getMessage());

            return false;
        }
        if ($dependencies === []) {
            return true;
        }

        $this->log(
            "Automatic Composer dependency installation is disabled for module [{$moduleName}]. Install its dependencies through your trusted deployment workflow.",
            'warning'
        );

        return false;
    }

    public function removeModuleDependencies(string $moduleName): bool
    {
        $this->log(
            "Composer dependencies for module [{$moduleName}] were left unchanged; the package does not remove application dependencies automatically.",
            'info'
        );

        return true;
    }

    protected function getModuleDependencies(string $moduleName): array
    {
        if (! ModuleNameValidator::isValid($moduleName)) {
            return [];
        }

        $moduleJsonPath = module_path($moduleName, 'module.json');
        if (! File::exists($moduleJsonPath)) {
            return [];
        }

        $moduleData = ModuleManifestValidator::read($moduleName, $moduleJsonPath);
        $dependencies = $moduleData['dependencies'] ?? [];

        return is_array($dependencies) ? $dependencies : [];
    }

    protected function log(string $message, string $level): void
    {
        Log::{$level}($message);

        if ($this->command !== null) {
            $this->command->line($message);
        }
    }
}
