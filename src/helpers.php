<?php

if (!function_exists('module_path')) {
    /**
     * Get the path to a module directory.
     *
     * @param string $module
     * @param string $path
     * @return string
     */
    function module_path($module, $path = '')
    {
        $modulePath = base_path('Modules/' . $module);
        return $path ? $modulePath . '/' . ltrim($path, '/') : $modulePath;
    }
}

if (!function_exists('module_config')) {
    /**
     * Get module configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function module_config($key, $default = null)
    {
        return config('modules.' . $key, $default);
    }
}

if (!function_exists('module_enabled')) {
    /**
     * Check if a module is enabled.
     *
     * @param string $module
     * @return bool
     */
    function module_enabled($module)
    {
        return app(\Rcv\Core\Services\ModuleManager::class)->isEnabled($module);
    }
}

if (!function_exists('module_namespace')) {
    /**
     * Get the namespace for a module.
     *
     * @param string $module
     * @return string
     */
    function module_namespace($module)
    {
        return 'Modules\\' . ucfirst($module);
    }
} 