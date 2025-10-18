<?php

namespace NDEstates\LaravelModelSchemaChecker\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MigrationGenerator
{
    protected array $existingMigrations = [];
    protected array $databaseTables = [];
    protected array $generatedMigrations = [];

    public function __construct()
    {
        $this->loadExistingMigrations();
        $this->loadDatabaseSchema();
    }

    /**
     * Generate fresh migrations from current database schema
     */
    public function generateMigrationsFromSchema(): array
    {
        $this->generatedMigrations = [];

        foreach ($this->databaseTables as $tableName => $tableInfo) {
            if ($this->shouldGenerateMigration($tableName)) {
                $this->generateTableMigration($tableName, $tableInfo);
            }
        }

        return $this->generatedMigrations;
    }

    /**
     * Check if a migration should be generated for this table
     */
    protected function shouldGenerateMigration(string $tableName): bool
    {
        // Skip Laravel's default tables
        $skipTables = ['migrations', 'failed_jobs', 'password_resets', 'personal_access_tokens'];

        if (in_array($tableName, $skipTables)) {
            return false;
        }

        // Check if migration already exists
        $migrationName = "create_{$tableName}_table";
        return !isset($this->existingMigrations[$migrationName]);
    }

    /**
     * Generate migration for a specific table
     */
    protected function generateTableMigration(string $tableName, array $tableInfo): void
    {
        $migrationName = "create_{$tableName}_table";
        $timestamp = Carbon::now()->format('Y_m_d_His');
        $filename = "{$timestamp}_{$migrationName}.php";

        $migrationContent = $this->buildMigrationContent($tableName, $tableInfo);

        $this->generatedMigrations[] = [
            'filename' => $filename,
            'content' => $migrationContent,
            'table' => $tableName
        ];
    }

