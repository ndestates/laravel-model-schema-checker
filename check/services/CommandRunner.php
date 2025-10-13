<?php

namespace Check\Services;

use Check\Utils\Logger;
use Illuminate\Support\Facades\Config;

class CommandRunner
{
    protected Logger $logger;
    protected string $environment;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->environment = $this->detectEnvironment();
    }

    /**
     * Detect the current environment (DDEV, Docker, or local)
     */
    protected function detectEnvironment(): string
    {
        // Check for DDEV
        if (getenv('DDEV_PROJECT')) {
            return 'ddev';
        }

        // Check for Docker
        if (file_exists('/.dockerenv') || getenv('DOCKER_CONTAINER')) {
            return 'docker';
        }

        // Check if we're in a DDEV project (ddev command available and can describe)
        if ($this->commandExists('ddev')) {
            $output = [];
            $returnCode = 0;
            exec('ddev describe 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0) {
                return 'ddev_available';
            }
        }

        // Check for local PHP
        if ($this->commandExists('php')) {
            return 'local_php';
        }

        return 'unknown';
    }

    /**
     * Check if a command exists on the system
     */
    protected function commandExists(string $command): bool
    {
        $output = [];
        $returnCode = 0;
        exec("command -v $command 2>/dev/null", $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Get the current environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Execute a PHP command with environment awareness
     */
    public function runPhpCommand(string $phpScript, array $args = []): array
    {
        $command = $this->buildPhpCommand($phpScript, $args);

        $this->logger->info("Executing: $command");

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        return [
            'command' => $command,
            'output' => $output,
            'return_code' => $returnCode,
            'success' => $returnCode === 0
        ];
    }

    /**
     * Execute a Laravel Artisan command
     */
    public function runArtisanCommand(string $command, array $args = []): array
    {
        $phpCommand = "artisan $command";
        if (!empty($args)) {
            $phpCommand .= ' ' . implode(' ', array_map('escapeshellarg', $args));
        }

        return $this->runPhpCommand($phpCommand, []);
    }

    /**
     * Execute a shell command with environment awareness
     */
    public function runShellCommand(string $command): array
    {
        $fullCommand = $this->buildShellCommand($command);

        $this->logger->info("Executing: $fullCommand");

        $output = [];
        $returnCode = 0;
        exec($fullCommand . ' 2>&1', $output, $returnCode);

        return [
            'command' => $fullCommand,
            'output' => $output,
            'return_code' => $returnCode,
            'success' => $returnCode === 0
        ];
    }

    /**
     * Build a PHP command for the current environment
     */
    protected function buildPhpCommand(string $phpScript, array $args = []): string
    {
        $argString = !empty($args) ? ' ' . implode(' ', array_map('escapeshellarg', $args)) : '';

        // If we're already inside a DDEV container, don't use ddev exec prefix
        if ($this->environment === 'ddev' && getenv('DDEV_PROJECT')) {
            $this->logger->info("Running PHP command inside DDEV container");
            return "php $phpScript$argString";
        }

        switch ($this->environment) {
            case 'ddev':
                $this->logger->info("Running in DDEV environment");
                // Use relative path in DDEV container
                $relativePath = str_replace(base_path() . '/', '', $phpScript);
                return "ddev exec php $relativePath$argString";

            case 'ddev_available':
                $this->logger->info("DDEV project detected, using DDEV");
                $relativePath = str_replace(base_path() . '/', '', $phpScript);
                return "ddev exec php $relativePath$argString";

            case 'docker':
                $this->logger->info("Running in Docker container");
                // Try different PHP paths in containers
                if ($this->commandExists('php')) {
                    return "php $phpScript$argString";
                } elseif (file_exists('/usr/local/bin/php')) {
                    return "/usr/local/bin/php $phpScript$argString";
                } elseif (file_exists('/usr/bin/php')) {
                    return "/usr/bin/php $phpScript$argString";
                } else {
                    throw new \RuntimeException("PHP not found in Docker container");
                }

            case 'local_php':
                $this->logger->info("Running with local PHP");
                return "php $phpScript$argString";

            default:
                throw new \RuntimeException("No suitable PHP environment found. Please ensure DDEV, Docker, or local PHP is available.");
        }
    }

    /**
     * Build a shell command for the current environment
     */
    protected function buildShellCommand(string $command): string
    {
        // If we're already inside a DDEV container, don't use ddev exec prefix
        if ($this->environment === 'ddev' && getenv('DDEV_PROJECT')) {
            return $command;
        }

        switch ($this->environment) {
            case 'ddev':
            case 'ddev_available':
                return "ddev exec $command";

            case 'docker':
                return $command;

            case 'local_php':
                return $command;

            default:
                return $command;
        }
    }

    /**
     * Run database migrations
     */
    public function runMigrations(): array
    {
        return $this->runArtisanCommand('migrate');
    }

    /**
     * Rollback database migrations
     */
    public function rollbackMigrations(): array
    {
        return $this->runArtisanCommand('migrate:rollback');
    }

    /**
     * Create a database backup
     */
    public function createDatabaseBackup(string $filename = null): array
    {
        if (!$filename) {
            $filename = 'backup_' . date('Y_m_d_His') . '.sql';
        }

        // Get database configuration
        $dbConfig = Config::get('database.connections.' . Config::get('database.default'));
        if (!$dbConfig) {
            return [
                'command' => 'N/A',
                'output' => ['Database configuration not found'],
                'return_code' => 1,
                'success' => false
            ];
        }

        $host = $dbConfig['host'] ?? 'localhost';
        $port = $dbConfig['port'] ?? null;
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        // Build backup command based on database type and environment
        $driver = $dbConfig['driver'] ?? 'mysql';

        // For DDEV, we need to handle backup differently since we're running inside container
        if ($this->environment === 'ddev') {
            return $this->createDdevDatabaseBackup($filename, $dbConfig);
        }

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $command = "mysqldump";
                if ($host) $command .= " -h $host";
                if ($port) $command .= " -P $port";
                if ($username) $command .= " -u $username";
                if ($password) $command .= " -p$password";
                $command .= " $database > $filename";
                break;

            case 'pgsql':
                $command = "pg_dump";
                if ($host) $command .= " -h $host";
                if ($port) $command .= " -p $port";
                if ($username) $command .= " -U $username";
                if ($password) putenv("PGPASSWORD=$password");
                $command .= " $database > $filename";
                break;

            case 'sqlite':
                // For SQLite, just copy the file
                $command = "cp $database $filename";
                break;

            default:
                return [
                    'command' => 'N/A',
                    'output' => ["Unsupported database driver: $driver"],
                    'return_code' => 1,
                    'success' => false
                ];
        }

        $this->logger->info("Creating database backup: $filename");
        $this->logger->info("Using $driver database driver");

        $result = $this->runShellCommand($command);

        if ($result['success']) {
            $this->logger->success("Database backup created successfully: $filename");
        } else {
            $this->logger->error("Database backup failed");
        }

        return $result;
    }

    /**
     * Create database backup specifically for DDEV environment
     */
    protected function createDdevDatabaseBackup(string $filename, array $dbConfig): array
    {
        $driver = $dbConfig['driver'] ?? 'mysql';
        $database = $dbConfig['database'];

        $this->logger->info("Creating DDEV database backup: $filename");
        $this->logger->info("Using $driver database driver in DDEV environment");

        // For DDEV, use Laravel's built-in backup if available, otherwise try direct dump
        try {
            // Try using Laravel's database backup command if available
            if ($this->commandExists('php') && file_exists(base_path('artisan'))) {
                $backupCommand = "php artisan db:backup --filename=$filename";
                $result = $this->runShellCommand($backupCommand);

                if ($result['success']) {
                    $this->logger->success("Database backup created successfully using Laravel: $filename");
                    return $result;
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning("Laravel backup failed, trying direct database dump");
        }

        // Fallback: Try direct database dump using PHP
        try {
            $backupContent = $this->createDatabaseDumpViaPHP($dbConfig);
            if (file_put_contents($filename, $backupContent) !== false) {
                $this->logger->success("Database backup created successfully via PHP: $filename");
                return [
                    'command' => 'PHP database dump',
                    'output' => ['Backup created successfully'],
                    'return_code' => 0,
                    'success' => true
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("PHP database dump failed: " . $e->getMessage());
        }

        // Last resort: Try running mysqldump directly (may not work in container)
        $command = $this->buildDdevMysqldumpCommand($dbConfig, $filename);
        $result = $this->runShellCommand($command);

        if ($result['success']) {
            $this->logger->success("Database backup created successfully: $filename");
        } else {
            $this->logger->error("All database backup methods failed");
        }

        return $result;
    }

    /**
     * Build mysqldump command for DDEV environment
     */
    protected function buildDdevMysqldumpCommand(array $dbConfig, string $filename): string
    {
        $host = $dbConfig['host'] ?? 'db';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'];
        $username = $dbConfig['username'] ?? 'db';
        $password = $dbConfig['password'] ?? 'db';

        $driver = $dbConfig['driver'] ?? 'mysql';

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $command = "mysqldump -h $host -P $port -u $username -p$password $database > $filename";
                break;

            case 'pgsql':
                putenv("PGPASSWORD=$password");
                $command = "pg_dump -h $host -p $port -U $username $database > $filename";
                break;

            default:
                $command = "echo 'Unsupported database driver for DDEV backup'";
        }

        return $command;
    }

    /**
     * Create database dump using PHP and PDO
     */
    protected function createDatabaseDumpViaPHP(array $dbConfig): string
    {
        $driver = $dbConfig['driver'] ?? 'mysql';
        $host = $dbConfig['host'] ?? 'db';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'];
        $username = $dbConfig['username'] ?? 'db';
        $password = $dbConfig['password'] ?? 'db';

        $dsn = $this->buildDsn($dbConfig);

        $pdo = new \PDO($dsn, $username, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $dump = "-- Database dump created by Laravel Model-Database Schema Checker\n";
        $dump .= "-- Created at: " . date('Y-m-d H:i:s') . "\n\n";

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $dump .= "-- Table: $table\n";
                $dump .= "DROP TABLE IF EXISTS `$table`;\n";

                // Get create table statement
                $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_ASSOC);
                $dump .= $createTable['Create Table'] . ";\n\n";

                // Get data
                $data = $pdo->query("SELECT * FROM `$table`")->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($data)) {
                    $columns = array_keys($data[0]);
                    $dump .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";

                    $values = [];
                    foreach ($data as $row) {
                        $rowValues = [];
                        foreach ($row as $value) {
                            $rowValues[] = $pdo->quote($value);
                        }
                        $values[] = "(" . implode(", ", $rowValues) . ")";
                    }
                    $dump .= implode(",\n", $values) . ";\n";
                }
                $dump .= "\n";
            }

            $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        }

        return $dump;
    }

    /**
     * Build DSN for PDO connection
     */
    protected function buildDsn(array $dbConfig): string
    {
        $driver = $dbConfig['driver'] ?? 'mysql';
        $host = $dbConfig['host'] ?? 'db';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'];

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                return "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
            case 'pgsql':
                return "pgsql:host=$host;port=$port;dbname=$database";
            case 'sqlite':
                return "sqlite:$database";
            default:
                throw new \Exception("Unsupported database driver: $driver");
        }
    }
}