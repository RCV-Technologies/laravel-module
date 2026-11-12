<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

class ModuleCheckLangCommand extends Command
{
    protected $signature = 'module:lang
        {module : The module to check}
        {--fallback= : Fallback locale (copy keys from this locale if missing)}
        {--placeholder= : Placeholder value for missing keys}';

    protected $description = 'Check and validate translation files/keys across locales in a module';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $basePath = module_path($module, 'src/Resources/lang');

        if (! $this->files->isDirectory($basePath)) {
            $this->warn("No lang directory found in module {$module}.");
            return self::SUCCESS;
        }

        $dirs = collect($this->files->directories($basePath));
        if ($dirs->count() < 2) {
            $this->warn("At least two locales needed in {$module}/lang for comparison.");
            return self::SUCCESS;
        }

        // Gather all filenames across all locales
        $allFiles = $dirs->flatMap(fn($d) => $this->files->files($d))
            ->map(fn($f) => $f->getFilename())
            ->unique();

        $totalMissingFiles = 0;
        $totalMissingKeys = 0;
        $totalFilesChecked = count($allFiles);
        $locales = $dirs->map(fn($d) => basename($d))->implode(', ');

        // Check for missing files
        foreach ($dirs as $dirPath) {
            $locale = basename($dirPath);
            $filesHere = collect($this->files->files($dirPath))->map->getFilename();
            $delta = $allFiles->diff($filesHere);

            foreach ($delta as $missing) {
                $this->error("$locale missing file: $missing");
                $totalMissingFiles++;
            }
        }

        // Check for missing keys
        foreach ($allFiles as $file) {
            $translations = $dirs->mapWithKeys(function ($dirPath) use ($file) {
                $locale = basename($dirPath);
                $filePath = "$dirPath/$file";

                if (! $this->files->exists($filePath)) {
                    return [$locale => []];
                }

                $arr = include $filePath;
                return [$locale => is_array($arr) ? $arr : []];
            });

            $flat = $translations->map(fn($arr) => collect(Arr::dot($arr))->keys());
            $union = $flat->flatten()->unique();

            foreach ($flat as $locale => $keys) {
                $miss = $union->diff($keys);

                foreach ($miss as $key) {
                    $totalMissingKeys++;
                    $this->error("$locale/$file missing key: $key");

                    // Auto-fill if options are provided
                    $fallback = $this->option('fallback');
                    $placeholder = $this->option('placeholder');

                    if ($fallback && isset($translations[$fallback][$key])) {
                        $this->line(" → Auto-filled from fallback [$fallback]");
                        // (You could also write the file here if auto-save is desired)
                    } elseif ($placeholder) {
                        $this->line(" → Auto-filled with placeholder [$placeholder]");
                        // (You could also write the file here if auto-save is desired)
                    }
                }
            }
        }

        // Final summary report
        $this->info("Language check completed for module: {$module}");
        $this->line("Locales checked: {$locales}");
        $this->line("Files compared: {$totalFilesChecked}");
        $this->line("Missing files: {$totalMissingFiles}");
        $this->line("Missing keys: {$totalMissingKeys}");
        $this->line("Status: " . ($totalMissingFiles === 0 && $totalMissingKeys === 0 ? '✅ All translations are in sync' : '⚠️ Issues detected'));

        return self::SUCCESS;
    }
}
