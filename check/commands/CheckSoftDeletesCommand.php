<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Services\ModelAnalyzer;
use Check\Services\DatabaseAnalyzer;
use Check\Utils\Logger;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckSoftDeletesCommand
{
    private Logger $logger;
    private ModelAnalyzer $modelAnalyzer;
    private DatabaseAnalyzer $databaseAnalyzer;

    public function __construct(CheckConfig $config, Logger $logger, ModelAnalyzer $modelAnalyzer, DatabaseAnalyzer $databaseAnalyzer)
    {
        $this->logger = $logger;
        $this->modelAnalyzer = $modelAnalyzer;
        $this->databaseAnalyzer = $databaseAnalyzer;
    }

    public function execute(): void
    {
        $this->logger->info("Starting soft deletes trait check...");
        echo "Starting soft deletes trait check...\n";

        $models = $this->modelAnalyzer->getAllModelClasses();
        $totalErrors = 0;

        foreach (array_keys($models) as $modelClass) {
            $tableName = $this->modelAnalyzer->getTableName($modelClass);
            $columns = $this->databaseAnalyzer->getTableColumns($tableName);

            if (in_array('deleted_at', $columns)) {
                $traits = class_uses_recursive($modelClass);
                if (!in_array(SoftDeletes::class, $traits)) {
                    $this->logAndEcho(
                        "Error: Model '{$modelClass}' has a 'deleted_at' column but does not use the SoftDeletes trait.",
                        'error'
                    );
                    $totalErrors++;
                }
            }
        }

        if ($totalErrors > 0) {
            $this->logAndEcho("Soft deletes check found {$totalErrors} errors.", 'error');
        } else {
            $this->logAndEcho("Soft deletes check completed successfully. No issues found.", 'info');
        }
    }

    private function logAndEcho(string $message, string $level = 'info'): void
    {
        $this->logger->{$level}($message);
        echo $message . "\n";
    }
}
