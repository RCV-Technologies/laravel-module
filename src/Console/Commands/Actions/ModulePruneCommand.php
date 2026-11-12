<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RCV\Core\Services\ModuleNameValidator;

class ModulePruneCommand extends Command
{
    protected $signature = 'module:prune {module}                                                      
                            {--model=* : Class names of the models to be pruned}
                            {--except=* : Class names of the models to be excluded from pruning}
                            {--path=* : Absolute path(s) to directories where models are located}
                            {--chunk=1000 : Number of models per chunk}
                            {--pretend : Show prunable count instead of deleting}';

    protected $description = 'Prune models by module that are no longer needed';

    public function handle()
    {
        // Retrieve the module argument
        $module = $this->argument('module');
        if (!$module) {
            $this->error('Please specify a module.');
            return 1;
        }

        if (!ModuleNameValidator::isValid($module)) {
            $this->error('Invalid module name. Use letters, numbers, hyphens, and underscores; start with a letter.');
            return 1;
        }

        // Retrieve options
        $paths = $this->option('path') ?: [];
        $only = $this->option('model');
        $except = $this->option('except');
        $pretend = $this->option('pretend');
        $chunkSize = (int) $this->option('chunk');

        $this->info("🔍 Scanning module: {$module}");

        $modulePath = module_path($module, 'src');
        if (!File::exists($modulePath)) {
            $this->warn("⚠️ Module path not found: {$modulePath}");
            return 1;
        }

        $searchPaths = $paths ?: [$modulePath];

        foreach ($searchPaths as $path) {
            if (!File::exists($path)) {
                $this->warn("❌ Path does not exist: $path");
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                $class = $this->getClassFromFile($file->getRealPath());
                if (!$class) continue;

                if (!empty($only) && !in_array(class_basename($class), $only)) continue;
                if (in_array(class_basename($class), $except)) continue;

                if (!method_exists($class, 'prunable')) continue;

                $model = new $class;

                if ($pretend) {
                    $count = $model->prunable()->count();
                    $this->line("🔸 [Pretend] {$class}: {$count} prunable");
                } else {
                    $this->line("🗑️ Pruning {$class}...");
                    $model->pruneAll($chunkSize);
                }
            }
        }

        // Optional: create stub helper class
        $helperStub = __DIR__ . '/stubs/prune-helper.stub';
        $helperPath = base_path('Modules/PruneHelper.php');
        if (File::exists($helperStub) && !File::exists($helperPath)) {
            File::put($helperPath, file_get_contents($helperStub));
            $this->info("📝 Generated helper: Modules/PruneHelper.php");
        }

        $this->info('✅ Pruning process complete.');
    }

    protected function getClassFromFile(string $path): ?string
    {
        $content = File::get($path);

        if (preg_match('/namespace\s+(.+);/', $content, $nsMatch) &&
            preg_match('/class\s+([^\s]+)/', $content, $classMatch)) {
            return $nsMatch[1] . '\\' . $classMatch[1];
        }

        return null;
    }
}
