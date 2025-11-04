<?php

namespace NDEstates\LaravelModelSchemaChecker\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class DataExporter
{
    protected string $exportPath;
    protected array $excludedTables = [
        'migrations',
        'failed_jobs',
        'password_resets',
        'personal_access_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ];

    public function __construct(string $exportPath = null)
    {
        $this->exportPath = $exportPath ?? storage_path('app/exports');
    }

    /**
     * Export database data to SQL file
     */
    public function exportDatabaseData(array $options = []): string
    {
        $this->ensureExportDirectoryExists();
        $filename = $this->generateFilename('sql');
        $filepath = $this->exportPath . '/' . $filename;

        $tables = $this->getTablesToExport($options);
        $sqlContent = $this->generateSqlDump($tables, $options);

        File::put($filepath, $sqlContent);

        return $filepath;
    }

    /**
     * Export database data to compressed SQL file
     */
    public function exportDatabaseDataToCompressedFile(array $options = []): string
    {
        $sqlFile = $this->exportDatabaseData($options);
        $compressedFile = $this->compressFile($sqlFile);

        // Remove the uncompressed file
        File::delete($sqlFile);

        return $compressedFile;
    }

    /**
     * Export specific tables
     */
    public function exportTables(array $tableNames, array $options = []): string
    {
        $this->ensureExportDirectoryExists();
        $filename = $this->generateFilename('sql', 'tables_' . implode('_', $tableNames));
        $filepath = $this->exportPath . '/' . $filename;

        $sqlContent = $this->generateSqlDump($tableNames, $options);
        File::put($filepath, $sqlContent);

        return $filepath;
    }

    /**
     * Export specific tables to compressed file
     */
    public function exportTablesToCompressedFile(array $tableNames, array $options = []): string
    {
        $sqlFile = $this->exportTables($tableNames, $options);
        $compressedFile = $this->compressFile($sqlFile);

        // Remove the uncompressed file
        File::delete($sqlFile);

        return $compressedFile;
    }

    /**
     * Get list of tables to export
     */
    protected function getTablesToExport(array $options = []): array
    {
        $database = config('database.connections.mysql.database');

        $tables = DB::select('SHOW TABLES');
        $tableNames = [];

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_{$database}"};

            // Skip excluded tables unless specifically requested
            if (in_array($tableName, $this->excludedTables) && !($options['include_system_tables'] ?? false)) {
                continue;
            }

            // Skip tables that are explicitly excluded
            if (isset($options['exclude_tables']) && in_array($tableName, $options['exclude_tables'])) {
                continue;
            }

            $tableNames[] = $tableName;
        }

        return $tableNames;
    }

    /**
     * Generate SQL dump content
     */
    protected function generateSqlDump(array $tables, array $options = []): string
    {
        $sql = $this->generateSqlHeader();

        foreach ($tables as $table) {
            $sql .= $this->generateTableData($table, $options);
        }

        $sql .= $this->generateSqlFooter();

        return $sql;
    }

    /**
     * Generate SQL header with database information
     */
    protected function generateSqlHeader(): string
    {
        $database = config('database.connections.mysql.database');
        $timestamp = Carbon::now()->toDateTimeString();

        return "-- Laravel Model Schema Checker Database Export
-- Generated: {$timestamp}
-- Database: {$database}
-- Host: " . config('database.connections.mysql.host') . "
-- Exported by: " . (Auth::check() ? (Auth::user()?->email ?? 'Unknown') : 'System') . "

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;

";
    }

    /**
     * Generate SQL footer
     */
    protected function generateSqlFooter(): string
    {
        return "

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- Export completed successfully
";
    }

    /**
     * Generate data for a specific table
     */
    protected function generateTableData(string $tableName, array $options = []): string
    {
        $sql = "\n-- Dumping data for table `{$tableName}`\n";

        // Get table structure (optional)
        if ($options['include_structure'] ?? false) {
            $sql .= $this->getTableStructure($tableName);
        }

        // Get table data
        $data = DB::table($tableName)->get();

        if ($data->isEmpty()) {
            $sql .= "-- Table `{$tableName}` is empty\n\n";
            return $sql;
        }

        $columns = $this->getTableColumns($tableName);
        $columnNames = array_keys($columns);

        foreach ($data as $row) {
            $values = [];
            foreach ($columnNames as $column) {
                $value = $row->$column;
                $values[] = $this->escapeValue($value, $columns[$column]);
            }

            $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columnNames) . "`) VALUES (" . implode(', ', $values) . ");\n";
        }

        $sql .= "\n";

        return $sql;
    }

    /**
     * Get table structure as SQL
     */
    protected function getTableStructure(string $tableName): string
    {
        $result = DB::select("SHOW CREATE TABLE `{$tableName}`");
        $createTableSql = $result[0]->{'Create Table'};

        return "-- Table structure for `{$tableName}`\n{$createTableSql};\n\n";
    }

    /**
     * Get table columns information
     */
    protected function getTableColumns(string $tableName): array
    {
        $columns = DB::select("DESCRIBE `{$tableName}`");
        $columnInfo = [];

        foreach ($columns as $column) {
            $columnInfo[$column->Field] = [
                'type' => $this->parseColumnType($column->Type),
                'nullable' => $column->Null === 'YES',
            ];
        }

        return $columnInfo;
    }

    /**
     * Parse MySQL column type
     */
    protected function parseColumnType(string $type): string
    {
        if (preg_match('/^(\w+)/', $type, $matches)) {
            return $matches[1];
        }
        return $type;
    }

    /**
     * Escape value for SQL insertion
     */
    protected function escapeValue(mixed $value, array $columnInfo): string
    {
        if ($value === null) {
            return 'NULL';
        }

        // Handle different data types
        switch ($columnInfo['type']) {
            case 'int':
            case 'bigint':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
                return (string) $value;

            case 'decimal':
            case 'float':
            case 'double':
                return (string) $value;

            case 'timestamp':
            case 'datetime':
            case 'date':
            case 'time':
                return "'{$value}'";

            default:
                // String types - escape quotes
                return "'" . addslashes($value) . "'";
        }
    }

    /**
     * Generate filename for export
     */
    protected function generateFilename(string $extension, string $prefix = 'database_export'): string
    {
        $timestamp = Carbon::now()->format('Y_m_d_H_i_s');
        return "{$prefix}_{$timestamp}.{$extension}";
    }

    /**
     * Compress file using gzip
     */
    protected function compressFile(string $filePath): string
    {
        $compressedPath = $filePath . '.gz';

        $command = "gzip -c '{$filePath}' > '{$compressedPath}'";

        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Failed to compress file: " . $process->getErrorOutput());
        }

        return $compressedPath;
    }

    /**
     * Ensure export directory exists
     */
    protected function ensureExportDirectoryExists(): void
    {
        if (!File::exists($this->exportPath)) {
            File::makeDirectory($this->exportPath, 0755, true);
        }
    }

    /**
     * Get export directory path
     */
    public function getExportPath(): string
    {
        return $this->exportPath;
    }

    /**
     * Set export directory path
     */
    public function setExportPath(string $path): self
    {
        $this->exportPath = $path;
        $this->ensureExportDirectoryExists();
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

    /**
     * Clean up old export files
     */
    public function cleanupOldExports(int $daysOld = 30): int
    {
        $files = File::files($this->exportPath);
        $deletedCount = 0;

        foreach ($files as $file) {
            if ($file->getMTime() < Carbon::now()->subDays($daysOld)->timestamp) {
                File::delete($file->getPathname());
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}
