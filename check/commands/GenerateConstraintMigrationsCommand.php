<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Check\Services\ModelAnalyzer;
use Check\Services\DatabaseAnalyzer;
use Check\Services\CommandRunner;

class GenerateConstraintMigrationsCommand
{
    protected CheckConfig $config;
    protected Logger $logger;
    protected ModelAnalyzer $modelAnalyzer;
    protected DatabaseAnalyzer $dbAnalyzer;
    protected CommandRunner $commandRunner;

    public function __construct(
        CheckConfig $config,
        Logger $logger,
        ModelAnalyzer $modelAnalyzer,
        DatabaseAnalyzer $dbAnalyzer,
        CommandRunner $commandRunner
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->modelAnalyzer = $modelAnalyzer;
        $this->dbAnalyzer = $dbAnalyzer;
        $this->commandRunner = $commandRunner;
    }

    public function execute(array $flags): void
    {
        $this->logger->section("GENERATING CONSTRAINT FIX MIGRATIONS");

        $modelClasses = $this->modelAnalyzer->getAllModelClasses();
        $constraintIssues = [];
        $generatedMigrations = [];

        // First pass: collect all constraint issues
        foreach ($modelClasses as $modelClass => $filePath) {
            $tableName = $this->modelAnalyzer->getTableName($modelClass);
            $dbSchema = $this->dbAnalyzer->getTableSchema($tableName);

            if (empty($dbSchema)) {
                continue;
            }

            $issues = $this->analyzeConstraints($dbSchema, $tableName);
            if (!empty($issues)) {
                $constraintIssues[$tableName] = $issues;
            }
        }

        if (empty($constraintIssues)) {
            $this->logger->info("No constraint violations found that need fixing.");
            return;
        }

        // Generate migration files
        foreach ($constraintIssues as $tableName => $issues) {
            $migrationName = "fix_{$tableName}_constraint_violations";
            $migrationFile = $this->generateMigrationFile($tableName, $issues, $migrationName);

            if ($migrationFile) {
                $generatedMigrations[] = $migrationFile;
                $this->logger->info("Generated migration: {$migrationFile}");
            }
        }

        $this->logger->section("MIGRATION SUMMARY");
        $this->logger->info("Generated " . count($generatedMigrations) . " constraint fix migrations");

        if (!empty($generatedMigrations)) {
            $this->logger->info("Review and run the generated migrations:");
            foreach ($generatedMigrations as $migration) {
                $this->logger->log("  php artisan migrate --path=database/migrations/" . basename($migration));
            }
        }
    }

    private function analyzeConstraints(array $dbSchema, string $tableName): array
    {
        $issues = [];
        $excludedFields = $this->config->getExcludedFields();
        $ignoreIdColumns = $this->config->shouldIgnoreIdColumnsInConstraintCheck();
        
        // Comprehensive constraint analysis
        $constraints = $this->analyzeTableConstraints($tableName);
        
        $this->logger->info("Analyzing constraints for table: $tableName");
        $this->logger->info("Excluded fields: " . implode(', ', $constraints['excluded_columns']));
        $this->logger->info("Foreign key columns: " . implode(', ', $constraints['foreign_key_columns']));
        $this->logger->info("Referenced columns: " . implode(', ', $constraints['referenced_columns']));
        $this->logger->info("Unique index columns: " . implode(', ', $constraints['unique_index_columns']));
        $this->logger->info("Primary key columns: " . implode(', ', $constraints['primary_key_columns']));

        foreach ($dbSchema as $columnName => $column) {
            if (in_array($columnName, $excludedFields)) {
                continue;
            }

            if ($ignoreIdColumns && substr($columnName, -3) === '_id') {
                continue;
            }

            // Skip columns that have any constraints that prevent making them nullable
            if (in_array($columnName, $constraints['excluded_columns'])) {
                continue;
            }

            if (!$column['nullable'] && is_null($column['default'])) {
                $issues[] = [
                    'column' => $columnName,
                    'type' => $column['type'],
                    'fix_type' => $this->determineFixType($columnName, $column)
                ];
            }
        }

        return $issues;
    }
    
    private function analyzeTableConstraints(string $tableName): array
    {
        $foreignKeyColumns = $this->dbAnalyzer->getForeignKeyColumns($tableName);
        $referencedColumns = $this->dbAnalyzer->getReferencedColumns($tableName);
        $indexes = $this->dbAnalyzer->getTableIndexes($tableName);
        $foreignKeys = $this->dbAnalyzer->getTableForeignKeys($tableName);
        
        $uniqueIndexColumns = [];
        $primaryKeyColumns = [];
        
        // Analyze indexes for unique constraints and primary keys
        foreach ($indexes as $index) {
            if ($index['unique']) {
                $uniqueIndexColumns = array_merge($uniqueIndexColumns, $index['columns']);
            }
            if ($index['primary']) {
                $primaryKeyColumns = array_merge($primaryKeyColumns, $index['columns']);
            }
        }
        
        // Remove duplicates
        $uniqueIndexColumns = array_unique($uniqueIndexColumns);
        $primaryKeyColumns = array_unique($primaryKeyColumns);
        
        // All columns that cannot be made nullable
        $excludedColumns = array_unique(array_merge(
            $foreignKeyColumns,
            $referencedColumns,
            $uniqueIndexColumns,
            $primaryKeyColumns
        ));
        
        return [
            'foreign_key_columns' => $foreignKeyColumns,
            'referenced_columns' => $referencedColumns,
            'unique_index_columns' => $uniqueIndexColumns,
            'primary_key_columns' => $primaryKeyColumns,
            'excluded_columns' => $excludedColumns,
        ];
    }

