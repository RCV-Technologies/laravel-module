<?php

namespace RCV\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

class ModuleBackupManager
{
    protected $backupPath;
    protected $maxBackups;
    protected $retentionDays;

    public function __construct()
    {
        $this->backupPath = Config::get('marketplace.modules.backup.path', storage_path('app/Modules/backups'));
        $this->maxBackups = Config::get('marketplace.modules.backup.max_backups', 5);
        $this->retentionDays = Config::get('marketplace.modules.backup.retention_days', 30);
    }

    public function createBackup(string $moduleName): string
    {
        $modulePath = module_path($moduleName);
        $timestamp = Carbon::now()->format('Y_m_d_His');
        $backupDir = "{$this->backupPath}/{$moduleName}_{$timestamp}";

        try {
            if (!File::exists($this->backupPath)) {
                File::makeDirectory($this->backupPath, 0755, true);
            }

            File::copyDirectory($modulePath, $backupDir);

            // Create backup metadata
            $metadata = [
                'module' => $moduleName,
                'timestamp' => $timestamp,
                'created_at' => Carbon::now()->toIso8601String(),
                'size' => $this->getDirectorySize($backupDir),
                'version' => $this->getModuleVersion($moduleName)
            ];

            File::put("{$backupDir}/backup_metadata.json", json_encode($metadata, JSON_PRETTY_PRINT));

            // Rotate old backups
            $this->rotateBackups($moduleName);

            return $backupDir;
        } catch (\Exception $e) {
            Log::error("Failed to create backup for module {$moduleName}: " . $e->getMessage());
            throw $e;
        }
    }

    public function restoreBackup(string $backupPath): bool
    {
        try {
            if (!File::exists($backupPath)) {
                throw new \Exception("Backup directory not found: {$backupPath}");
            }

            $metadata = json_decode(File::get("{$backupPath}/backup_metadata.json"), true);
            $moduleName = $metadata['module'];
            $modulePath = module_path($moduleName);

            // Create a backup of current state before restore
            $this->createBackup($moduleName);

            // Restore the backup
            File::deleteDirectory($modulePath);
            File::copyDirectory($backupPath, $modulePath);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to restore backup {$backupPath}: " . $e->getMessage());
            throw $e;
        }
    }

    public function listBackups(string $moduleName = null): array
    {
        $backups = [];

        if (!File::exists($this->backupPath)) {
            return $backups;
        }

        $directories = File::directories($this->backupPath);
        foreach ($directories as $dir) {
            $metadataFile = "{$dir}/backup_metadata.json";
            if (File::exists($metadataFile)) {
                $metadata = json_decode(File::get($metadataFile), true);
                if (!$moduleName || $metadata['module'] === $moduleName) {
                    $backups[] = array_merge($metadata, [
                        'path' => $dir,
                        'age' => Carbon::parse($metadata['created_at'])->diffForHumans()
                    ]);
                }
            }
        }

        // Sort by creation date, newest first
        usort($backups, function($a, $b) {
            return Carbon::parse($b['created_at'])->timestamp - Carbon::parse($a['created_at'])->timestamp;
        });

        return $backups;
    }

    public function deleteBackup(string $backupPath): bool
    {
        try {
            if (!File::exists($backupPath)) {
                throw new \Exception("Backup directory not found: {$backupPath}");
            }

            File::deleteDirectory($backupPath);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete backup {$backupPath}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function rotateBackups(string $moduleName): void
    {
        $backups = $this->listBackups($moduleName);

        // Delete backups older than retention period
        $cutoffDate = Carbon::now()->subDays($this->retentionDays);
        foreach ($backups as $backup) {
            if (Carbon::parse($backup['created_at'])->lt($cutoffDate)) {
                $this->deleteBackup($backup['path']);
            }
        }

        // Keep only the most recent N backups
        $backups = $this->listBackups($moduleName);
        if (count($backups) > $this->maxBackups) {
            $backupsToDelete = array_slice($backups, $this->maxBackups);
            foreach ($backupsToDelete as $backup) {
                $this->deleteBackup($backup['path']);
            }
        }
    }

    protected function getDirectorySize(string $path): int
    {
        $size = 0;
        $files = File::allFiles($path);
        foreach ($files as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    protected function getModuleVersion(string $moduleName): string
    {
        $composerFile = module_path($moduleName, 'composer.json');
        if (File::exists($composerFile)) {
            $composer = json_decode(File::get($composerFile), true);
            return $composer['version'] ?? '1.0.0';
        }
        return '1.0.0';
    }

    public function cleanupOldBackups(): void
    {
        $this->info("Starting backup cleanup...");
        
        $backups = $this->listBackups();
        $cutoffDate = Carbon::now()->subDays($this->retentionDays);
        $deletedCount = 0;
        $freedSpace = 0;

        foreach ($backups as $backup) {
            if (Carbon::parse($backup['created_at'])->lt($cutoffDate)) {
                $freedSpace += $backup['size'];
                $this->deleteBackup($backup['path']);
                $deletedCount++;
            }
        }

        $this->info("Cleanup completed:");
        $this->info("- Deleted {$deletedCount} old backups");
        $this->info("- Freed " . $this->formatSize($freedSpace));
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
} 
