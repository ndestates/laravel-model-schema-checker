<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Services\ModelAnalyzer;
use Check\Services\RelationshipAnalyzer;
use Check\Utils\Logger;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

class CheckInverseRelationshipsCommand
{
    private Logger $logger;
    private CheckConfig $config;
    private ModelAnalyzer $modelAnalyzer;
    private RelationshipAnalyzer $relationshipAnalyzer;

    public function __construct(CheckConfig $config, Logger $logger, ModelAnalyzer $modelAnalyzer, RelationshipAnalyzer $relationshipAnalyzer)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->modelAnalyzer = $modelAnalyzer;
        $this->relationshipAnalyzer = $relationshipAnalyzer;
    }

    public function execute(): void
    {
        $this->logger->info("Starting inverse relationship check...");
        echo "Starting inverse relationship check...\n";

        $models = $this->modelAnalyzer->getAllModelClasses();
        $totalErrors = 0;

        foreach (array_keys($models) as $modelClass) {
            $relationships = $this->relationshipAnalyzer->analyzeModelRelationships($modelClass);

            foreach ($relationships as $relationshipName => $relationshipInfo) {
                if (!$this->relationshipAnalyzer->hasInverseRelationship($modelClass, $relationshipName, $relationshipInfo)) {
                    $relatedModel = $relationshipInfo['related_model'];
                    $this->logAndEcho(
                        "Error: Missing inverse relationship. '{$modelClass}' defines '{$relationshipName}' but no corresponding inverse was found on '{$relatedModel}'.",
                        'error'
                    );
                    $totalErrors++;
                }
            }
        }

        if ($totalErrors > 0) {
            $this->logAndEcho("Inverse relationship check found {$totalErrors} errors.", 'error');
        } else {
            $this->logAndEcho("Inverse relationship check completed successfully. No issues found.", 'info');
        }
    }

    private function logAndEcho(string $message, string $level = 'info'): void
    {
        $this->logger->{$level}($message);
        echo $message . "\n";
    }
}
