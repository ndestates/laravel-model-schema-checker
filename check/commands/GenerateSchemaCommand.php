<?php
namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Services\DatabaseAnalyzer;
use Check\Utils\Logger;
use Illuminate\Support\Facades\Schema;

class GenerateSchemaCommand
{
    private Logger $logger;
    private DatabaseAnalyzer $dbAnalyzer;

    public function __construct(CheckConfig $config, Logger $logger, DatabaseAnalyzer $dbAnalyzer)
    {
        $this->logger = $logger;
        $this->dbAnalyzer = $dbAnalyzer;
    }

    public function execute(array $flags): void
    {
        $this->logger->info('Generating database schema...');
        echo "Generating database schema...\n";

        try {
            $schema = [];
            $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();

            foreach ($tables as $table) {
                $columns = Schema::getColumnListing($table);
                $schema[$table] = [
                    'columns' => [],
                ];
                foreach ($columns as $columnName) {
                    $columnType = Schema::getColumnType($table, $columnName);
                    $schema[$table]['columns'][$columnName] = $columnType;
                }
            }

            if ($flags['json']) {
                echo json_encode($schema, JSON_PRETTY_PRINT);
            } else {
                print_r($schema);
            }
            echo "\n";
            $this->logger->info('Schema generation complete.');

        } catch (\Exception $e) {
            $errorMessage = "Error generating schema: " . $e->getMessage();
            echo $errorMessage . "\n";
            $this->logger->error($errorMessage);
        }
    }
}