    private function determineFixType(string $columnName, array $column): string
    {
        $type = strtolower($column['type']);
        $isForeignKey = substr($columnName, -3) === '_id';

        // Foreign keys: make nullable
        if ($isForeignKey) {
            return 'nullable';
        }

        // Type-specific fixes
        switch ($type) {
            case 'varchar':
            case 'string':
            case 'text':
            case 'longtext':
                if (str_contains($columnName, 'name') || str_contains($columnName, 'title')) {
                    return 'default_empty_string';
                }
                if (str_contains($columnName, 'status') || str_contains($columnName, 'type') || str_contains($columnName, 'state')) {
                    return 'default_active';
                }
                return 'nullable';

            case 'boolean':
            case 'tinyint':
                return 'default_false';

            case 'integer':
            case 'int':
            case 'bigint':
                if (str_contains($columnName, 'count') || str_contains($columnName, 'amount') || str_contains($columnName, 'quantity')) {
                    return 'default_zero';
                }
                return 'nullable';

            case 'decimal':
            case 'float':
            case 'double':
                return 'default_zero_decimal';

            case 'date':
            case 'datetime':
            case 'timestamp':
                return 'nullable';

            case 'json':
                return 'nullable';

            default:
                return 'nullable';
        }
    }

    private function generateMigrationFile(string $tableName, array $issues, string $migrationName): ?string
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$migrationName}.php";
        $filepath = database_path("migrations/{$filename}");

        $upCode = $this->generateUpCode($tableName, $issues);
        $downCode = $this->generateDownCode($tableName, $issues);

        $migrationContent = "<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix constraint violations for {$tableName} table.
     * Generated by schema checker --generate-constraint-migrations
     */
    public function up(): void
    {
{$upCode}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downCode}
    }
};
";

        if (file_put_contents($filepath, $migrationContent)) {
            return $filepath;
        }

        return null;
    }

    private function generateUpCode(string $tableName, array $issues): string
    {
        $code = "        Schema::table('{$tableName}', function (Blueprint \$table) {\n";

        foreach ($issues as $issue) {
            $column = $issue['column'];
            $type = $issue['type'];
            $fixType = $issue['fix_type'];

            // Handle enum columns specially
            if (str_starts_with(strtolower($type), 'enum(')) {
                $enumValues = $this->parseEnumValues($type);
                $code .= "            \$table->enum('{$column}', " . $this->formatEnumArray($enumValues) . ")->nullable()->change();\n";
            } else {
                // Map database types to Laravel column methods
                $laravelMethod = $this->mapDatabaseTypeToLaravelMethod($type);
                $code .= "            \$table->{$laravelMethod}('{$column}')->nullable()->change();\n";
            }
        }

        $code .= "        });";
        return $code;
    }

    private function mapDatabaseTypeToLaravelMethod(string $dbType): string
    {
        $type = strtolower($dbType);

        // Handle common MySQL types
        if (str_contains($type, 'varchar') || str_contains($type, 'char')) {
            return 'string';
        }
        if (str_contains($type, 'text') || str_contains($type, 'longtext')) {
            return 'text';
        }
        if (str_contains($type, 'int') || str_contains($type, 'bigint')) {
            return 'integer';
        }
        if (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
            return 'decimal';
        }
        if ($type === 'date') {
            return 'date';
        }
        if (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) {
            return 'timestamp';
        }
        if ($type === 'boolean' || $type === 'tinyint(1)') {
            return 'boolean';
        }
        if ($type === 'json') {
            return 'json';
        }

        // Default fallback
        return 'string';
    }

    private function formatEnumArray(array $values): string
    {
        return "['" . implode("', '", $values) . "']";
    }

    private function parseEnumValues(string $enumType): array
    {
        // Extract values from enum('value1','value2','value3')
        if (preg_match("/enum\((.*)\)/i", $enumType, $matches)) {
            $valuesString = $matches[1];
            // Split by comma and clean up quotes
            $values = array_map(function($value) {
                return trim($value, "'\"");
            }, explode(',', $valuesString));
            return $values;
        }
        return [];
    }

    private function generateDownCode(string $tableName, array $issues): string
    {
        $code = "        Schema::table('{$tableName}', function (Blueprint \$table) {\n";

        foreach ($issues as $issue) {
            $column = $issue['column'];
            $type = $issue['type'];

            // Handle enum columns specially
            if (str_starts_with(strtolower($type), 'enum(')) {
                $enumValues = $this->parseEnumValues($type);
                $code .= "            \$table->enum('{$column}', " . $this->formatEnumArray($enumValues) . ")->nullable(false)->change();\n";
            } else {
                // Map database types to Laravel column methods
                $laravelMethod = $this->mapDatabaseTypeToLaravelMethod($type);
                $code .= "            \$table->{$laravelMethod}('{$column}')->nullable(false)->change();\n";
            }
        }

        $code .= "        });";
        return $code;
    }
}