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
        return 'Check migration syntax, consistency, and database schema best practices';
    }

    protected function getRuleName(): ?string
    {
        return 'migration_syntax';
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
        $validationMode = $this->config['migration_validation_mode'] ?? 'migration_files';

        $this->info("Migration validation mode: {$validationMode}");

        // Always check migration files for syntax and best practices
        foreach ($migrationFiles as $file) {
            if ($file->getExtension() === 'php') {
                // Check if this migration file should be excluded
                if ($this->shouldSkipFile($file->getPathname())) {
                    $this->info("Skipping excluded migration: {$file->getPathname()}");
                    continue;
                }
                $this->checkMigrationFile($file);
            }
        }

        // Check database schema if requested
        if (in_array($validationMode, ['database_schema', 'both'])) {
            $this->checkDatabaseSchema();
        }

        // Check migration naming conventions
        $this->checkMigrationNaming($migrationFiles);

        return $this->issues;
    }

    protected function checkMigrationFile($file): void
    {
        $content = file_get_contents($file->getPathname());
        $fileName = $file->getFilename();

        // Check for PHP syntax errors
        $this->checkMigrationSyntax($file->getPathname());

        // Extract table name from migration
        if (preg_match('/create_(\w+)_table/', $fileName, $matches)) {
            $tableName = $matches[1];
            $this->checkMigrationContent($content, $tableName, $file->getPathname());
        }
    }

    protected function checkMigrationSyntax(string $filePath): void
    {
        // Use PHP's built-in syntax checker
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($filePath) . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            // Syntax error detected
            $errorMessage = implode("\n", $output);
            $this->addIssue('migration', 'syntax_error', [
                'file' => $filePath,
                'message' => "PHP syntax error in migration file: " . $errorMessage
            ]);
        }
    }

    protected function checkMigrationContent(string $content, string $tableName, string $filePath): void
    {
        // Check for common migration issues

        // Check for nullable foreign keys without default
        if (preg_match_all('/\$table->foreignId\(([^\)]+)\)->nullable\(\)/', $content, $matches)) {
            foreach ($matches[1] as $columnDef) {
                if (!preg_match('/default\(/', $columnDef)) {
                    $this->addIssue('migration', 'nullable_foreign_key_no_default', [
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
                    $this->addIssue('migration', 'string_without_length', [
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
            $this->addIssue('migration', 'boolean_nullable', [
                'file' => $filePath,
                'table' => $tableName,
                'message' => "Boolean columns should not be nullable. Use ->default(false) instead."
            ]);
        }

        // Check for malformed method calls (e.g., $table->string('key'(255)) instead of $table->string('key', 255))
        if (preg_match_all('/\$table->(\w+)\(\s*[^,)]+\([^)]*\)\s*\)/', $content, $matches)) {
            foreach ($matches[0] as $malformedCall) {
                $this->addIssue('migration', 'malformed_method_call', [
                    'file' => $filePath,
                    'table' => $tableName,
                    'malformed_call' => $malformedCall,
                    'message' => "Malformed method call detected: '{$malformedCall}'. Check for missing commas between arguments."
                ]);
            }
        }

        // Check for missing timestamps
        if (!preg_match('/\$table->timestamps\(\)/', $content)) {
            $this->addIssue('migration', 'missing_timestamps', [
                'file' => $filePath,
                'table' => $tableName,
                'message' => "Consider adding timestamps() for created_at and updated_at columns"
            ]);
        }

        // Check for foreign keys without indexes in the same migration
        $this->checkForeignKeysWithoutIndexes($content, $tableName, $filePath);
    }

    protected function checkForeignKeysWithoutIndexes(string $content, string $tableName, string $filePath): void
    {
        // Find all foreign key definitions in this migration
        if (preg_match_all('/\$table->foreignId\(([^)]+)\)/', $content, $foreignKeyMatches)) {
            $foreignKeyColumns = [];
            foreach ($foreignKeyMatches[1] as $columnDef) {
                // Extract column name from the definition
                if (preg_match('/[\'"]([^\'"]+)[\'"]/', $columnDef, $columnMatch)) {
                    $foreignKeyColumns[] = $columnMatch[1];
                }
            }

            // Check if each foreign key column has an index defined in the same migration
            foreach ($foreignKeyColumns as $fkColumn) {
                // Look for index creation for this column
                $indexPattern = '/\$table->index\(\s*[\'"](?:' . preg_quote($fkColumn, '/') . ')[\'"]\s*(?:,|\))/';
                if (!preg_match($indexPattern, $content)) {
                    $this->addIssue('migration', 'foreign_key_without_index', [
                        'file' => $filePath,
                        'table' => $tableName,
                        'column' => $fkColumn,
                        'message' => "Foreign key column '{$fkColumn}' should have an index for performance. Add: \$table->index('{$fkColumn}');"
                    ]);
                }
            }
        }
    }

    protected function checkMigrationNaming($migrationFiles): void
    {
        foreach ($migrationFiles as $file) {
            $fileName = $file->getFilename();

            // Check naming convention: YYYY_MM_DD_HHMMSS_description.php
            if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z][a-z0-9_]*\.php$/', $fileName)) {
                $this->addIssue('migration', 'invalid_migration_name', [
                    'file' => $file->getPathname(),
                    'filename' => $fileName,
                    'message' => "Migration filename should follow convention: YYYY_MM_DD_HHMMSS_description.php"
                ]);
            }

            // Check for descriptive names
            $description = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $fileName);
            $description = preg_replace('/\.php$/', '', $description);

            if (strlen($description) < 5) {
                $this->addIssue('migration', 'poor_migration_description', [
                    'file' => $file->getPathname(),
                    'description' => $description,
                    'message' => "Migration description '{$description}' is too short. Use descriptive names like 'create_users_table' or 'add_email_to_users'"
                ]);
            }
        }
    }

    protected function checkDatabaseSchema(): void
    {
        $this->info('Checking current database schema for missing indexes...');

        try {
            $driver = DB::getDriverName();

            // Get all tables based on database driver
            if ($driver === 'sqlite') {
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tableNames = array_column($tables, 'name');
            } elseif ($driver === 'mysql') {
                $tables = DB::select('SHOW TABLES');
                $databaseName = DB::getDatabaseName();
                $tableNames = [];
                foreach ($tables as $table) {
                    $tableNames[] = $table->{'Tables_in_' . $databaseName};
                }
            } elseif ($driver === 'pgsql') {
                $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                $tableNames = array_column($tables, 'tablename');
            } else {
                $this->warn('Database driver not supported for schema checking');
                return;
            }

            foreach ($tableNames as $tableName) {
                // Skip Laravel system tables
                if (in_array($tableName, ['migrations', 'failed_jobs', 'cache', 'sessions', 'password_resets'])) {
                    continue;
                }

                // Check for foreign keys without indexes based on database driver
                if ($driver === 'mysql') {
                    $foreignKeys = DB::select("
                        SELECT COLUMN_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                    ", [DB::getDatabaseName(), $tableName]);

                    foreach ($foreignKeys as $fk) {
                        $columnName = $fk->COLUMN_NAME;

                        // Check if there's an index on this column
                        $hasIndex = DB::select("
                            SELECT 1
                            FROM information_schema.STATISTICS
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                        ", [DB::getDatabaseName(), $tableName, $columnName]);

                        if (empty($hasIndex)) {
                            $this->addIssue('migration', 'database_missing_foreign_key_index', [
                                'table' => $tableName,
                                'column' => $columnName,
                                'message' => "Foreign key column '{$columnName}' in table '{$tableName}' should have an index for performance (found in current database schema)"
                            ]);
                        }
                    }
                } elseif ($driver === 'sqlite') {
                    // SQLite foreign key detection is complex, skip for now
                    $this->warn('SQLite schema validation for foreign key indexes is not yet implemented');
                    continue;
                } elseif ($driver === 'pgsql') {
                    // PostgreSQL foreign key checking could be added here
                    $this->warn('PostgreSQL schema validation for foreign key indexes is not yet implemented');
                    continue;
                }
            }
        } catch (\Exception $e) {
            $this->warn("Could not check database schema: " . $e->getMessage());
        }
    }
}
