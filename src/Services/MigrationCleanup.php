<?php

namespace NDEstates\LaravelModelSchemaChecker\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MigrationCleanup
{
    protected string $migrationsPath;
    protected string $backupPath;
    protected array $protectedMigrations = [];

    public function __construct()
    {
        $this->migrationsPath = database_path('migrations');
        $this->backupPath = storage_path('app/migration-backups');
        $this->ensureBackupDirectoryExists();
        $this->loadProtectedMigrations();
    }

    /**
     * Clean up old migration files with backup
     */
    public function cleanupMigrationFiles(array $options = []): array
    {
        $results = [
            'files_backed_up' => 0,
            'files_deleted' => 0,
            'total_size_saved' => 0,
            'errors' => [],
            'warnings' => []
        ];

        if (!File::exists($this->migrationsPath)) {
            $results['warnings'][] = 'Migrations directory does not exist';
            return $results;
        }

        $migrationFiles = $this->getMigrationFiles();

        if (empty($migrationFiles)) {
            $results['warnings'][] = 'No migration files found';
            return $results;
        }

        // Filter files to delete based on criteria
        $filesToDelete = $this->filterFilesToDelete($migrationFiles, $options);

        if (empty($filesToDelete)) {
            $results['warnings'][] = 'No migration files match cleanup criteria';
            return $results;
        }

        // Create backup before deletion
        if (!($options['no_backup'] ?? false)) {
            $backupResult = $this->createBackup($filesToDelete);
            $results['files_backed_up'] = $backupResult['files_backed_up'];
            $results['errors'] = array_merge($results['errors'], $backupResult['errors']);
        }

        // Delete files (if not in dry-run mode)
        if (!($options['dry_run'] ?? false)) {
            $deleteResult = $this->deleteMigrationFiles($filesToDelete);
            $results['files_deleted'] = $deleteResult['files_deleted'];
            $results['total_size_saved'] = $deleteResult['total_size_saved'];
            $results['errors'] = array_merge($results['errors'], $deleteResult['errors']);
        } else {
            $results['files_deleted'] = count($filesToDelete);
            $results['total_size_saved'] = $this->calculateTotalSize($filesToDelete);
            $results['warnings'][] = 'Dry run mode - no files were actually deleted';
        }

        return $results;
    }

