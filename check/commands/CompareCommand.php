<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Check\Services\ModelAnalyzer;
use Check\Services\DatabaseAnalyzer;

class CompareCommand
{
    protected CheckConfig $config;
    protected Logger $logger;
    protected ModelAnalyzer $modelAnalyzer;
    protected DatabaseAnalyzer $dbAnalyzer;
    
    public function __construct(
        CheckConfig $config, 
        Logger $logger, 
        ModelAnalyzer $modelAnalyzer, 
        DatabaseAnalyzer $dbAnalyzer
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->modelAnalyzer = $modelAnalyzer;
        $this->dbAnalyzer = $dbAnalyzer;
    }
    
    public function execute(array $flags): void
    {
        $this->logger->section("MODEL-DATABASE COMPARISON");
        
        $modelClasses = $this->modelAnalyzer->getAllModelClasses();
        $totalChanges = 0;
        $results = [];
        
        foreach ($modelClasses as $modelClass => $filePath) {
            $tableName = $this->modelAnalyzer->getTableName($modelClass);
            $modelFields = $this->modelAnalyzer->getModelFields($modelClass);
            $dbSchema = $this->dbAnalyzer->getTableSchema($tableName);
            
            $result = [
                'model_class' => $modelClass,
                'table_name' => $tableName,
                'file_path' => $filePath,
                'status' => 'ok',
                'issues' => []
            ];
            
            if (empty($dbSchema)) {
                if (!$this->dbAnalyzer->tableExists($tableName)) {
                    $result['status'] = 'table_missing';
                    $result['issues'][] = "Table '$tableName' does not exist";
                    $this->logger->warning("Table '$tableName' does not exist for model '$modelClass'");
                    $results[] = $result;
                    continue;
                }
            }
            
            $comparison = $this->compareModelAndSchema($modelFields, $dbSchema, $modelClass, $tableName);
            
            $this->checkConstraints($dbSchema, $result, $filePath);

            if ($comparison['has_changes']) {
                $totalChanges++;
                $result['status'] = 'mismatch';
                
                $this->logFillableMismatch($comparison, $filePath);

                if (!$flags['dry_run']) {
                    $this->suggestFix($comparison);
                }
            } else {
                $this->logger->log("\nComparing Model: $modelClass (Table: $tableName)");
                $this->logger->log("File: $filePath");
                if (empty($result['issues'])) {
                    $this->logger->log("OK: \$fillable property matches database schema.");
                }
            }
            
            // Log constraint warnings if there are any
            if (!empty($result['issues'])) {
                foreach($result['issues'] as $issue) {
                    $this->logger->logIssue($issue);
                }
            }

            $results[] = $result;
        }
        
        $this->logger->section("SUMMARY");
        $this->logger->info("Models processed: " . count($modelClasses));
        $this->logger->info("Models with changes: $totalChanges");
        
        if ($totalChanges > 0 && !$flags['fix'] && !$flags['dry_run']) {
            $this->logger->info("Run with --fix to apply changes automatically");
        }
        
        // JSON output if requested
        if ($flags['json']) {
            $jsonOutput = [
                'timestamp' => date('c'),
                'summary' => [
                    'models_processed' => count($modelClasses),
                    'models_with_changes' => $totalChanges,
                    'total_issues' => array_sum(array_map(fn($r) => count($r['issues']), $results))
                ],
                'results' => $results
            ];
            
            echo json_encode($jsonOutput, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    protected function compareModelAndSchema(array $modelFields, array $dbSchema, string $modelClass, string $tableName): array
    {
        $dbFields = array_keys($dbSchema);
        $excludedFields = $this->config->getExcludedFields();
        
        $fillableDbFields = array_diff($dbFields, $excludedFields);
        
        $modelOnly = array_diff($modelFields, $fillableDbFields);
        $dbOnly = array_diff($fillableDbFields, $modelFields);
        
        $correctedFields = array_unique(array_merge(array_intersect($modelFields, $fillableDbFields), $dbOnly));
        sort($correctedFields);

        return [
            'model_class' => $modelClass,
            'table_name' => $tableName,
            'has_changes' => !empty($modelOnly) || !empty($dbOnly),
            'model_only' => $modelOnly,
            'db_only' => $dbOnly,
            'corrected_fields' => $correctedFields,
        ];
    }

    private function checkConstraints(array $dbSchema, array &$result, string $modelPath): void
    {
        $excludedFields = $this->config->getExcludedFields();
        $ignoreIdColumns = $this->config->shouldIgnoreIdColumnsInConstraintCheck();
        $foreignKeyColumns = $this->dbAnalyzer->getForeignKeyColumns($result['table_name']);

        foreach ($dbSchema as $columnName => $column) {
            if (in_array($columnName, $excludedFields)) {
                continue;
            }

            if ($ignoreIdColumns && substr($columnName, -3) === '_id') {
                continue;
            }

            // Skip columns that are part of foreign key constraints
            if (in_array($columnName, $foreignKeyColumns)) {
                continue;
            }

            if (!$column['nullable'] && is_null($column['default'])) {
                $migrationFile = $this->dbAnalyzer->findMigrationFileForTable($result['table_name']);
                $suggestion = $this->generateConstraintFixSuggestion($columnName, $column, $result['table_name'], $migrationFile);
                
                $result['issues'][] = [
                    'level' => 'warning',
                    'message' => "Constraint Violation: Column '{$columnName}' in table '{$result['table_name']}' is non-nullable but has no default value.",
                    'file' => $migrationFile,
                    'suggestion' => $suggestion
                ];
            }
        }
    }
    
    private function logFillableMismatch(array $comparison, string $filePath): void
    {
        $modelClass = $comparison['model_class'];
        $tableName = $comparison['table_name'];

        $this->logger->log("\nComparing Model: {$modelClass} (Table: {$tableName})");

        if (!empty($comparison['model_only'])) {
            foreach ($comparison['model_only'] as $field) {
                $this->logger->logIssue([
                    'level' => 'warning',
                    'message' => "Fillable Mismatch: Field '{$field}' is in \$fillable but not in the database table '{$tableName}'.",
                    'file' => $filePath,
                    'suggestion' => "Remove '{$field}' from the \$fillable array in the {$modelClass} model."
                ]);
            }
        }

        if (!empty($comparison['db_only'])) {
            foreach ($comparison['db_only'] as $field) {
                $this->logger->logIssue([
                    'level' => 'warning',
                    'message' => "Fillable Mismatch: Field '{$field}' is in the database table '{$tableName}' but not in \$fillable.",
                    'file' => $filePath,
                    'suggestion' => "Add '{$field}' to the \$fillable array in the {$modelClass} model to allow mass assignment."
                ]);
            }
        }
    }
    
    private function generateConstraintFixSuggestion(string $columnName, array $column, string $tableName, ?string $migrationFile): string
    {
        $type = strtolower($column['type']);
        $isForeignKey = substr($columnName, -3) === '_id';
        
        // For foreign keys, suggest nullable
        if ($isForeignKey) {
            return "Since '{$columnName}' appears to be a foreign key, consider making it nullable:\n" .
                   "  \$table->{$columnName}('{$type}')->nullable()->change();";
        }
        
        // Type-specific suggestions
        switch ($type) {
            case 'varchar':
            case 'string':
            case 'text':
            case 'longtext':
                if (str_contains($columnName, 'name') || str_contains($columnName, 'title')) {
                    return "For name/title fields, consider a default empty string:\n" .
                           "  \$table->{$columnName}('{$type}')->default('')->change();";
                }
                if (str_contains($columnName, 'status') || str_contains($columnName, 'type') || str_contains($columnName, 'state')) {
                    return "For status/type fields, consider a default value like 'active' or 'pending':\n" .
                           "  \$table->{$columnName}('{$type}')->default('active')->change();";
                }
                return "For text fields, consider making nullable or providing a default:\n" .
                       "  \$table->{$columnName}('{$type}')->nullable()->change();\n" .
                       "  // OR\n" .
                       "  \$table->{$columnName}('{$type}')->default('')->change();";
                
            case 'boolean':
            case 'tinyint':
                return "For boolean fields, set a default value:\n" .
                       "  \$table->{$columnName}('{$type}')->default(false)->change();";
                
            case 'integer':
            case 'int':
            case 'bigint':
                if (str_contains($columnName, 'count') || str_contains($columnName, 'amount') || str_contains($columnName, 'quantity')) {
                    return "For count/amount fields, consider default 0:\n" .
                           "  \$table->{$columnName}('{$type}')->default(0)->change();";
                }
                return "For integer fields, consider making nullable or setting a default:\n" .
                       "  \$table->{$columnName}('{$type}')->nullable()->change();\n" .
                       "  // OR\n" .
                       "  \$table->{$columnName}('{$type}')->default(0)->change();";
                
            case 'decimal':
            case 'float':
            case 'double':
                return "For decimal fields, set a default value:\n" .
                       "  \$table->{$columnName}('{$type}', 8, 2)->default(0.00)->change();";
                
            case 'date':
                if (str_contains($columnName, 'start') || str_contains($columnName, 'begin')) {
                    return "For start dates, consider making nullable:\n" .
                           "  \$table->{$columnName}('{$type}')->nullable()->change();";
                }
                return "For date fields, consider making nullable:\n" .
                       "  \$table->{$columnName}('{$type}')->nullable()->change();";
                
            case 'datetime':
            case 'timestamp':
                if (str_contains($columnName, 'created') || str_contains($columnName, 'updated')) {
                    return "For audit timestamps, use Laravel's timestamps():\n" .
                           "  // These are usually handled by \$table->timestamps();";
                }
                return "For datetime fields, consider making nullable:\n" .
                       "  \$table->{$columnName}('{$type}')->nullable()->change();";
                
            case 'json':
                return "For JSON fields, consider making nullable or providing empty JSON:\n" .
                       "  \$table->{$columnName}('{$type}')->nullable()->change();\n" .
                       "  // OR\n" .
                       "  \$table->{$columnName}('{$type}')->default('{}')->change();";
                
            default:
                return "Consider making this field nullable:\n" .
                       "  \$table->{$columnName}('{$type}')->nullable()->change();\n" .
                       "  // OR provide an appropriate default value based on your business logic";
        }
    }

    private function suggestFix(array $comparison): void
    {
        if (!$comparison['has_changes']) {
            return;
        }
        $this->logger->log("\nSuggested fix for {$comparison['model_class']} (run with --fix to apply):");
        $fillableArray = "    protected \$fillable = [\n";
        foreach ($comparison['corrected_fields'] as $field) {
            $fillableArray .= "        '$field',\n";
        }
        $fillableArray .= "    ];";
        $this->logger->log($fillableArray);
    }
}