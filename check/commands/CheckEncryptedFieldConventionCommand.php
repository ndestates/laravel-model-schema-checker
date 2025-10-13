<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Services\ModelAnalyzer;
use Check\Services\DatabaseAnalyzer;
use Check\Utils\Logger;

class CheckEncryptedFieldConventionCommand
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
        $this->logger->info("Starting encrypted field naming convention check...");
        echo "Starting encrypted field naming convention check...\n";

        $models = $this->modelAnalyzer->getAllModelClasses();
        $totalErrors = 0;

        foreach (array_keys($models) as $modelClass) {
            $casts = $this->modelAnalyzer->getModelCasts($modelClass);
            $tableName = $this->modelAnalyzer->getTableName($modelClass);
            $columns = $this->databaseAnalyzer->getTableColumns($tableName);

            foreach ($casts as $attribute => $castType) {
                if ($castType === 'encrypted') {
                    if (!in_array($attribute, $columns)) {
                        // This is a different problem, handled by the main check.
                        continue;
                    }
                    if (!str_ends_with($attribute, '_encrypted')) {
                        $this->logAndEcho(
                            "Warning: Attribute '{$attribute}' in model '{$modelClass}' is encrypted but does not follow the '_encrypted' naming convention.",
                            'warning'
                        );
                        // This is a warning, not a hard error.
                    }
                }
            }
        }

        $this->logAndEcho("Encrypted field naming convention check completed.", 'info');
    }

    private function logAndEcho(string $message, string $level = 'info'): void
    {
        $this->logger->{$level}($message);
        echo $message . "\n";
    }
}
