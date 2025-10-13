<?php

namespace Check\Services;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Illuminate\Support\Facades\Schema;

class DatabaseAnalyzer
{
    private CheckConfig $config;
    private Logger $logger;
    
    public function __construct(CheckConfig $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    public function getTableSchema(string $tableName): array
    {
        try {
            $connection = $this->config->getDatabaseConnection();
            $columns = Schema::connection($connection)->getColumns($tableName);
            
            $schema = [];
            foreach ($columns as $column) {
                $schema[$column['name']] = [
                    'type' => $column['type'],
                    'nullable' => $column['nullable'],
                    'default' => $column['default'],
                ];
            }
            
            return $schema;
        } catch (\Exception $e) {
            $this->logger->error("Error inspecting table $tableName: {$e->getMessage()}");
            return [];
        }
    }
    
    public function tableExists(string $tableName): bool
    {
        try {
            $connection = $this->config->getDatabaseConnection();
            return Schema::connection($connection)->hasTable($tableName);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getForeignKeyColumns(string $tableName): array
    {
        try {
            $connection = $this->config->getDatabaseConnection();
            
            // Query information_schema to find foreign key columns
            $foreignKeys = \DB::connection($connection)->select("
                SELECT COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$tableName]);
            
            return array_column($foreignKeys, 'COLUMN_NAME');
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getReferencedColumns(string $tableName): array
    {
        try {
            $connection = $this->config->getDatabaseConnection();
            
            // Query information_schema to find columns that are referenced by foreign keys from other tables
            $referencedColumns = \DB::connection($connection)->select("
                SELECT REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME = ?
                AND REFERENCED_COLUMN_NAME IS NOT NULL
            ", [$tableName]);
            
            return array_column($referencedColumns, 'REFERENCED_COLUMN_NAME');
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getTableColumns(string $tableName): array
    {
        try {
            $connection = $this->config->getDatabaseConnection();
            return Schema::connection($connection)->getColumns($tableName);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getTableIndexes(string $tableName): array
    {
        try {
            $connection = $this->config->getDatabaseConnection();
            return Schema::connection($connection)->getIndexes($tableName);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function getTableForeignKeys(string $tableName): array
    {
        try {
            $connection = $this->config->getDatabaseConnection();
            return Schema::connection($connection)->getForeignKeys($tableName);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function generateBackupCommands(): void
    {
        $connection = $this->config->getDatabaseConnection();
        $config = config("database.connections.$connection");
        
        if (!$config) {
            $this->logger->warning("Database connection configuration not found");
            return;
        }
        
        $database = $config['database'] ?? 'unknown';
        $username = $config['username'] ?? 'username';
        $host = $config['host'] ?? 'localhost';
        
        $backupFile = "backup_{$database}_" . date('Y_m_d_His') . ".sql";
        
        $this->logger->section("DATABASE BACKUP RECOMMENDATIONS");
        $this->logger->log("Before making any changes, create a backup:");
        $this->logger->log("mysqldump -h $host -u $username -p $database > $backupFile");
    }

    public function findMigrationFileForTable(string $tableName): ?string
    {
        $migrationsPath = $this->config->getMigrationsDir();
        $files = glob("{$migrationsPath}/*.php");

        foreach (array_reverse($files) as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, "Schema::create('{$tableName}'") || str_contains($content, "Schema::table('{$tableName}'")) {
                return $file;
            }
        }

        return null;
    }
}