    /**
     * Get all migration files
     */
    protected function getMigrationFiles(): array
    {
        $files = File::files($this->migrationsPath);

        $migrationFiles = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $migrationFiles[] = $file;
            }
        }

        return $migrationFiles;
    }

    /**
     * Filter files that should be deleted based on criteria
     */
    protected function filterFilesToDelete(array $migrationFiles, array $options = []): array
    {
        $filesToDelete = [];

        foreach ($migrationFiles as $file) {
            if ($this->shouldDeleteFile($file, $options)) {
                $filesToDelete[] = $file;
            }
        }

        return $filesToDelete;
    }

    /**
     * Determine if a migration file should be deleted
     */
    protected function shouldDeleteFile($file, array $options = []): bool
    {
        $filename = $file->getFilename();

        // Never delete protected migrations
        if (in_array($filename, $this->protectedMigrations)) {
            return false;
        }

        // Check age-based criteria
        if (isset($options['older_than_days'])) {
            $fileAge = Carbon::now()->diffInDays(Carbon::createFromTimestamp($file->getMTime()));
            if ($fileAge <= $options['older_than_days']) {
                return false;
            }
        }

        // Check file size criteria
        if (isset($options['larger_than_kb'])) {
            $fileSizeKb = $file->getSize() / 1024;
            if ($fileSizeKb <= $options['larger_than_kb']) {
                return false;
            }
        }

        // Check pattern-based criteria
        if (isset($options['match_pattern'])) {
            if (!preg_match($options['match_pattern'], $filename)) {
                return false;
            }
        }

        // Check if migration has been run
        if ($options['only_ran'] ?? false) {
            if (!$this->migrationHasBeenRun($filename)) {
                return false;
            }
        }

        // Check if migration exists in database but not in filesystem
        if ($options['orphaned_only'] ?? false) {
            if (!$this->isOrphanedMigration($filename)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if migration has been run in the database
     */
    protected function migrationHasBeenRun(string $filename): bool
    {
        // Extract migration name from filename
        // Format: YYYY_MM_DD_HHMMSS[_microseconds]_migration_name.php
        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6,8}_(.+)\.php$/', $filename, $matches)) {
            $migrationName = $matches[1];

            $count = DB::table('migrations')
                ->where('migration', $migrationName)
                ->count();

            return $count > 0;
        }

        return false;
    }

    /**
     * Check if migration is orphaned (exists in DB but not in filesystem)
     */
    protected function isOrphanedMigration(string $filename): bool
    {
        // This would check if the migration exists in the migrations table
        // but the file doesn't exist - but since we're iterating files,
        // this doesn't make sense in this context
        return false;
    }

    /**
     * Create backup of migration files
     */
    protected function createBackup(array $files): array
    {
        $results = [
            'files_backed_up' => 0,
            'errors' => []
        ];

        $backupDir = $this->backupPath . '/' . Carbon::now()->format('Y-m-d_H-i-s') . '_migration_cleanup';

        try {
            File::makeDirectory($backupDir, 0755, true);

            foreach ($files as $file) {
                $sourcePath = $file->getPathname();
                $destPath = $backupDir . '/' . $file->getFilename();

                if (File::copy($sourcePath, $destPath)) {
                    $results['files_backed_up']++;
                } else {
                    $results['errors'][] = "Failed to backup file: {$file->getFilename()}";
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = "Failed to create backup directory: {$e->getMessage()}";
        }

        return $results;
    }

    /**
     * Delete migration files
     */
    protected function deleteMigrationFiles(array $files): array
    {
        $results = [
            'files_deleted' => 0,
            'total_size_saved' => 0,
            'errors' => []
        ];

        foreach ($files as $file) {
            try {
                $fileSize = $file->getSize();
                if (File::delete($file->getPathname())) {
                    $results['files_deleted']++;
                    $results['total_size_saved'] += $fileSize;
                } else {
                    $results['errors'][] = "Failed to delete file: {$file->getFilename()}";
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Error deleting {$file->getFilename()}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Calculate total size of files
     */
    protected function calculateTotalSize(array $files): int
    {
        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += $file->getSize();
        }
        return $totalSize;
    }

    /**
     * Load protected migrations that should never be deleted
     */
    protected function loadProtectedMigrations(): void
    {
        // Always protect the migrations table creation
        $this->protectedMigrations = [
            '2014_10_12_000000_create_users_table.php',
            '2014_10_12_100000_create_password_resets_table.php',
            '2019_08_19_000000_create_failed_jobs_table.php',
            '2019_12_14_000001_create_personal_access_tokens_table.php',
        ];

        // Add any custom protected migrations from config
        $configProtected = config('model-schema-checker.protected_migrations', []);
        $this->protectedMigrations = array_merge($this->protectedMigrations, $configProtected);
    }

    /**
     * Get cleanup preview
     */
    public function getCleanupPreview(array $options = []): array
    {
        $migrationFiles = $this->getMigrationFiles();
        $filesToDelete = $this->filterFilesToDelete($migrationFiles, $options);

        $preview = [
            'total_migration_files' => count($migrationFiles),
            'files_to_delete' => count($filesToDelete),
            'total_size_to_save' => $this->calculateTotalSize($filesToDelete),
            'files' => []
        ];

        foreach ($filesToDelete as $file) {
            $preview['files'][] = [
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified' => Carbon::createFromTimestamp($file->getMTime())->toDateTimeString(),
                'path' => $file->getPathname()
            ];
        }

        return $preview;
    }

    /**
     * Clean up old backup files
     */
    public function cleanupOldBackups(int $daysOld = 30): int
    {
        if (!File::exists($this->backupPath)) {
            return 0;
        }

        $backupDirs = File::directories($this->backupPath);
        $deletedCount = 0;

        foreach ($backupDirs as $dir) {
            $dirName = basename($dir);

            // Extract timestamp from directory name
            if (preg_match('/(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})_migration_cleanup/', $dirName, $matches)) {
                $backupDate = Carbon::createFromFormat('Y-m-d_H-i-s', $matches[1]);

                if ($backupDate->addDays($daysOld)->isPast()) {
                    if (File::deleteDirectory($dir)) {
                        $deletedCount++;
                    }
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Restore migrations from backup
     */
    public function restoreFromBackup(string $backupDir): array
    {
        $results = [
            'files_restored' => 0,
            'errors' => []
        ];

        $backupPath = $this->backupPath . '/' . $backupDir;

        if (!File::exists($backupPath)) {
            $results['errors'][] = "Backup directory does not exist: {$backupDir}";
            return $results;
        }

        $backupFiles = File::files($backupPath);

        foreach ($backupFiles as $file) {
            if ($file->getExtension() === 'php') {
                $destPath = $this->migrationsPath . '/' . $file->getFilename();

                // Check if file already exists
                if (File::exists($destPath)) {
                    $results['errors'][] = "Migration file already exists: {$file->getFilename()}";
                    continue;
                }

                if (File::copy($file->getPathname(), $destPath)) {
                    $results['files_restored']++;
                } else {
                    $results['errors'][] = "Failed to restore file: {$file->getFilename()}";
                }
            }
        }

        return $results;
    }

    /**
     * Get list of available backups
     */
    public function getAvailableBackups(): array
    {
        if (!File::exists($this->backupPath)) {
            return [];
        }

        $backupDirs = File::directories($this->backupPath);
        $backups = [];

        foreach ($backupDirs as $dir) {
            $dirName = basename($dir);
            $fileCount = count(File::files($dir));

            // Extract timestamp from directory name
            if (preg_match('/(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})_migration_cleanup/', $dirName, $matches)) {
                $backupDate = Carbon::createFromFormat('Y-m-d_H-i-s', $matches[1]);

                $backups[] = [
                    'directory' => $dirName,
                    'date' => $backupDate->toDateTimeString(),
                    'file_count' => $fileCount,
                    'path' => $dir
                ];
            }
        }

        // Sort by date descending
        usort($backups, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $backups;
    }

    /**
     * Ensure backup directory exists
     */
    protected function ensureBackupDirectoryExists(): void
    {
        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    /**
     * Get migrations path
     */
    public function getMigrationsPath(): string
    {
        return $this->migrationsPath;
    }

    /**
     * Set migrations path
     */
    public function setMigrationsPath(string $path): self
    {
        $this->migrationsPath = $path;
        return $this;
    }

    /**
     * Get backup path
     */
    public function getBackupPath(): string
    {
        return $this->backupPath;
    }

    /**
     * Set backup path
     */
    public function setBackupPath(string $path): self
    {
        $this->backupPath = $path;
        $this->ensureBackupDirectoryExists();
        return $this;
    }

    /**
     * Get protected migrations
     */
    public function getProtectedMigrations(): array
    {
        return $this->protectedMigrations;
    }

    /**
     * Set protected migrations
     */
    public function setProtectedMigrations(array $migrations): self
    {
        $this->protectedMigrations = $migrations;
        return $this;
    }
}
