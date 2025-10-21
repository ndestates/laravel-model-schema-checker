<?php

namespace NDEstates\LaravelModelSchemaChecker\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class DataImporter
{
    protected string $importPath;
    protected array $excludedTables = [
        'migrations',
        'failed_jobs',
        'password_resets',
        'personal_access_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ];

    public function __construct()
    {
        $this->importPath = storage_path('app/imports');
        $this->ensureImportDirectoryExists();
    }

    /**
     * Import database data from SQL file
     */
    public function importDatabaseData(string $filePath, array $options = []): array
    {
        if (!File::exists($filePath)) {
            throw new \InvalidArgumentException("Import file does not exist: {$filePath}");
        }

        // Decompress if it's a compressed file
        if (str_ends_with($filePath, '.gz')) {
            $filePath = $this->decompressFile($filePath);
        }

        $sqlContent = File::get($filePath);
        $statements = $this->parseSqlStatements($sqlContent);

        $results = [
            'success' => true,
            'tables_imported' => 0,
            'rows_imported' => 0,
            'errors' => [],
            'warnings' => []
        ];

        DB::beginTransaction();

        try {
            foreach ($statements as $statement) {
                $result = $this->executeStatement($statement, $options);

                if ($result['type'] === 'insert') {
                    $results['rows_imported'] += $result['affected_rows'];
                } elseif ($result['type'] === 'table_created') {
                    $results['tables_imported']++;
                }

                if (!empty($result['warnings'])) {
                    $results['warnings'] = array_merge($results['warnings'], $result['warnings']);
                }
            }

            if (!($options['dry_run'] ?? false)) {
                DB::commit();
            } else {
                DB::rollBack();
                $results['warnings'][] = 'Dry run mode - no changes were made to the database';
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();

            // Clean up decompressed file if it was created
            if (isset($decompressedFile) && File::exists($decompressedFile)) {
                File::delete($decompressedFile);
            }
        }

        // Clean up decompressed file if it was created
        if (isset($decompressedFile) && File::exists($decompressedFile)) {
            File::delete($decompressedFile);
        }

        return $results;
    }

    /**
     * Import data from compressed SQL file
     */
    public function importDatabaseDataFromCompressedFile(string $compressedFilePath, array $options = []): array
    {
        if (!File::exists($compressedFilePath)) {
            throw new \InvalidArgumentException("Compressed import file does not exist: {$compressedFilePath}");
        }

        $decompressedFile = $this->decompressFile($compressedFilePath);
        $result = $this->importDatabaseData($decompressedFile, $options);

        // Clean up decompressed file
        File::delete($decompressedFile);

        return $result;
    }

    /**
     * Parse SQL statements from content
     */
    protected function parseSqlStatements(string $sqlContent): array
    {
        // Remove comments
        $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
        $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);

        // Split by semicolons, but be careful with semicolons inside strings
        $statements = [];
        $currentStatement = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($sqlContent); $i++) {
            $char = $sqlContent[$i];

            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && $sqlContent[$i - 1] !== '\\') {
                $inString = false;
                $stringChar = '';
            }

            if (!$inString && $char === ';') {
                $statement = trim($currentStatement);
                if (!empty($statement)) {
                    $statements[] = $statement;
                }
                $currentStatement = '';
            } else {
                $currentStatement .= $char;
            }
        }

        // Add any remaining statement
        $statement = trim($currentStatement);
        if (!empty($statement)) {
            $statements[] = $statement;
        }

