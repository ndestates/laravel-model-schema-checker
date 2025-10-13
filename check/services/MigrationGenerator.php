<?php

namespace Check\Services;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;

class MigrationGenerator
{
    protected CheckConfig $config;
    protected Logger $logger;
    protected DatabaseAnalyzer $dbAnalyzer;

    public function __construct(CheckConfig $config, Logger $logger, DatabaseAnalyzer $dbAnalyzer)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->dbAnalyzer = $dbAnalyzer;
    }

    /**
     * Generate a complete Laravel migration for creating a missing table
     */
    public function generateCreateTableMigration(string $tableName, array $modelFields, array $relationships = []): string
    {
        $className = 'Create' . Str::studly($tableName) . 'Table';

        $migrationContent = [];
        $migrationContent[] = "        Schema::create('$tableName', function (Blueprint \$table) {";
        $migrationContent[] = "            \$table->id();";

        // Add columns based on model fields
        foreach ($modelFields as $field) {
            // Skip excluded fields
            if (in_array($field, $this->config->getExcludedFields())) {
                continue;
            }

            // Infer data type (simplified - could be enhanced)
            $migrationContent[] = "            \$table->string('$field')->nullable();";
        }

        // Add timestamps
        $migrationContent[] = "            \$table->timestamps();";

        // Add foreign keys based on relationships
        foreach ($relationships as $relationship) {
            if (isset($relationship['foreign_key'])) {
                $foreignTable = $relationship['table'];
                $foreignKey = $relationship['foreign_key'];
                $migrationContent[] = "            \$table->foreignId('$foreignKey')->constrained('$foreignTable')->onDelete('cascade');";
            }
        }

        $migrationContent[] = "        });";

        return $this->buildMigrationTemplate($className, $migrationContent, $tableName);
    }

    /**
     * Generate a Laravel migration for syncing existing table columns
     */
    public function generateSyncTableMigration(string $tableName, array $missingFields, array $extraFields): ?string
    {
        if (empty($missingFields) && empty($extraFields)) {
            return null;
        }

        $className = 'Update' . Str::studly($tableName) . 'Table';

        $migrationContent = [];
        $migrationContent[] = "        Schema::table('$tableName', function (Blueprint \$table) {";

        // Add missing columns
        foreach ($missingFields as $field) {
            $migrationContent[] = "            \$table->string('$field')->nullable();";
        }

        // Remove extra columns (optional - only if explicitly requested)
        if ($this->config->shouldRemoveColumns()) {
            foreach ($extraFields as $field) {
                $migrationContent[] = "            \$table->dropColumn('$field');";
            }
        }

        $migrationContent[] = "        });";

        $downContent = [];
        $downContent[] = "        Schema::table('$tableName', function (Blueprint \$table) {";

        // Reverse operations for down migration
        if ($this->config->shouldRemoveColumns()) {
            foreach ($extraFields as $field) {
                $downContent[] = "            \$table->string('$field')->nullable();";
            }
        }

        foreach ($missingFields as $field) {
            $downContent[] = "            \$table->dropColumn('$field');";
        }

        $downContent[] = "        });";

        return $this->buildMigrationTemplate($className, $migrationContent, $tableName, $downContent);
    }

    /**
     * Generate an enhanced migration based on existing database schema
     */
    public function generateEnhancedMigration(string $tableName): ?string
    {
        if (!$this->dbAnalyzer->tableExists($tableName)) {
            return null;
        }

        $columns = $this->dbAnalyzer->getTableColumns($tableName);
        if (empty($columns)) {
            return null;
        }

        $className = 'Create' . Str::studly($tableName) . 'Table';

        $migrationContent = [];
        $migrationContent[] = "        Schema::create('$tableName', function (Blueprint \$table) {";

        // Generate column definitions from database schema
        foreach ($columns as $column) {
            $columnName = $column['name'];
            if (in_array($columnName, $this->config->getExcludedFields())) {
                continue;
            }

            $columnDef = $this->generateColumnDefinition($columnName, $column);
            if ($columnDef) {
                $migrationContent[] = "            $columnDef";
            }
        }

        // Add timestamps if they exist
        $hasCreatedAt = collect($columns)->pluck('name')->contains('created_at');
        $hasUpdatedAt = collect($columns)->pluck('name')->contains('updated_at');

        if ($hasCreatedAt && $hasUpdatedAt) {
            $migrationContent[] = "            \$table->timestamps();";
        }

        $migrationContent[] = "        });";

        return $this->buildMigrationTemplate($className, $migrationContent, $tableName);
    }

    /**
     * Generate a column definition from database column info
     */
    protected function generateColumnDefinition(string $columnName, array $columnInfo): ?string
    {
        $type = strtolower($columnInfo['type'] ?? 'varchar');
        $nullable = $columnInfo['nullable'] ?? false;
        $default = $columnInfo['default'] ?? null;
        $autoIncrement = $columnInfo['auto_increment'] ?? false;

        // Handle primary key
        if ($columnName === 'id' && $autoIncrement) {
            return "\$table->id();";
        }

        // Map database types to Laravel migration methods
        $typeMap = [
            'bigint' => $autoIncrement ? 'id' : 'bigInteger',
            'int' => 'integer',
            'tinyint' => ($columnInfo['length'] ?? 0) == 1 ? 'boolean' : 'tinyInteger',
            'smallint' => 'smallInteger',
            'mediumint' => 'mediumInteger',
            'varchar' => 'string',
            'char' => 'char',
            'text' => 'text',
            'longtext' => 'longText',
            'mediumtext' => 'mediumText',
            'json' => 'json',
            'decimal' => 'decimal',
            'float' => 'float',
            'double' => 'double',
            'date' => 'date',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
        ];

        $method = $typeMap[$type] ?? 'string';
        $params = [];

        // Add length parameter for string types
        if (in_array($method, ['string', 'char']) && isset($columnInfo['length'])) {
            $params[] = $columnInfo['length'];
        }

        // Add precision/scale for decimal
        if ($method === 'decimal' && isset($columnInfo['precision'])) {
            $params[] = $columnInfo['precision'];
            if (isset($columnInfo['scale'])) {
                $params[] = $columnInfo['scale'];
            }
        }

        // Build the column definition
        $paramString = empty($params) ? "'$columnName'" : "'$columnName', " . implode(', ', $params);
        $columnDef = "\$table->$method($paramString)";

        // Add nullable
        if ($nullable && $columnName !== 'id') {
            $columnDef .= "->nullable()";
        }

        // Add default value
        if ($default !== null) {
            if (is_string($default)) {
                $columnDef .= "->default('$default')";
            } elseif (is_numeric($default)) {
                $columnDef .= "->default($default)";
            }
        }

        return $columnDef . ";";
    }

    /**
     * Build the complete migration template
     */
    protected function buildMigrationTemplate(string $className, array $upContent, string $tableName, array $downContent = null): string
    {
        if ($downContent === null) {
            $downContent = ["        Schema::dropIfExists('$tableName');"];
        }

        return "<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
" . implode("\n", $upContent) . "
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
" . implode("\n", $downContent) . "
    }
};
";
    }

    /**
     * Save migration file to disk
     */
    public function saveMigration(string $migrationContent, string $tableName, string $timestamp): string
    {
        $fileName = $timestamp . '_create_' . Str::snake($tableName) . '_table.php';
        $filePath = database_path('migrations') . '/' . $fileName;

        if (file_put_contents($filePath, $migrationContent)) {
            $this->logger->success("Generated migration for '$tableName' at $filePath");
            return $filePath;
        } else {
            $this->logger->error("Failed to create migration file for '$tableName'");
            return '';
        }
    }
}