<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ModuleAllCommands extends Command
{
    protected $signature = 'module:commands';

    protected $description = 'Display a table of module commands and allow selection';

    public const COLOR = '<fg=#684df4;options=bold>';

    protected array $makeCommandDescriptions = [
        'module:actions' => 'Show module actions.',
        'module:autoload' => 'Register enabled modules for the current process.',
        'module:commands' => 'List all module commands.',
        'module:debug' => 'Debug module setup.',
        'module:disable' => 'Disable a module.',
        'module:enable' => 'Enable a module.',
        'module:health' => 'Check the health of the module.',
        'module:make' => 'Create a new module.',
        'module:make-action' => 'Create a new action class.',
        'module:make-cast' => 'Create a new Eloquent cast class.',
        'module:make-channel' => 'Create a new broadcast channel class.',
        'module:make-class' => 'Create a generic class using stub.',
        'module:make-command' => 'Create a new Artisan command.',
        'module:make-component' => 'Create a new Blade component class.',
        'module:make-component-view' => 'Create a new component view.',
        'module:make-controller' => 'Generate a controller class.',
        'module:make-enum' => 'Create a PHP Enum class.',
        'module:make-event' => 'Create a new event class.',
        'module:make-event-provider' => 'Generate a new event service provider.',
        'module:make-exception' => 'Create a new custom exception class.',
        'module:make-factory' => 'Generate a model factory.',
        'module:make-helper' => 'Generate a helper class.',
        'module:make-interface' => 'Create a new interface class.',
        'module:make-job' => 'Generate a queued job class.',
        'module:make-listener' => 'Create a new event listener.',
        'module:make-mail' => 'Generate a mailable class.',
        'module:make-migration' => 'Create a database migration.',
        'module:make-model' => 'Generate a model class.',
        'module:make-notification' => 'Create a new notification class.',
        'module:make-observer' => 'Generate a model observer.',
        'module:make-policy' => 'Create a policy class.',
        'module:make-repository' => 'Generate a repository class.',
        'module:make-request' => 'Generate a form request class.',
        'module:make-resource' => 'Create a new API resource class.',
        'module:make-route-provider' => 'Create a RouteServiceProvider class.',
        'module:make-rule' => 'Generate a custom validation rule.',
        'module:make-scope' => 'Generate a model query scope.',
        'module:make-seeder' => 'Generate a database seeder.',
        'module:make-service' => 'Create a service class.',
        'module:make-trait' => 'Create a reusable PHP trait.',
        'module:make-view' => 'Create a new Blade view file.',
        'module:marketplace' => 'Marketplace module operations.',
        'module:middleware' => 'List module middlewares.',
        'module:migrate' => 'Run module migrations.',
        'module:migrate-fresh' => 'Drop and re-run all migrations.',
        'module:migrate-one' => 'Run a specific module migration.',
        'module:migrate-refresh' => 'Reset and re-run all migrations.',
        // 'module:migrate-reset' => 'Reset all migrations.',
        'module:migrate-rollback' => 'Rollback the last migration operation.',
        'module:migrate-status' => 'Show the status of each migration.',
        'module:model-show' => 'Show details of a model.',
        'module:prune' => 'Prune obsolete data.',
        // 'module:publish-assets' => 'Publish module assets.',
        'module:publish-config' => 'Publish module configuration.',
        'module:publish-migration' => 'Publish module migrations.',
        // 'module:publish-translation' => 'Publish module translations.',
        'module:remove' => 'Remove a module.',
        'module:seed' => 'Seed the database.',
        'module:state' => 'Show module state.',
        'module:unuse' => 'Unset active module.',
        'module:update-phpunit-coverage' => 'Update PHPUnit coverage config.',
        'module:use' => 'Set active module.',
        'module:v2:migrate' => 'Run version 2 migrations.',
        'list:commands' => 'List all available Artisan commands.',
        'module:check-updates' => 'Check for available module updates.',
        // 'module:lang' => 'Manage or list module language files.',
        'module:seeder-list' => 'List all registered database seeders.',
        'module:make-middleware' => 'Create a new middleware class.',
    ];

    protected function getCommandArgsMap(): array
    {
        return [
            'module:make' => ['name'],
            'module:disable' => ['module'],
            'module:enable' => ['module'],
            'module:remove' => ['module'],
            'module:use' => ['module'],
            'module:model-show' => ['module'],
            'module:prune' => ['module'],
            // 'module:lang'=> ['module'],
            'module:seed' => ['module'],
            // 'module:publish-translation'=> ['module'],
            'module:publish-migration' => ['module'],
            'module:publish-config' => ['module'],
            'module:make-component-view' => ['module', 'name'],
            'module:migrate-one' => ['migration_name', 'module_name'],
            'module:marketplace' => ['action', 'module'],
            'module:make-action' => ['name', 'module'],
            'module:make-cast' => ['name', 'module'],
            'module:make-channel' => ['name', 'module'],
            'module:make-class' => ['name', 'module'],
            'module:make-command' => ['name', 'module'],
            'module:make-component' => ['name', 'module'],
            'module:make-controller' => ['name', 'module'],
            'module:make-enum' => ['name', 'module'],
            'module:make-event' => ['name', 'module'],
            'module:make-event-provider' => ['name', 'module'],
            'module:make-exception' => ['name', 'module'],
            'module:make-factory' => ['name', 'module'],
            'module:make-helper' => ['name', 'module'],
            'module:make-interface' => ['name', 'module'],
            'module:make-job' => ['name', 'module'],
            'module:make-listener' => ['name', 'module', 'event'],
            'module:make-mail' => ['name', 'module'],
            'module:make-migration' => ['name', 'module'],
            'module:make-model' => ['name', 'module'],
            'module:make-notification' => ['name', 'module'],
            'module:make-observer' => ['name', 'module'],
            'module:make-policy' => ['name', 'module'],
            'module:make-repository' => ['name', 'module'],
            'module:make-request' => ['name', 'module'],
            'module:make-resource' => ['name', 'module'],
            'module:make-route-provider' => ['module'],
            'module:make-rule' => ['name', 'module'],
            'module:make-scope' => ['name', 'module'],
            'module:make-seeder' => ['name', 'module'],
            'module:make-service' => ['name', 'module'],
            'module:make-trait' => ['name', 'module'],
            'module:make-view' => ['name', 'module'],
            'module:make-middleware' => ['name', 'module'],
        ];
    }

    /**
     * Get commands that don't require any arguments
     */
    protected function getNoArgsCommands(): array
    {
        return [
            'module:seeder-list',
            'module:actions',
            'module:autoload',
            'module:commands',
            'module:debug',
            'module:health',
            'module:middleware',
            'module:migrate',
            'module:migrate-fresh',
            'module:migrate-refresh',
            // 'module:migrate-reset',
            'module:migrate-rollback',
            'module:migrate-status',
            'module:state',
            'module:unuse',
            'module:update-phpunit-coverage',
            'module:v2:migrate',
            'list:commands',
            'module:check-updates',
        ];
    }

    public function handle()
    {
        $categories = [
            ['Number' => 1, 'Categories' => 'Actions'],
            ['Number' => 2, 'Categories' => 'Database'],
            ['Number' => 3, 'Categories' => 'Make'],
            ['Number' => 4, 'Categories' => 'Publish'],
            ['Number' => 5, 'Categories' => 'All Commands'],
        ];

        $this->table([self::COLOR.'No', self::COLOR.'Categories'], $categories);
        $this->newLine();

        $choice = (int) $this->ask(self::COLOR.'👉 Enter a number to view its commands');

        match ($choice) {
            1 => $this->handleGroup(self::COLOR.'Actions Commands', [
                'module:check-updates', 'list:commands', 'module:disable',
                'module:enable', 'module:marketplace', 'module:prune', 'module:model-show',
            ]),

            2 => $this->handleDatabaseSubMenu(),
            3 => $this->handleGroup(self::COLOR.'Make Commands', ['module:make']),
            4 => $this->handleGroup(self::COLOR.'Publish Commands', ['module:publish']),
            5 => $this->handleGroup(self::COLOR.'All Module Commands', ['module:']),
            6 => $this->handleMarketplaceMenu(),
            default => $this->error(self::COLOR.'Invalid selection.'),
        };
    }

    protected function handleMarketplaceMenu()
    {
        $this->info(self::COLOR.'Marketplace Commands');

        $actions = [
            ['Number' => 1, 'Action' => 'list', 'Description' => 'List available modules'],
            ['Number' => 2, 'Action' => 'install', 'Description' => 'Install one or more modules'],
            ['Number' => 3, 'Action' => 'remove', 'Description' => 'Remove one or more modules'],
            ['Number' => 4, 'Action' => 'update', 'Description' => 'Update one or more modules'],
            ['Number' => 5, 'Action' => 'cleanup', 'Description' => 'Cleanup marketplace cache'],
        ];

        $this->table(
            [self::COLOR.'No', self::COLOR.'Action', self::COLOR.'Description'],
            $actions
        );
        $this->newLine();

        $choice = (int) $this->ask(self::COLOR.'👉 Select marketplace action');

        $actionMap = [
            1 => 'list',
            2 => 'install',
            3 => 'remove',
            4 => 'update',
            5 => 'cleanup',
        ];

        if (! isset($actionMap[$choice])) {
            $this->error(self::COLOR.'Invalid selection.');

            return;
        }

        $selectedAction = $actionMap[$choice];
        $this->runMarketplaceCommand($selectedAction);
    }

    protected function runMarketplaceCommand(string $action)
    {
        $args = ['action' => $action];

        // For actions that require module names
        if (in_array($action, ['install', 'remove', 'update'])) {
            $names = $this->ask(self::COLOR.'📦 Enter module name(s) (comma-separated for multiple)');
            if (! empty($names)) {
                $moduleNames = array_map('trim', explode(',', $names));
                $args['module'] = $moduleNames;
            }
        }

        // Ask for force option
        if ($this->confirm(self::COLOR.'💪 Use --force option?', false)) {
            $args['--force'] = true;
        }

        // Display command being run
        $argsDisplay = [$action];
        if (isset($args['module']) && is_array($args['module'])) {
            $argsDisplay = array_merge($argsDisplay, $args['module']);
        }
        if (isset($args['--force'])) {
            $argsDisplay[] = '--force';
        }

        $this->info(self::COLOR.'🚀 Running: php artisan module:marketplace '.implode(' ', $argsDisplay));
        $this->newLine();

        Artisan::call('module:marketplace', $args);
        $this->line(Artisan::output());
    }

    protected function handleDatabaseSubMenu()
    {
        $groups = [
            ['Number' => 1, 'Categories' => 'Migrations'],
            ['Number' => 2, 'Categories' => 'Seeders'],
            ['Number' => 3, 'Categories' => 'Factories'],
            ['Number' => 4, 'Categories' => 'All'],
        ];

        $this->table([self::COLOR.'No', self::COLOR.'Type'], $groups);
        $this->newLine();

        $subChoice = (int) $this->ask(self::COLOR.'👉 Select a database sub-category');

        match ($subChoice) {
            1 => $this->handleGroup(self::COLOR.'Migration Commands', ['module:migrate']),
            2 => $this->handleGroup(self::COLOR.'Seeder Commands', ['module:seed', 'module:seeder-list', 'module:make-seeder']),
            3 => $this->handleGroup(self::COLOR.'Factory Commands', ['module:make-factory']),
            4 => $this->handleGroup(self::COLOR.'All Database Commands', ['module:migrate', 'module:seed', 'module:factory']),
            default => $this->error(self::COLOR.'Invalid sub-category.'),
        };
    }

    protected function handleGroup(string $title, array $prefixes)
    {
        $this->info($title);
        $commands = $this->getCommandsByPrefixes($prefixes);

        if (empty($commands)) {
            $this->warn(self::COLOR.'No commands found.');

            return;
        }

        $indexed = [];

        foreach ($commands as $index => $command) {
            $indexed[] = [
                self::COLOR.'Index' => $index + 1,
                self::COLOR.'Command' => $command,
                self::COLOR.'Description' => $this->makeCommandDescriptions[$command] ?? '—',
            ];
        }

        $this->table(
            [self::COLOR.'Index', self::COLOR.'Command', self::COLOR.'Description'],
            $indexed
        );

        $input = (int) $this->ask(self::COLOR.'👉 Enter the index of the command to run (or 0 to skip)');

        if ($input > 0 && isset($commands[$input - 1])) {
            $selectedCommand = $commands[$input - 1];
            $this->runCommandWithArgs($selectedCommand);
        } elseif ($input !== 0) {
            $this->error(self::COLOR.'Invalid index.');
        }
    }

    protected function runCommandWithArgs(string $selectedCommand)
    {
        $commandArgsMap = $this->getCommandArgsMap();
        $noArgsCommands = $this->getNoArgsCommands();
        $args = [];

        // Check if command requires no arguments
        if (in_array($selectedCommand, $noArgsCommands)) {
            $this->info(self::COLOR."🚀 Running: php artisan $selectedCommand");
            $this->newLine();

            Artisan::call($selectedCommand);
            $this->line(Artisan::output());

            return;
        }

        // Handle commands with arguments
        if (array_key_exists($selectedCommand, $commandArgsMap)) {
            // Special handling for marketplace command
            if ($selectedCommand === 'module:marketplace') {
                $this->runMarketplaceCommandFromGroup();

                return;
            }

            foreach ($commandArgsMap[$selectedCommand] as $arg) {
                // Skip empty arguments
                if (empty($arg)) {
                    continue;
                }

                $question = match ($arg) {
                    'name' => $selectedCommand === 'module:make'
                        ? '📝 Give the module name (comma-separated for multiple)'
                        : '📝 Give the class or directory name',
                    'module' => '📦 Give the module name',
                    'names' => '📝 Give the module names (comma-separated)',
                    'action' => '🎯 Select action (list|install|remove|update|cleanup)',
                    'migration_name' => '📝 Give the migration name',
                    'module_name' => '📦 Give the module name',
                    'event' => '📝 Give the event name (optional)',
                    default => "✏️  Enter value for <comment>$arg</comment>",
                };

                $value = $this->ask(self::COLOR.$question);

                // Skip if value is empty and it's an optional parameter
                if (empty($value) && $arg === 'event') {
                    continue;
                }

                // Special handling for commands that need arrays
                if ($selectedCommand === 'module:make' && $arg === 'name') {
                    // Split comma-separated names and pass as array
                    $names = array_map('trim', explode(',', $value));
                    $args['name'] = $names;
                } elseif (($selectedCommand === 'module:disable' || $selectedCommand === 'module:enable') && $arg === 'module') {
                    // Split comma-separated module names and trim whitespace
                    $names = array_map('trim', explode(',', $value));
                    $args['module'] = $names;
                } else {
                    // For all other commands, pass as regular string
                    $args[$arg] = $value;
                }
            }

            // Safe display of command arguments
            $argsDisplay = [];
            foreach ($args as $key => $value) {
                if (is_array($value)) {
                    $argsDisplay[] = "$key=".implode(',', $value);
                } else {
                    $argsDisplay[] = "$key=$value";
                }
            }

            $this->info(self::COLOR."🚀 Running: php artisan $selectedCommand ".implode(' ', $argsDisplay));
            $this->newLine();

            Artisan::call($selectedCommand, $args);
            $this->line(Artisan::output());
        } else {
            // For unmapped commands, try running without arguments first
            $this->info(self::COLOR."🚀 Running: php artisan $selectedCommand (no arguments required)");
            $this->newLine();

            try {
                Artisan::call($selectedCommand);
                $this->line(Artisan::output());
            } catch (\Exception $e) {
                $this->warn(self::COLOR."⚠️  This command may require arguments. You'll need to run it manually.");
                $this->error($e->getMessage());
            }
        }
    }

    protected function runMarketplaceCommandFromGroup()
    {
        $action = $this->ask(self::COLOR.'🎯 Select action (list|install|remove|update|cleanup)');

        $args = ['action' => $action];

        // For actions that require module names
        if (in_array($action, ['install', 'remove', 'update'])) {
            $names = $this->ask(self::COLOR.'📦 Enter module name(s) (comma-separated for multiple)');
            if (! empty($names)) {
                $moduleNames = array_map('trim', explode(',', $names));
                $args['module'] = $moduleNames;
            }
        }

        // Ask for force option
        if ($this->confirm(self::COLOR.'💪 Use --force option?', false)) {
            $args['--force'] = true;
        }

        // Display command being run
        $argsDisplay = [$action];
        if (isset($args['module']) && is_array($args['module'])) {
            $argsDisplay = array_merge($argsDisplay, $args['module']);
        }
        if (isset($args['--force'])) {
            $argsDisplay[] = '--force';
        }

        $this->info(self::COLOR.'🚀 Running: php artisan module:marketplace '.implode(' ', $argsDisplay));
        $this->newLine();

        Artisan::call('module:marketplace', $args);
        $this->line(Artisan::output());
    }

    protected function getCommandsByPrefixes(array $prefixes): array
    {
        $allCommands = Artisan::all();
        $matched = [];

        foreach ($allCommands as $name => $command) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $matched[] = $name;
                    break;
                }
            }
        }

        sort($matched);

        return $matched;
    }
}
