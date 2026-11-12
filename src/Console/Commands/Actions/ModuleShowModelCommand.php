<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ModuleShowModelCommand extends Command
{
    protected $signature = 'module:model-show {module?} {--json}';
    protected $description = 'Display information about all Eloquent models in a specific module or across all modules';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $jsonOutput = $this->option('json');

        $modules = $moduleName
            ? [$this->getModulePath($moduleName)]
            : $this->getAllModulePaths();

        $models = $this->getModelsFromPaths($modules);

        if (empty($models)) {
            $this->info('No models found.');
            return 0;
        }

        $this->displayModelsInfo($models, $jsonOutput);

        return 0;
    }

    protected function getModulePath(string $moduleName): string
    {
        return module_path($moduleName, 'src/Models');
    }

    protected function getAllModulePaths(): array
    {
        return collect(glob(base_path('Modules/*/src/Models')))
            ->filter(fn($path) => is_dir($path))
            ->toArray();
    }

    protected function getModelsFromPaths(array $paths): array
    {
        $models = [];

        foreach ($paths as $path) {
            foreach (glob("{$path}/*.php") as $file) {
                $modelClass = $this->getModelClassFromFile($file);
                if ($modelClass && class_exists($modelClass)) {
                    $models[] = $modelClass;
                }
            }
        }

        return $models;
    }

    protected function getModelClassFromFile(string $file): ?string
    {
        $namespace = $this->getNamespaceFromFile($file);
        $className = basename($file, '.php');

        return $namespace ? "{$namespace}\\{$className}" : null;
    }

    protected function getNamespaceFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);
        if (preg_match('/namespace\s+(.+?);/', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function displayModelsInfo(array $models, bool $jsonOutput): void
    {
        $this->components->twoColumnDetail('Model', 'Module');

        foreach ($models as $modelClass) {
            $model = new $modelClass;
            $moduleName = $this->getModuleNameFromModel($modelClass);

            $this->components->twoColumnDetail($modelClass, $moduleName);

            if ($jsonOutput) {
                $this->line(json_encode([
                    'model' => $modelClass,
                    'module' => $moduleName,
                    'table' => $model->getTable(),
                    'primary_key' => $model->getKeyName(),
                    'connection' => $model->getConnectionName(),
                ], JSON_PRETTY_PRINT));
            }
        }
    }

    protected function getModuleNameFromModel(string $modelClass): string
    {
        $namespaceParts = explode('\\', $modelClass);
        return $namespaceParts[1] ?? 'Unknown';
    }

    protected function getOptions()
    {
        return [
            ['json', 'j', InputOption::VALUE_NONE, 'Output information in JSON format'],
        ];
    }
}
