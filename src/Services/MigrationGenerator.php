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

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        // Get all tables based on database driver
        if ($driver === 'sqlite') {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $table) {
                $tableName = $table->name;
                $this->databaseTables[$tableName] = $this->analyzeTable($tableName, $driver);
            }
        } elseif ($driver === 'mysql') {
            $tables = DB::select('SHOW TABLES');
            $databaseName = config("database.connections.{$connection}.database");

            foreach ($tables as $table) {
                $tableName = $table->{"Tables_in_{$databaseName}"};
                $this->databaseTables[$tableName] = $this->analyzeTable($tableName, $driver);
            }
        } elseif ($driver === 'pgsql') {
            $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            foreach ($tables as $table) {
                $tableName = $table->tablename;
                $this->databaseTables[$tableName] = $this->analyzeTable($tableName, $driver);
            }
        }
    }

    /**
     * Analyze a specific table
     */
    protected function analyzeTable(string $tableName, string $driver): array
    {
        $tableInfo = [
            'columns' => [],
            'indexes' => [],
            'foreign_keys' => []
        ];

        // Get columns based on database driver
        if ($driver === 'sqlite') {
            $columns = DB::select("PRAGMA table_info({$tableName})");
            foreach ($columns as $column) {
                $tableInfo['columns'][] = [
                    'name' => $column->name,
                    'type' => $this->parseColumnType($column->type),
                    'nullable' => !$column->notnull,
                    'default' => $column->dflt_value,
                    'auto_increment' => $column->pk && strpos(strtolower($column->type), 'integer') !== false,
                    'length' => $this->extractLength($column->type)
                ];
            }
        } elseif ($driver === 'mysql') {
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
        } elseif ($driver === 'pgsql') {
            $columns = DB::select("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public' ORDER BY ordinal_position", [$tableName]);
            foreach ($columns as $column) {
                $tableInfo['columns'][] = [
                    'name' => $column->column_name,
                    'type' => $this->parseColumnType($column->data_type),
                    'nullable' => $column->is_nullable === 'YES',
                    'default' => $column->column_default,
                    'auto_increment' => false, // PostgreSQL handles this differently
                    'length' => null
                ];
            }
        }

        // Get indexes based on database driver
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list({$tableName})");
            foreach ($indexes as $index) {
                if (!$index->unique) { // Skip unique indexes for now
                    $indexInfo = DB::select("PRAGMA index_info({$index->name})");
                    $columns = array_column($indexInfo, 'name');
                    $tableInfo['indexes'][] = [
                        'columns' => $columns,
                        'unique' => false
                    ];
                }
            }
        } elseif ($driver === 'mysql') {
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
        }

        // Foreign keys are more complex and database-specific
        // For now, we'll skip them in the DDEV test
        $tableInfo['foreign_keys'] = [];

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

    /**
     * Generate alter migrations to fix detected issues
     */
    public function generateAlterMigrations(array $issues): array
    {
        $alterMigrations = [];

        foreach ($issues as $issue) {
            if ($this->canGenerateAlterMigration($issue)) {
                $migration = $this->generateAlterMigrationForIssue($issue);
                if ($migration) {
                    $alterMigrations[] = $migration;
                }
            }
        }

        return $alterMigrations;
    }

    /**
     * Check if an alter migration can be generated for this issue
     */
    protected function canGenerateAlterMigration(array $issue): bool
    {
        $supportedTypes = [
            'string_without_length',
            'missing_timestamps',
            'missing_foreign_key_index',
            'nullable_foreign_key_no_default'
        ];

        return ($issue['category'] ?? '') === 'migration' && in_array($issue['type'] ?? '', $supportedTypes);
    }

    /**
     * Generate an alter migration for a specific issue
     */
    protected function generateAlterMigrationForIssue(array $issue): ?array
    {
        $tableName = $this->extractTableNameFromIssue($issue);
        if (!$tableName) {
            return null;
        }

        $migrationName = $this->generateAlterMigrationName($issue, $tableName);
        $upCode = $this->generateAlterUpCode($issue, $tableName);
        $downCode = $this->generateAlterDownCode($issue, $tableName);

        if (!$upCode) {
            return null;
        }

        return [
            'name' => $migrationName,
            'content' => $this->generateAlterMigrationContent($migrationName, $upCode, $downCode),
            'table' => $tableName,
            'issue_type' => $issue['type']
        ];
    }

    /**
     * Extract table name from issue data
     */
    protected function extractTableNameFromIssue(array $issue): ?string
    {
        // Try different ways to extract table name from issue
        if (isset($issue['table'])) {
            return $issue['table'];
        }

        if (isset($issue['file'])) {
            // Extract from migration filename
            $filename = basename($issue['file']);
            if (preg_match('/create_(\w+)_table/', $filename, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Generate migration name for alter migration
     */
    protected function generateAlterMigrationName(array $issue, string $tableName): string
    {
        $timestamp = date('Y_m_d_His');
        $action = $this->getAlterActionName($issue['type']);

        return "{$timestamp}_alter_{$tableName}_table_{$action}";
    }

    /**
     * Get action name for alter migration
     */
    protected function getAlterActionName(string $issueType): string
    {
        $actions = [
            'string_without_length' => 'add_string_lengths',
            'missing_timestamps' => 'add_timestamps',
            'missing_foreign_key_index' => 'add_foreign_key_indexes',
            'nullable_foreign_key_no_default' => 'fix_foreign_keys'
        ];

        return $actions[$issueType] ?? 'fix_schema_issues';
    }

    /**
     * Generate the up() code for alter migration
     */
    protected function generateAlterUpCode(array $issue, string $tableName): ?string
    {
        switch ($issue['type']) {
            case 'string_without_length':
                return $this->generateStringLengthFix($tableName, $issue);
            case 'missing_timestamps':
                return $this->generateTimestampsFix($tableName);
            case 'missing_foreign_key_index':
                return $this->generateForeignKeyIndexFix($tableName, $issue);
            case 'nullable_foreign_key_no_default':
                return $this->generateForeignKeyNullableFix($tableName, $issue);
            default:
                return null;
        }
    }

    /**
     * Generate the down() code for alter migration
     */
    protected function generateAlterDownCode(array $issue, string $tableName): ?string
    {
        // For safety, most alter migrations will have empty down() methods
        // Users should manually write down() methods if needed
        return "// Reverse migration not automatically generated for safety\n        // Please implement manually if needed";
    }

    /**
     * Generate string length fix code
     */
    protected function generateStringLengthFix(string $tableName, array $issue): string
    {
        // This is a simplified example - in practice, we'd need to parse the migration file
        // to determine which columns need length specifications
        return "\$table->string('name', 255)->change();\n        \$table->string('email', 255)->change();";
    }

    /**
     * Generate timestamps fix code
     */
    protected function generateTimestampsFix(string $tableName): string
    {
        return "\$table->timestamps();";
    }

    /**
     * Generate foreign key index fix code
     */
    protected function generateForeignKeyIndexFix(string $tableName, array $issue): string
    {
        $column = $issue['column'] ?? 'unknown_column';
        return "\$table->index('{$column}');";
    }

    /**
     * Generate foreign key nullable fix code
     */
    protected function generateForeignKeyNullableFix(string $tableName, array $issue): string
    {
        $column = $issue['column'] ?? 'unknown_column';
        return "\$table->foreignId('{$column}')->nullable()->constrained()->change();";
    }

    /**
     * Generate the complete migration content
     */
    protected function generateAlterMigrationContent(string $migrationName, string $upCode, string $downCode): string
    {
        $className = Str::studly(str_replace(['_', '.php'], [' ', ''], $migrationName));

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
        Schema::table('{$this->extractTableFromMigrationName($migrationName)}', function (Blueprint \$table) {
            {$upCode}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{$this->extractTableFromMigrationName($migrationName)}', function (Blueprint \$table) {
            {$downCode}
        });
    }
};";
    }

    /**
     * Extract table name from migration name
     */
    protected function extractTableFromMigrationName(string $migrationName): string
    {
        if (preg_match('/alter_(\w+)_table/', $migrationName, $matches)) {
            return $matches[1];
        }
        return 'unknown_table';
    }
}