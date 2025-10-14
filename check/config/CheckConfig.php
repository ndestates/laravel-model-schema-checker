<?php

namespace Check\Config;

class CheckConfig
{
    private array $config;
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
    }
    
    private function loadConfig(): array
    {
        // Use Laravel helpers if available, otherwise use basic paths
        $basePath = getcwd();
        
        if (function_exists('app_path')) {
            // Laravel is bootstrapped, use helpers
            $modelsDir = app_path('Models');
            $migrationsDir = database_path('migrations');
            $dbConnection = 'mysql'; // Default Laravel connection
        } else {
            // Laravel not available, use basic paths
            $appPath = $basePath . '/app';
            $databasePath = $basePath . '/database';
            $modelsDir = $appPath . '/Models';
            $migrationsDir = $databasePath . '/migrations';
            $dbConnection = 'sqlite'; // Use SQLite for CI
        }
        
        return [
            'models_dir' => $modelsDir,
            'excluded_fields' => ['id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by'],
            'database_connection' => $dbConnection,
            'migrations_dir' => $migrationsDir,
            'timestamp' => date('Y-m-d-His'),
            'backup_enabled' => true,
            'ignore_id_columns_in_constraint_check' => true,
        ];
    }
    
    public function getModelsDir(): string
    {
        return $this->config['models_dir'];
    }
    
    public function getExcludedFields(): array
    {
        return $this->config['excluded_fields'];
    }
    
    public function getDatabaseConnection(): string
    {
        return $this->config['database_connection'];
    }
    
    public function getMigrationsDir(): string
    {
        return $this->config['migrations_dir'];
    }
    
    public function getLogFile(): string
    {
        return dirname(__DIR__) . "/logs/{$this->config['timestamp']}-check.log";
    }
    
    public function getTimestamp(): string
    {
        return $this->config['timestamp'];
    }
    
    public function isBackupEnabled(): bool
    {
        return $this->config['backup_enabled'];
    }
    
    public function shouldRemoveColumns(): bool
    {
        return false; // Default to false for safety - only remove columns when explicitly requested
    }

    public function shouldIgnoreIdColumnsInConstraintCheck(): bool
    {
        return $this->config['ignore_id_columns_in_constraint_check'] ?? false;
    }
}