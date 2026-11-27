<?php

namespace RCV\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getAvailableModules()
 * @method static array getEnabledModules()
 * @method static bool isModuleEnabled(string $moduleName)
 * @method static array getModuleState(string $moduleName)
 * @method static bool enableModule(string $moduleName)
 * @method static bool disableModule(string $moduleName)
 * @method static void refreshEnabledModulesCache()
 * @method static array getLoadableEnabledModules()
 *
 * @see \RCV\Core\Services\ModuleManager
 */
class Module extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \RCV\Core\Services\ModuleManager::class;
    }
}
