<?php

return [
    'name' => 'Core',
    'description' => 'Core module for managing other modules',
    'version' => '1.0.0',
    'enabled' => true,
    'dependencies' => [],

    'autoload' => [
        'auto_cleanup' => true,
        'core_modules' => ['Core'],
    ],

    'paths' => [
        'modules' => base_path('Modules'),
        'generator' => [
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
        ],
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],

    'pagination' => [
        'per_page' => 10,
        'max_per_page' => 100,
    ],

    'lazy_load_modules' => false,
    'module_warmup_enabled' => false,
    'max_boot_failures' => 3,
    'fail_fast_on_error' => false,
    'strict_mode' => false,
    'hot_reload' => false,
    'performance_monitoring' => false,
    'seed_modules' => false,

    'messaging' => [
        'queue' => null,
        'after_commit' => false,
        'tries' => 5,
        'backoff' => [5, 15, 60],
    ],
];
