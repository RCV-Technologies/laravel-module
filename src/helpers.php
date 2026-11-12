<?php

use RCV\Core\Services\ModuleManager;
use RCV\Core\Services\ModuleNameValidator;

if (! function_exists('module_path')) {
    /**
     * Get the path to a module directory.
     */
    function module_path(string $module, string $path = ''): string
    {
        if (! ModuleNameValidator::isValid($module)) {
            throw new InvalidArgumentException('Invalid module name.');
        }

        $segments = preg_split('~[\\\\/]~', $path) ?: [];
        if (in_array('..', $segments, true)
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/\A[A-Za-z]:/', $path) === 1
            || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Module path must stay within the module directory.');
        }

        $modulePath = base_path('Modules/'.$module);

        return $path ? $modulePath.'/'.ltrim($path, '/') : $modulePath;
    }
}

if (! function_exists('module_config')) {
    /**
     * Get module configuration value.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function module_config($key, $default = null)
    {
        return config('modules.'.$key, $default);
    }
}

if (! function_exists('module_enabled')) {
    /**
     * Check if a module is enabled.
     *
     * @param  string  $module
     * @return bool
     */
    function module_enabled($module)
    {
        return app(ModuleManager::class)->isModuleEnabled($module);
    }
}

if (! function_exists('module_namespace')) {
    /**
     * Get the namespace for a module.
     *
     * @param  string  $module
     * @return string
     */
    function module_namespace($module)
    {
        return 'Modules\\'.ucfirst($module);
    }
}
