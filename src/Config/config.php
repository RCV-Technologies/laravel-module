<?php

return array (
  'name' => 'Core',
  'description' => 'Core module for managing other modules',
  'version' => '1.0.0',
  'enabled' => true,
  'dependencies' => 
  array (
  ),
  'autoload' => 
  array (
    'auto_cleanup' => true,
    'core_modules' => 
    array (
      0 => 'Core',
    ),
  ),
  'paths' => 
  array (
    'modules' => '/var/www/html/RCVCore/Modules',
    'generator' => 
    array (
      'config' => 'src/Config',
      'command' => 'src/Console/Commands',
      'migration' => 'src/Database/Migrations',
      'seeder' => 'src/Database/Seeders',
      'factory' => 'src/Database/Factories',
      'model' => 'src/Models',
      'repository' => 'src/Repositories',
      'service' => 'src/Services',
      'controller' => 'src/Http/Controllers',
      'middleware' => 'src/Http/Middleware',
      'request' => 'src/Http/Requests',
      'provider' => 'src/Providers',
      'assets' => 'src/Resources/assets',
      'lang' => 'src/Resources/lang',
      'views' => 'src/Resources/views',
      'routes' => 'src/Routes',
      'test' => 'tests',
    ),
  ),
  'cache' => 
  array (
    'enabled' => true,
    'ttl' => 3600,
  ),
  'pagination' => 
  array (
    'per_page' => 10,
    'max_per_page' => 100,
  ),
  'modules' => 
  array (
    0 => 'UserManagement',
  ),
  'lazy_load_modules' => false,
  'module_warmup_enabled' => false,
  'max_boot_failures' => 3,
  'fail_fast_on_error' => false,
  'strict_mode' => false,
  'hot_reload' => false,
  'performance_monitoring' => false,
  'seed_modules' => false,
  'messaging' => 
  array (
    'queue' => NULL,
    'after_commit' => false,
    'tries' => 5,
    'backoff' => 
    array (
      0 => 5,
      1 => 15,
      2 => 60,
    ),
  ),
);