        return array_filter($statements);
    }

    /**
     * Execute a single SQL statement
     */
    protected function executeStatement(string $statement, array $options = []): array
    {
        $result = [
            'type' => 'unknown',
            'affected_rows' => 0,
            'warnings' => []
        ];

        // Skip certain statements
        if ($this->shouldSkipStatement($statement)) {
            return $result;
        }

        // Handle different statement types
        if (stripos($statement, 'INSERT INTO') === 0) {
            $result['type'] = 'insert';
            $result['affected_rows'] = $this->executeInsertStatement($statement, $options);
        } elseif (stripos($statement, 'CREATE TABLE') === 0) {
            $result['type'] = 'table_created';
            $this->executeCreateTableStatement($statement, $options);
        } elseif (stripos($statement, 'SET') === 0) {
            // Handle SET statements (like SET FOREIGN_KEY_CHECKS)
            $this->executeSetStatement($statement);
        } elseif (
            stripos($statement, 'START TRANSACTION') === 0 ||
                  stripos($statement, 'COMMIT') === 0 ||
                  stripos($statement, 'BEGIN') === 0
        ) {
            // Transaction control statements - skip as we handle transactions ourselves
        } else {
            // Execute other statements
            DB::statement($statement);
        }

        return $result;
    }

    /**
     * Check if statement should be skipped
     */
    protected function shouldSkipStatement(string $statement): bool
    {
        $skipPatterns = [
            '/^SET FOREIGN_KEY_CHECKS/i',
            '/^SET SQL_MODE/i',
            '/^SET AUTOCOMMIT/i',
            '/^START TRANSACTION/i',
            '/^COMMIT/i',
            '/^BEGIN/i',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $statement)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute INSERT statement
     */
    protected function executeInsertStatement(string $statement, array $options): int
    {
        // Extract table name
        if (preg_match('/INSERT INTO `?(\w+)`?\s*\(/i', $statement, $matches)) {
            $tableName = $matches[1];

            // Check if table should be skipped
            if (in_array($tableName, $this->excludedTables) && !($options['include_system_tables'] ?? false)) {
                return 0;
            }

            // Check if table exists
            if (!DB::select("SHOW TABLES LIKE '{$tableName}'")) {
                throw new \RuntimeException("Table '{$tableName}' does not exist. Please create the table first or run migrations.");
            }
        }

        return DB::insert($statement) ? 1 : 0;
    }

    /**
     * Execute CREATE TABLE statement
     */
    protected function executeCreateTableStatement(string $statement, array $options): void
    {
        // Extract table name
        if (preg_match('/CREATE TABLE `?(\w+)`?/i', $statement, $matches)) {
            $tableName = $matches[1];

            // Check if table should be skipped
            if (in_array($tableName, $this->excludedTables) && !($options['include_system_tables'] ?? false)) {
                return;
            }

            // Check if table already exists
            if (DB::select("SHOW TABLES LIKE '{$tableName}'")) {
                if ($options['drop_existing'] ?? false) {
                    DB::statement("DROP TABLE `{$tableName}`");
                } else {
                    throw new \RuntimeException("Table '{$tableName}' already exists. Use --drop-existing to overwrite.");
                }
            }
        }

        DB::statement($statement);
    }

    /**
     * Execute SET statement
     */
    protected function executeSetStatement(string $statement): void
    {
        // Handle specific SET statements that are safe to execute
        if (preg_match('/SET FOREIGN_KEY_CHECKS\s*=\s*(\d)/i', $statement, $matches)) {
            DB::statement("SET FOREIGN_KEY_CHECKS = {$matches[1]}");
        }
    }

    /**
     * Decompress gzip file
     */
    protected function decompressFile(string $compressedFilePath): string
    {
        $decompressedPath = str_replace('.gz', '', $compressedFilePath);

        $command = "gzip -dc '{$compressedFilePath}' > '{$decompressedPath}'";

        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Failed to decompress file: " . $process->getErrorOutput());
        }

        return $decompressedPath;
    }

    /**
     * Validate import file
     */
    public function validateImportFile(string $filePath): array
    {
        $issues = [];

        if (!File::exists($filePath)) {
            $issues[] = "File does not exist: {$filePath}";
            return $issues;
        }

        // Check file size
        $fileSize = File::size($filePath);
        if ($fileSize > 100 * 1024 * 1024) { // 100MB limit
            $issues[] = "File is too large: " . number_format($fileSize / 1024 / 1024, 2) . " MB (max: 100MB)";
        }

        // Check if it's a compressed file
        if (str_ends_with($filePath, '.gz')) {
            // Try to decompress a small portion to validate
            try {
                $this->decompressFile($filePath);
                File::delete(str_replace('.gz', '', $filePath)); // Clean up test decompression
            } catch (\Exception $e) {
                $issues[] = "Invalid compressed file: " . $e->getMessage();
            }
        } else {
            // Validate SQL content
            $content = File::get($filePath);
            if (empty(trim($content))) {
                $issues[] = "File is empty";
            }

            // Check for basic SQL structure
            if (!preg_match('/INSERT INTO/i', $content) && !preg_match('/CREATE TABLE/i', $content)) {
                $issues[] = "File does not contain valid SQL INSERT or CREATE TABLE statements";
            }
        }

        return $issues;
    }

    /**
     * Get import preview (parse without executing)
     */
    public function getImportPreview(string $filePath): array
    {
        if (!File::exists($filePath)) {
            throw new \InvalidArgumentException("Import file does not exist: {$filePath}");
        }

        // Decompress if needed
        if (str_ends_with($filePath, '.gz')) {
            $filePath = $this->decompressFile($filePath);
            $cleanupDecompressed = true;
        }

        $sqlContent = File::get($filePath);
        $statements = $this->parseSqlStatements($sqlContent);

        $preview = [
            'total_statements' => count($statements),
            'tables_to_create' => [],
            'tables_to_import' => [],
            'estimated_rows' => 0,
            'warnings' => []
        ];

        foreach ($statements as $statement) {
            if (preg_match('/CREATE TABLE `?(\w+)`?/i', $statement, $matches)) {
                $preview['tables_to_create'][] = $matches[1];
            } elseif (preg_match('/INSERT INTO `?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
                if (!in_array($tableName, $preview['tables_to_import'])) {
                    $preview['tables_to_import'][] = $tableName;
                }
                $preview['estimated_rows']++;
            }
        }

        // Check for existing tables
        foreach (array_merge($preview['tables_to_create'], $preview['tables_to_import']) as $tableName) {
            if (DB::select("SHOW TABLES LIKE '{$tableName}'")) {
                $preview['warnings'][] = "Table '{$tableName}' already exists";
            }
        }

        // Clean up decompressed file if it was created
        if (isset($cleanupDecompressed) && $cleanupDecompressed) {
            File::delete($filePath);
        }

        return $preview;
    }

    /**
     * Ensure import directory exists
     */
    protected function ensureImportDirectoryExists(): void
    {
        if (!File::exists($this->importPath)) {
            File::makeDirectory($this->importPath, 0755, true);
        }
    }

    /**
     * Get import directory path
     */
    public function getImportPath(): string
    {
        return $this->importPath;
    }

    /**
     * Set import directory path
     */
    public function setImportPath(string $path): self
    {
        $this->importPath = $path;
        $this->ensureImportDirectoryExists();
        return $this;
    }

    /**
     * Get list of excluded tables
     */
    public function getExcludedTables(): array
    {
        return $this->excludedTables;
    }

    /**
     * Set excluded tables
     */
    public function setExcludedTables(array $tables): self
    {
        $this->excludedTables = $tables;
        return $this;
    }
}
