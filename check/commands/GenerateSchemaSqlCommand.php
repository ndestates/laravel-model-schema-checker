<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Services\CommandRunner;
use Check\Utils\Logger;
use Illuminate\Support\Facades\DB;

class GenerateSchemaSqlCommand
{
    private Logger $logger;
    private CommandRunner $commandRunner;
    private string $schemaSqlPath;
    private string $projectPath;

    public function __construct(CheckConfig $config, Logger $logger, CommandRunner $commandRunner)
    {
        $this->logger = $logger;
        $this->commandRunner = $commandRunner;
        $this->projectPath = rtrim($config->get('laravel_project_path', realpath(__DIR__ . '/../../')), '/') . '/';
        // Define the output path for the schema file, which is conventional for Laravel
        $this->schemaSqlPath = $this->projectPath . 'database/schema/mysql-schema.sql';
    }

    public function execute(array $flags): void
    {
        $this->logger->info('Generating database schema as SQL using mysqldump...');
        echo "Generating database schema as SQL...\n";

        try {
            // Get database connection details from Laravel's config
            $connection = DB::connection();
            $host = $connection->getConfig('host');
            $port = $connection->getConfig('port');
            $database = $connection->getDatabaseName();
            $username = $connection->getConfig('username');
            $password = $connection->getConfig('password');

            // Ensure the output directory exists
            $schemaDir = dirname($this->schemaSqlPath);
            if (!is_dir($schemaDir)) {
                mkdir($schemaDir, 0755, true);
            }

            // Construct the mysqldump command
            // Using --no-data ensures only the schema is dumped.
            // Note: Passing password on the command line can be a security risk in some environments.
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s --no-data > %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($this->schemaSqlPath)
            );

            $output = $this->commandRunner->run($command);

            // Verify that the file was created and is not empty
            if (file_exists($this->schemaSqlPath) && filesize($this->schemaSqlPath) > 0) {
                $message = "Successfully generated SQL schema at: " . $this->schemaSqlPath;
                echo $message . "\n";
                $this->logger->info($message);
            } else {
                $errorMessage = "Error: Failed to generate SQL schema.";
                echo $errorMessage . "\n";
                if (!empty($output)) {
                    echo "Output: " . $output . "\n";
                }
                $this->logger->error($errorMessage . " Output: " . $output);
            }
        } catch (\Exception $e) {
            $errorMessage = "An exception occurred: " . $e->getMessage();
            echo $errorMessage . "\n";
            $this->logger->error($errorMessage);
        }
    }
}
