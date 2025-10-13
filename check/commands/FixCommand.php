<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Check\Services\ModelAnalyzer;
use Check\Services\DatabaseAnalyzer;

class FixCommand extends CompareCommand
{
    public function execute(array $flags): void
    {
        $this->logger->section("FIXING MODEL FILLABLE PROPERTIES");
        
        if ($this->config->isBackupEnabled()) {
            $this->logger->warning("Creating backups before making changes...");
        }
        
        $modelClasses = $this->modelAnalyzer->getAllModelClasses();
        $fixedModels = 0;
        
        foreach ($modelClasses as $modelClass => $filePath) {
            $tableName = $this->modelAnalyzer->getTableName($modelClass);
            $modelFields = $this->modelAnalyzer->getModelFields($modelClass);
            $dbSchema = $this->dbAnalyzer->getTableSchema($tableName);
            
            if (empty($dbSchema)) {
                if (!$this->dbAnalyzer->tableExists($tableName)) {
                    $this->logger->warning("Skipping $modelClass - table '$tableName' does not exist");
                    continue;
                }
            }
            
            $comparison = $this->compareModelAndSchema($modelFields, $dbSchema, $modelClass, $tableName);
            
            if ($comparison['has_changes']) {
                $this->logger->log("\nFIXING: {$comparison['model_class']}");
                
                if ($flags['dry_run']) {
                    $this->logger->log("[DRY RUN] Would update \$fillable with: " . implode(', ', $comparison['corrected_fields']));
                } else {
                    if ($this->modelAnalyzer->updateModelFile($filePath, $comparison['corrected_fields'])) {
                        $fixedModels++;
                    }
                }
            }
        }
        
        $this->logger->section("FIX SUMMARY");
        if ($flags['dry_run']) {
            $this->logger->info("DRY RUN: Would have fixed $fixedModels models");
        } else {
            $this->logger->success("Fixed $fixedModels model(s)");
        }
    }
}