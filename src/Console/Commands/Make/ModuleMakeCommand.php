<?php

namespace RCV\Core\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RCV\Core\Services\ModuleNameValidator;

class ModuleMakeCommand extends Command
{
    protected $signature = 'module:make {name*}';

    protected $description = 'Create one or more new modules';

    protected $moduleName;

    protected $moduleNameStudly;

    protected $moduleNameLower;

    protected $modulePath;

    public function handle(): int
    {
        $names = $this->argument('name');
        $normalizedNames = [];
        $registeredModules = $this->getRegisteredModuleNames();

        foreach ($names as $name) {
            if (! ModuleNameValidator::isValid($name)) {
                $this->error("Invalid module name [{$name}]. Use letters, numbers, hyphens, and underscores; start with a letter.");

                return self::FAILURE;
            }

            $moduleName = Str::studly($name);
            $normalizedName = strtolower($moduleName);
            if (isset($normalizedNames[$normalizedName])) {
                $this->error("Module [{$moduleName}] was requested more than once.");

                return self::FAILURE;
            }

            $normalizedNames[$normalizedName] = true;

            if (File::isDirectory(module_path($moduleName))) {
                $this->error("Module [{$moduleName}] already exists. Choose a different name.");

                return self::FAILURE;
            }

            if (in_array($normalizedName, array_map('strtolower', $registeredModules), true)) {
                $this->error("Module [{$moduleName}] is already registered.");

                return self::FAILURE;
            }
        }

        foreach ($names as $name) {
            $this->prepareModuleNames($name);
            $this->createModuleDirectories();
            $this->createModuleFiles();
            $this->createModuleState();
            $this->registerModuleInCoreConfig();

            $this->info("Module [{$this->moduleNameStudly}] created and registered successfully!");
            $this->line("To enable this module, run: php artisan module:enable {$this->moduleNameStudly}");
        }

        return 0;
    }

    /**
     * @return array<int, string>
     */
    protected function getRegisteredModuleNames(): array
    {
        $configPath = base_path('vendor/rcv/core/src/Config/config.php');
        if (! File::exists($configPath)) {
            return [];
        }

        $config = require $configPath;
        $modules = $config['modules'] ?? [];

        return is_array($modules)
            ? array_values(array_filter($modules, 'is_string'))
            : [];
    }

    protected function prepareModuleNames(string $name): void
    {
        $this->moduleName = $name;
        $this->moduleNameStudly = Str::studly($name);
        $this->moduleNameLower = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $this->modulePath = module_path($this->moduleNameStudly);
    }

    protected function createModuleDirectories(): void
    {
        $directories = [
            '',
            'src/Config',
            'src/Console',
            'src/Database/Migrations',
            'src/Database/Seeders',
            'src/Database/Factories',
            'src/Http/Controllers',
            'src/Http/Controllers/Api',
            'src/Http/Middleware',
            'src/Http/Requests',
            'src/Models',
            'src/Providers',
            'src/Repositories',
            'src/Services',
            'src/Resources/views',
            'src/Resources/assets/css',
            'src/Resources/assets/js',
            'src/Resources/assets/images',
            'src/Resources/lang',
            'src/Routes',
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->modulePath.($dir ? "/$dir" : '');
            File::ensureDirectoryExists($fullPath, 0755, true);
        }
    }

    protected function createModuleFiles(): void
    {
        $stubBase = __DIR__.'/../stubs';
        $targetBase = $this->modulePath;
        $srcBase = "$targetBase/src";

        $files = [
            ['stub' => 'composer.stub', 'target' => "$targetBase/composer.json"],
            ['stub' => 'provider.stub', 'target' => "$srcBase/Providers/{$this->moduleNameStudly}ServiceProvider.php"],
            ['stub' => 'config.stub', 'target' => "$srcBase/Config/config.php"],
            ['stub' => 'routes/web.stub', 'target' => "$srcBase/Routes/web.php"],
            ['stub' => 'routes/api.stub', 'target' => "$srcBase/Routes/api.php"],
            ['stub' => 'base-model.stub', 'target' => "$srcBase/Models/BaseModel.php", 'replace' => ['{{ class_name }}' => 'BaseModel']],
            ['stub' => 'repository.stub', 'target' => "$srcBase/Repositories/BaseRepository.php", 'replace' => ['{{ class_name }}' => 'BaseRepository']],
            ['stub' => 'service.stub', 'target' => "$srcBase/Services/BaseService.php", 'replace' => ['{{ class_name }}' => 'Base']],
            ['stub' => 'HomeController.stub', 'target' => "$srcBase/Http/Controllers/HomeController.php"],
            ['stub' => 'ApiHomeController.stub', 'target' => "$srcBase/Http/Controllers/Api/HomeController.php"],
            ['stub' => 'EventServiceProvider.stub', 'target' => "$srcBase/Providers/{$this->moduleNameStudly}EventServiceProvider.php"],
            ['stub' => 'database-seeder.stub', 'target' => "$srcBase/Database/Seeders/{$this->moduleNameStudly}DatabaseSeeder.php"],
        ];

        foreach ($files as $file) {
            $content = File::get("$stubBase/{$file['stub']}");
            $content = str_replace(['{{ module_name }}', '{{ module_name_lower }}'], [$this->moduleNameStudly, $this->moduleNameLower], $content);

            if (isset($file['replace'])) {
                foreach ($file['replace'] as $search => $replace) {
                    $content = str_replace($search, $replace, $content);
                }
            }

            File::put($file['target'], $content);
        }
    }

    protected function createModuleState(): void
    {
        $state = [
            'name' => $this->moduleNameStudly,
            'version' => '1.0.0',
            'dependencies' => [],
            'dependents' => [],
            'config' => [],
        ];

        File::put("{$this->modulePath}/module.json", json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function registerModuleInCoreConfig(): void
    {
        $configPath = base_path('vendor/rcv/core/src/Config/config.php');

        if (! File::exists($configPath)) {
            $this->error("Core config file not found: $configPath");

            return;
        }

        $config = require $configPath;
        $config['modules'] = $config['modules'] ?? [];

        if (! in_array($this->moduleNameStudly, $config['modules'])) {
            $config['modules'][] = $this->moduleNameStudly;
        }

        File::put($configPath, "<?php\n\nreturn ".var_export($config, true).";\n");
    }
}
