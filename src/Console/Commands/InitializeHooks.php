<?php

namespace RCV\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RCV\Core\Services\ModuleManager;

class InitializeHooks extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'module:initialize-hooks';

    /**
     * The console command description.
     */
    protected $description = 'Initialize and enable the optional Hook System for active modules.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Initializing Hook System...');

        /*
        |--------------------------------------------------------------------------
        | Publish Hook Config
        |--------------------------------------------------------------------------
        */

        $packageConfigPath = __DIR__ . '/../../Config/rcv-hooks.php';

        $destinationConfigPath = config_path('rcv-hooks.php');

        if (! File::exists($packageConfigPath)) {
            $this->components->error(
                'Hook configuration template not found in package.'
            );

            return self::FAILURE;
        }

        // Read package config content
        $configContent = File::get($packageConfigPath);

        // Enable hooks by default after initialization
        $configContent = str_replace(
            "'enabled' => false,",
            "'enabled' => true,",
            $configContent
        );

        // Ensure config directory exists
        File::ensureDirectoryExists(
            dirname($destinationConfigPath)
        );

        // Publish config
        File::put(
            $destinationConfigPath,
            $configContent
        );

        $this->components->task(
            'Published rcv-hooks.php configuration file (enabled = true)',
            fn () => true
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Hook Structure
        |--------------------------------------------------------------------------
        */

        $this->components->task(
            'Verifying Hook system package structure',
            fn () => File::isDirectory(__DIR__ . '/../../Hooks')
        );

        /*
        |--------------------------------------------------------------------------
        | Generate Sample Listener
        |--------------------------------------------------------------------------
        */

        if (! app()->bound(ModuleManager::class)) {

            $this->components->warn(
                'ModuleManager not bound. Skipping active modules scan.'
            );

            $this->components->info(
                'Hook System initialized successfully!'
            );

            return self::SUCCESS;
        }

        /** @var ModuleManager $moduleManager */
        $moduleManager = app(ModuleManager::class);

        $activeModules = $moduleManager->getEnabledModules();

        if (empty($activeModules)) {

            $this->components->warn(
                'No active modules found. Skipping sample listener creation.'
            );

            $this->components->info(
                'Hook System initialized successfully!'
            );

            return self::SUCCESS;
        }

        // Safely get first active module
        foreach ($activeModules as $module) {

        // Extract module name safely
        $moduleName = is_object($module)
            ? ($module->name ?? method_exists($module, 'getName')
                ? $module->getName()
                : 'UnknownModule')
            : (string) $module;

        $moduleHooksDir = base_path(
            "Modules/{$moduleName}/src/Hooks"
        );

        // Ensure module hook directory exists
        File::ensureDirectoryExists($moduleHooksDir);

        $sampleListenerPath = $moduleHooksDir . '/ExampleHookListener.php';

        if (! File::exists($sampleListenerPath)) {

            $sampleContent = $this->getSampleListenerTemplate(
                $moduleName
            );

            File::put(
                $sampleListenerPath,
                $sampleContent
            );

            $this->components->task(
                "Created sample listener inside module [{$moduleName}]",
                fn () => true
            );

        } else {

            $this->components->task(
                "Sample listener already exists inside module [{$moduleName}]",
                fn () => true
            );
        }
    }


        /*
        |--------------------------------------------------------------------------
        | Final Success Message
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->components->info(
            'Hook System initialized and enabled successfully!'
        );

        $this->line('');
        $this->line('Next Steps:');
        $this->line('- Run: php artisan optimize:clear');
        $this->line('- Run: php artisan config:cache');
        $this->line('- Create listeners inside Modules/{Module}/src/Hooks');
        $this->line('- Register hooks using Hook::listen()');

        return self::SUCCESS;
    }

    /**
     * Generate sample listener template.
     */
    protected function getSampleListenerTemplate(
        string $moduleName
    ): string {
        return <<<PHP
<?php

namespace Modules\\{$moduleName}\\Hooks;

use RCV\Core\Hooks\Contracts\HookListenerInterface;

class ExampleHookListener implements HookListenerInterface
{
    /**
     * Get hook name.
     */
    public static function getHook(): string
    {
        return 'module.example_hook';
    }

    /**
     * Get listener priority.
     */
    public static function getPriority(): int
    {
        return 10;
    }

    /**
     * Handle hook execution.
     */
    public function handle(...\$args)
    {
        logger(
            'ExampleHookListener triggered for module {$moduleName}',
            \$args
        );

        return 'Example output from {$moduleName}';
    }
}
PHP;
    }
}