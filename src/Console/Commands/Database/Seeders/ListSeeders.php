<?php

namespace Rcv\Core\Console\Commands\Database\Seeders;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListSeeders extends Command
{
    protected $signature = 'module:seeder-list';
    protected $description = 'List all seeder classes in the main and module seeders directories';

    public function handle(): void
    {
        $paths = [
            database_path('seeders'), 
            base_path('Modules'),    
        ];

        $seeders = [];

        // 1. Collect base seeders
        $baseSeederPath = database_path('seeders');
        if (File::exists($baseSeederPath)) {
            $seeders = array_merge($seeders, $this->getSeederFiles($baseSeederPath));
        }

        // 2. Scan modules folder for seeders
        $modulesPath = base_path('Modules');
        if (File::exists($modulesPath)) {
            $moduleFolders = File::directories($modulesPath);

            foreach ($moduleFolders as $moduleDir) {
                // Possible seeder paths inside modules
                $possiblePaths = [
                    $moduleDir . '/database/seeders',
                    $moduleDir . '/src/Database/Seeders',
                ];

                foreach ($possiblePaths as $path) {
                    if (File::exists($path)) {
                        $seeders = array_merge($seeders, $this->getSeederFiles($path, $moduleDir));
                    }
                }
            }
        }

        if (empty($seeders)) {
            $this->info('No seeders found in base or modules.');
            return;
        }

        $this->table(['Seeder Files'], array_map(fn($f) => [$f], $seeders));
    }

    /**
     * Helper function to get seeder files with relative path.
     *
     * @param string $path
     * @param string|null $moduleDir Optional base module directory to trim from path
     * @return array
     */
protected function getSeederFiles(string $path, ?string $moduleDir = null): array
    {
        $files = File::allFiles($path);
        $seeders = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                if ($moduleDir) {
                    // Show relative to module base folder for clarity
                    $seeders[] = str_replace($moduleDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                } else {
                    // Relative to database/seeders base folder
                    $seeders[] = $file->getRelativePathname();
                }
            }
        }

        return $seeders;
    }
}
