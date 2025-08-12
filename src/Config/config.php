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
  'commands' => 
  array (
    0 => 'Rcv\\Core\\Console\\Commands\\ModuleAutoloadCommand',
    1 => 'Rcv\\Core\\Console\\Commands\\Make\\ModuleMakeCommand',
    2 => 'Rcv\\Core\\Console\\Commands\\Make\\ModuleModelMakeCommand',
    3 => 'Rcv\\Core\\Console\\Commands\\Make\\ModuleControllerMakeCommand',
    4 => 'Rcv\\Core\\Console\\Commands\\Make\\ModuleResourceMakeCommand',
    5 => 'Rcv\\Core\\Console\\Commands\\Actions\\ModuleEnableCommand',
    6 => 'Rcv\\Core\\Console\\Commands\\Actions\\ModuleDisableCommand',
    7 => 'Rcv\\Core\\Console\\Commands\\Actions\\ModuleMarketplaceCommand',
    8 => 'Rcv\\Core\\Console\\Commands\\ModuleHealthCheckCommand',
    9 => 'Rcv\\Core\\Console\\Commands\\Database\\Migrations\\ModuleMigrateCommand',
    10 => 'Rcv\\Core\\Console\\Commands\\Make\\ModuleMiddlewareCommand',
  ),
  'paths' => 
  array (
    'modules' => 'C:\\Users\\Day Shift\\Desktop\\Demo3\\demo\\modules',
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
    0 => 'Individual',
    1 => 'Agency',
    2 => 'Socialmanagement',
    3 => 'CollageStudent',
    4 => 'Test',
    5 => 'Student',
    6 => 'Demo',
    7 => 'Class',
    8 => 'Nest',
    9 => 'Section',
    10 => 'Test1',
    11 => 'Test2',
    12 => 'Demo1',
    13 => 'Demo2',
    14 => 'Test3',
    15 => 'Test4',
    16 => 'Test5',
    17 => 'Demo3',
    18 => 'Demo4',
    19 => 'Colleger',
    20 => 'UserManagement',
    21 => 'Employee',
    22 => 'Staff',
    23 => 'Candidate',
    24 => 'Help',
    25 => 'CollegeManagement',
  ),
);
