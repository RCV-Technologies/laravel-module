<?php

namespace RCV\Core\Services;

use Composer\Autoload\ClassLoader;
use Illuminate\Support\Str;

class ModuleAutoloader
{
    public static function register(string $moduleName): bool
    {
        if (! ModuleNameValidator::isValid($moduleName)) {
            return false;
        }

        $modulePath = module_path($moduleName, 'src');
        if (! is_dir($modulePath)) {
            return false;
        }

        $namespace = 'Modules\\'.Str::studly($moduleName).'\\';
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4($namespace, $modulePath);
        }

        return true;
    }

    public static function unregister(string $moduleName): bool
    {
        if (! ModuleNameValidator::isValid($moduleName)) {
            return false;
        }

        $namespace = 'Modules\\'.Str::studly($moduleName).'\\';
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->setPsr4($namespace, []);
        }

        return true;
    }
}