    /**
     * Build the migration content for a table
     */
    protected function buildMigrationContent(string $tableName, array $tableInfo): string
    {
        $className = Str::studly($tableName) . 'Table';

        $upMethod = $this->buildUpMethod($tableName, $tableInfo);
        $downMethod = $this->buildDownMethod($tableName);

        return "<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
{$upMethod}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downMethod}
    }
};";
    }

    /**
     * Build the up() method content
     */
    protected function buildUpMethod(string $tableName, array $tableInfo): string
    {
        $content = "        Schema::create('{$tableName}', function (Blueprint \$table) {\n";

        // Add columns
        if (isset($tableInfo['columns'])) {
            foreach ($tableInfo['columns'] as $column) {
                $content .= $this->buildColumnDefinition($column);
            }
        }

        // Add indexes
        if (isset($tableInfo['indexes'])) {
            foreach ($tableInfo['indexes'] as $index) {
                $content .= $this->buildIndexDefinition($index);
            }
        }

        // Add foreign keys
        if (isset($tableInfo['foreign_keys'])) {
            foreach ($tableInfo['foreign_keys'] as $foreignKey) {
                $content .= $this->buildForeignKeyDefinition($foreignKey);
            }
        }

        $content .= "        });";

        return $content;
    }

    /**
     * Build the down() method content
     */
    protected function buildDownMethod(string $tableName): string
    {
        return "        Schema::dropIfExists('{$tableName}');";
    }

    /**
     * Build column definition
     */
    protected function buildColumnDefinition(array $column): string
    {
        $name = $column['name'];
        $type = $column['type'];
        $nullable = $column['nullable'] ? '->nullable()' : '';
        $default = isset($column['default']) ? "->default('{$column['default']}')" : '';
        $length = isset($column['length']) ? "({$column['length']})" : '';

        // Handle different column types
        switch ($type) {
            case 'bigint':
            case 'int':
            case 'integer':
                if ($column['auto_increment']) {
                    return "            \$table->id('{$name}');\n";
                }
                return "            \$table->integer('{$name}'){$nullable}{$default};\n";

            case 'varchar':
            case 'string':
                $length = $length ?: '(255)';
                return "            \$table->string('{$name}'{$length}){$nullable}{$default};\n";

            case 'text':
                return "            \$table->text('{$name}'){$nullable};\n";

            case 'longtext':
                return "            \$table->longText('{$name}'){$nullable};\n";

            case 'timestamp':
                if ($name === 'created_at') {
                    return '';
                }
                if ($name === 'updated_at') {
                    return '';
                }
                return "            \$table->timestamp('{$name}'){$nullable}{$default};\n";

            case 'datetime':
                return "            \$table->dateTime('{$name}'){$nullable}{$default};\n";

            case 'date':
                return "            \$table->date('{$name}'){$nullable}{$default};\n";

            case 'decimal':
            case 'float':
            case 'double':
                return "            \$table->decimal('{$name}', 8, 2){$nullable}{$default};\n";

            case 'boolean':
            case 'tinyint':
                if ($column['length'] == 1) {
                    return "            \$table->boolean('{$name}'){$nullable}{$default};\n";
                }
                return "            \$table->tinyInteger('{$name}'){$nullable}{$default};\n";

            default:
                return "            \$table->string('{$name}'){$nullable}{$default}; // Unknown type: {$type}\n";
        }
    }

    /**
     * Build index definition
     */
    protected function buildIndexDefinition(array $index): string
    {
        $columns = is_array($index['columns']) ? $index['columns'] : [$index['columns']];
        $columnsStr = "'" . implode("', '", $columns) . "'";

        if ($index['unique']) {
            return "            \$table->unique([{$columnsStr}]);\n";
        }

        return "            \$table->index([{$columnsStr}]);\n";
    }

    /**
     * Build foreign key definition
     */
    protected function buildForeignKeyDefinition(array $foreignKey): string
    {
        $localColumn = $foreignKey['local_column'];
        $foreignTable = $foreignKey['foreign_table'];
        $foreignColumn = $foreignKey['foreign_column'];

        return "            \$table->foreign('{$localColumn}')->references('{$foreignColumn}')->on('{$foreignTable}');\n";
    }

    /**
     * Load existing migrations
     */
    protected function loadExistingMigrations(): void
    {
        $migrationPath = database_path('migrations');

        if (!File::exists($migrationPath)) {
            return;
        }

        $migrationFiles = File::allFiles($migrationPath);

        foreach ($migrationFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = File::get($file->getPathname());
                $this->parseMigrationFile($file->getFilename(), $content);
            }
        }
    }

    /**
     * Parse migration file to extract table information
     */
    protected function parseMigrationFile(string $filename, string $content): void
    {
        // Extract migration name from filename
        // Format: YYYY_MM_DD_HHMMSS_create_table_name_table.php
        $pattern = '/\d{4}_\d{2}_\d{2}_\d{6}_(create_\w+_table)\.php/';
        if (preg_match($pattern, $filename, $matches)) {
            $migrationName = $matches[1];
            $this->existingMigrations[$migrationName] = true;
        }
    }

    /**
     * Load database schema information
     */
    protected function loadDatabaseSchema(): void
    {
        $this->databaseTables = [];

        // Get all tables
        $tables = DB::select('SHOW TABLES');
        $databaseName = config('database.connections.mysql.database');

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_{$databaseName}"};
            $this->databaseTables[$tableName] = $this->analyzeTable($tableName);
        }
    }

    /**
     * Analyze a specific table
     */
    protected function analyzeTable(string $tableName): array
    {
        $tableInfo = [
            'columns' => [],
            'indexes' => [],
            'foreign_keys' => []
        ];

        // Get columns
        $columns = DB::select("DESCRIBE `{$tableName}`");
        foreach ($columns as $column) {
            $tableInfo['columns'][] = [
                'name' => $column->Field,
                'type' => $this->parseColumnType($column->Type),
                'nullable' => $column->Null === 'YES',
                'default' => $column->Default,
                'auto_increment' => strpos($column->Extra, 'auto_increment') !== false,
                'length' => $this->extractLength($column->Type)
            ];
        }

        // Get indexes
        $indexes = DB::select("SHOW INDEX FROM `{$tableName}`");
        $indexGroups = [];
        foreach ($indexes as $index) {
            $keyName = $index->Key_name;
            if ($keyName === 'PRIMARY') continue;

            if (!isset($indexGroups[$keyName])) {
                $indexGroups[$keyName] = [
                    'columns' => [],
                    'unique' => !$index->Non_unique
                ];
            }
            $indexGroups[$keyName]['columns'][] = $index->Column_name;
        }

        foreach ($indexGroups as $index) {
            $tableInfo['indexes'][] = $index;
        }

        // Get foreign keys
        $foreignKeys = DB::select("
            SELECT
                COLUMN_NAME as local_column,
                REFERENCED_TABLE_NAME as foreign_table,
                REFERENCED_COLUMN_NAME as foreign_column
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [config('database.connections.mysql.database'), $tableName]);

        foreach ($foreignKeys as $fk) {
            $tableInfo['foreign_keys'][] = [
                'local_column' => $fk->local_column,
                'foreign_table' => $fk->foreign_table,
                'foreign_column' => $fk->foreign_column
            ];
        }

        return $tableInfo;
    }

    /**
     * Parse MySQL column type
     */
    protected function parseColumnType(string $type): string
    {
        // Extract base type from type definition
        if (preg_match('/^(\w+)/', $type, $matches)) {
            return $matches[1];
        }
        return $type;
    }

    /**
     * Extract length from column type
     */
    protected function extractLength(string $type): ?int
    {
        if (preg_match('/\((\d+)\)/', $type, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Save generated migrations to disk
     */
    public function saveMigrations(string $path = null): array
    {
        $path = $path ?: database_path('migrations');
        $savedFiles = [];

        foreach ($this->generatedMigrations as $migration) {
            $filePath = $path . '/' . $migration['filename'];
            File::put($filePath, $migration['content']);
            $savedFiles[] = $filePath;
        }

        return $savedFiles;
    }

    /**
     * Get generated migrations without saving
     */
    public function getGeneratedMigrations(): array
    {
        return $this->generatedMigrations;
    }

    /**
     * Preview what migrations would be generated
     */
    public function previewMigrations(): array
    {
        return $this->generateMigrationsFromSchema();
    }
}