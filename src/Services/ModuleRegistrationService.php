<?php

namespace RCV\Core\Services;

class ModuleRegistrationService
{
    public function registerModule(string $moduleName): bool
    {
        return ModuleAutoloader::register($moduleName);
    }

    public function unregisterModule(string $moduleName): bool
    {
        return ModuleAutoloader::unregister($moduleName);
    }

    public function getRegisteredModules(): array
    {
        return app(ModuleManager::class)->getEnabledModules();
    }
}
