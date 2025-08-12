<?php

namespace Rcv\Core\Console\Commands\Actions;


use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ModuleCheckLangCommand extends Command
{
    protected $signature = 'module:lang {module}';
    protected $description = 'Check missing translation files and keys in a module';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $module = $this->argument('module');
        $basePath = base_path("Modules/$module/Resources/lang");
        if (! $this->files->isDirectory($basePath)) {
            return $this->warn("No lang directory found in module $module.");
        }

        $dirs = collect($this->files->directories($basePath));
        if ($dirs->count() < 2) {
            return $this->warn("At least two locales needed in $module/lang.");
        }

        // Gather all filenames
        $allFiles = $dirs->flatMap(fn($d) => $this->files->files($d))
                         ->map(fn($f) => $f->getFilename())
                         ->unique();

        // Check for missing files
        foreach ($dirs as $dirPath) {
            $locale = basename($dirPath);
            $filesHere = collect($this->files->files($dirPath))->map->getFilename();
            $delta = $allFiles->diff($filesHere);
            foreach ($delta as $missing) {
                $this->error("$locale missing file: $missing");
            }
        }

        // Check for missing keys
        foreach ($allFiles as $file) {
            $translations = $dirs->mapWithKeys(fn($dirPath) => [
                basename($dirPath) => include("$dirPath/$file")
            ]);

            $flat = $translations->map(fn($arr) => collect(Arr::dot($arr))->keys());
            $union = $flat->flatten()->unique();

            foreach ($flat as $locale => $keys) {
                $miss = $union->diff($keys);
                foreach ($miss as $key) {
                    $this->error("$locale/$file missing key: $key");
                }
            }
        }
    }
}
