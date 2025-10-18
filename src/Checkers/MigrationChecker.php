<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrationChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Migration Checker';
    }

    public function getDescription(): string
    {
        return 'Check migration consistency, indexes, and foreign keys';
    }

    public function check(): array
    {
        $this->info('');
        $this->info('Checking Migration Consistency');
        $this->info('==============================');

        $migrationPath = database_path('migrations');

        if (!File::exists($migrationPath)) {
            $this->warn("Migrations directory not found: {$migrationPath}");
            return $this->issues;
        }

        $migrationFiles = File::allFiles($migrationPath);

        foreach ($migrationFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkMigrationFile($file);
            }
        }

        // Check for missing indexes
        $this->checkMissingIndexes();

        // Check migration naming conventions
        $this->checkMigrationNaming($migrationFiles);

        return $this->issues;
    }

    protected function checkMigrationFile($file): void
    {
        $content = file_get_contents($file->getPathname());
        $fileName = $file->getFilename();

        // Extract table name from migration
        if (preg_match('/create_(\w+)_table/', $fileName, $matches)) {
            $tableName = $matches[1];
            $this->checkMigrationContent($content, $tableName, $file->getPathname());
        }
    }

    protected function checkMigrationContent(string $content, string $tableName, string $filePath): void
    {
        // Check for common migration issues

        // Check for nullable foreign keys without default
        if (preg_match_all('/\$table->foreignId\(([^\)]+)\)->nullable\(\)/', $content, $matches)) {
            foreach ($matches[1] as $columnDef) {
                if (!preg_match('/default\(/', $columnDef)) {
                    $this->addIssue('nullable_foreign_key_no_default', [
                        'file' => $filePath,
                        'table' => $tableName,
                        'column' => trim($columnDef, "'\""),
                        'message' => "Nullable foreign key should have a default value (usually null)"
                    ]);
                }
            }
        }

        // Check for string columns without length
        if (preg_match_all('/\$table->string\(([^,)]+)\)/', $content, $matches)) {
            foreach ($matches[1] as $columnName) {
                if (!preg_match('/\d+/', $columnName)) {
                    $this->addIssue('string_without_length', [
                        'file' => $filePath,
                        'table' => $tableName,
                        'column' => trim($columnName, "'\""),
                        'message' => "String column should specify a length (e.g., string('name', 255))"
                    ]);
                }
            }
        }

        // Check for boolean columns with default null
        if (preg_match('/\$table->boolean\([^)]+\)->nullable\(\)/', $content)) {
            $this->addIssue('boolean_nullable', [
                'file' => $filePath,
                'table' => $tableName,
                'message' => "Boolean columns should not be nullable. Use ->default(false) instead."
            ]);
        }

        // Check for missing timestamps
        if (!preg_match('/\$table->timestamps\(\)/', $content)) {
            $this->addIssue('missing_timestamps', [
                'file' => $filePath,
                'table' => $tableName,
                'message' => "Consider adding timestamps() for created_at and updated_at columns"
            ]);
        }
    }

    protected function checkMissingIndexes(): void
    {
        try {
            // Get all tables
            $tables = DB::select('SHOW TABLES');
            $databaseName = DB::getDatabaseName();

            foreach ($tables as $table) {
                $tableName = $table->{'Tables_in_' . $databaseName};

                // Skip Laravel system tables
                if (in_array($tableName, ['migrations', 'failed_jobs', 'cache', 'sessions'])) {
                    continue;
                }

                // Check for foreign keys without indexes
                if (DB::getDriverName() === 'mysql') {
                    $foreignKeys = DB::select("
                        SELECT COLUMN_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                    ", [$databaseName, $tableName]);

                    foreach ($foreignKeys as $fk) {
                        $columnName = $fk->COLUMN_NAME;

                        // Check if there's an index on this column
                        $hasIndex = DB::select("
                            SELECT 1
                            FROM information_schema.STATISTICS
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                        ", [$databaseName, $tableName, $columnName]);

                        if (empty($hasIndex)) {
                            $this->addIssue('missing_foreign_key_index', [
                                'table' => $tableName,
                                'column' => $columnName,
                                'message' => "Foreign key column '{$columnName}' in table '{$tableName}' should have an index for performance"
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("Could not check database indexes: " . $e->getMessage());
        }
    }

    protected function checkMigrationNaming($migrationFiles): void
    {
        foreach ($migrationFiles as $file) {
            $fileName = $file->getFilename();

            // Check naming convention: YYYY_MM_DD_HHMMSS_description.php
            if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z][a-z0-9_]*\.php$/', $fileName)) {
                $this->addIssue('invalid_migration_name', [
                    'file' => $file->getPathname(),
                    'filename' => $fileName,
                    'message' => "Migration filename should follow convention: YYYY_MM_DD_HHMMSS_description.php"
                ]);
            }

            // Check for descriptive names
            $description = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $fileName);
            $description = preg_replace('/\.php$/', '', $description);

            if (strlen($description) < 5) {
                $this->addIssue('poor_migration_description', [
                    'file' => $file->getPathname(),
                    'description' => $description,
                    'message' => "Migration description '{$description}' is too short. Use descriptive names like 'create_users_table' or 'add_email_to_users'"
                ]);
            }
        }
    }
}