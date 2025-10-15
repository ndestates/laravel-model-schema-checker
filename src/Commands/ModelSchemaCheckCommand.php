<?php

namespace NDEstates\LaravelModelSchemaChecker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelSchemaCheckCommand extends Command
{
    protected $signature = 'model:schema-check 
                            {--dry-run : Show what would be changed without making changes}
                            {--fix : Fix model fillable properties}
                            {--generate-migrations : Generate Laravel migrations}
                            {--json : Output results in JSON format}';

    protected $description = 'Check model fillable properties against database schema';

    protected array $config;
    protected array $issues = [];
    protected array $stats = [
        'models_checked' => 0,
        'issues_found' => 0,
        'fixes_applied' => 0,
    ];

    public function handle(): int
    {
        $this->config = config('model-schema-checker', [
            'models_dir' => app_path('Models'),
            'excluded_fields' => [
                'id', 'created_at', 'updated_at', 'created_by', 
                'updated_by', 'deleted_by', 'deleted_at',
                'email_verified_at', 'remember_token'
            ],
            'database_connection' => env('DB_CONNECTION', 'mysql'),
        ]);

        $this->info('Laravel Model Schema Checker');
        $this->info('================================');

        if ($this->option('dry-run')) {
            $this->warn('Running in DRY-RUN mode - no changes will be made');
        }

        $this->checkModels();
        $this->displayResults();

        if ($this->option('generate-migrations')) {
            $this->generateMigrations();
        }

        return Command::SUCCESS;
    }

    protected function checkModels(): void
    {
        $modelsPath = $this->config['models_dir'];

        if (!File::exists($modelsPath)) {
            $this->error("Models directory not found: {$modelsPath}");
            return;
        }

        $modelFiles = File::allFiles($modelsPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkModel($file);
            }
        }
    }

    protected function checkModel($file): void
    {
        $namespace = $this->getNamespaceFromFile($file->getPathname());
        $className = $namespace . '\\' . $file->getFilenameWithoutExtension();

        if (!class_exists($className)) {
            return;
        }

        try {
            $reflection = new ReflectionClass($className);
            
            if (!$reflection->isSubclassOf('Illuminate\Database\Eloquent\Model')) {
                return;
            }

            $model = app($className);
            $this->stats['models_checked']++;

            $this->info("Checking model: {$className}");

            $this->checkModelFillableProperties($model, $className);

        } catch (\Exception $e) {
            $this->warn("Could not check model {$className}: " . $e->getMessage());
        }
    }

    protected function getNamespaceFromFile($filePath): string
    {
        $content = File::get($filePath);
        
        if (preg_match('/namespace\s+([^;]+);/i', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return 'App\\Models';
    }

    protected function checkModelFillableProperties($model, string $className): void
    {
        $tableName = $model->getTable();
        
        if (!$this->tableExists($tableName)) {
            $this->addIssue($className, 'missing_table', [
                'table' => $tableName,
                'message' => "Table '{$tableName}' does not exist"
            ]);
            return;
        }

        $fillable = $model->getFillable();
        $tableColumns = $this->getTableColumns($tableName);
        $excludedFields = $this->config['excluded_fields'];

        // Remove excluded fields from table columns for comparison
        $relevantColumns = array_diff($tableColumns, $excludedFields);

        // Check for fillable properties not in database
        $extraFillable = array_diff($fillable, $tableColumns);
        if (!empty($extraFillable)) {
            $this->addIssue($className, 'extra_fillable', [
                'fields' => $extraFillable,
                'message' => 'Fillable properties not found in database table'
            ]);
        }

        // Check for database columns not in fillable
        $missingFillable = array_diff($relevantColumns, $fillable);
        if (!empty($missingFillable)) {
            $this->addIssue($className, 'missing_fillable', [
                'fields' => $missingFillable,
                'message' => 'Database columns not in fillable array'
            ]);
        }

        if ($this->option('fix') && !$this->option('dry-run')) {
            $this->fixModelFillable($className, $relevantColumns);
        }
    }

    protected function tableExists(string $tableName): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($tableName);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getTableColumns(string $tableName): array
    {
        try {
            return DB::getSchemaBuilder()->getColumnListing($tableName);
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function addIssue(string $model, string $type, array $data): void
    {
        $this->issues[] = [
            'model' => $model,
            'type' => $type,
            'data' => $data
        ];
        $this->stats['issues_found']++;
    }

    protected function fixModelFillable(string $className, array $columns): void
    {
        $reflection = new ReflectionClass($className);
        $filePath = $reflection->getFileName();
        
        if (!$filePath || !File::exists($filePath)) {
            $this->warn("Could not locate file for model: {$className}");
            return;
        }

        $content = File::get($filePath);
        
        // Create new fillable array
        $fillableString = $this->generateFillableString($columns);
        
        // Replace existing fillable array or add new one
        if (preg_match('/protected\s+\$fillable\s*=\s*\[[^\]]*\];/s', $content)) {
            $content = preg_replace(
                '/protected\s+\$fillable\s*=\s*\[[^\]]*\];/s',
                "protected \$fillable = {$fillableString};",
                $content
            );
        } else {
            // Add fillable array after class declaration
            $content = preg_replace(
                '/(class\s+\w+[^{]*{)/',
                "$1\n    protected \$fillable = {$fillableString};\n",
                $content
            );
        }

        File::put($filePath, $content);
        $this->info("Fixed fillable array for: {$className}");
        $this->stats['fixes_applied']++;
    }

    protected function generateFillableString(array $columns): string
    {
        $excludedFields = $this->config['excluded_fields'];
        $fillableColumns = array_diff($columns, $excludedFields);
        
        $formatted = array_map(function ($column) {
            return "        '{$column}'";
        }, $fillableColumns);
        
        return "[\n" . implode(",\n", $formatted) . "\n    ]";
    }

    protected function displayResults(): void
    {
        $this->info('');
        $this->info('Results:');
        $this->info('========');
        $this->info("Models checked: {$this->stats['models_checked']}");
        $this->info("Issues found: {$this->stats['issues_found']}");
        
        if ($this->option('fix') && !$this->option('dry-run')) {
            $this->info("Fixes applied: {$this->stats['fixes_applied']}");
        }

        if (!empty($this->issues)) {
            $this->info('');
            $this->warn('Issues found:');
            $this->displayIssues();
        }

        if ($this->option('json')) {
            $this->outputJson();
        }
    }

    protected function displayIssues(): void
    {
        foreach ($this->issues as $issue) {
            $this->warn("Model: {$issue['model']}");
            $this->line("  Type: {$issue['type']}");
            $this->line("  Message: {$issue['data']['message']}");
            
            if (isset($issue['data']['fields'])) {
                $this->line("  Fields: " . implode(', ', $issue['data']['fields']));
            }
            
            if (isset($issue['data']['table'])) {
                $this->line("  Table: {$issue['data']['table']}");
            }
            
            $this->line('');
        }
    }

    protected function outputJson(): void
    {
        $output = [
            'stats' => $this->stats,
            'issues' => $this->issues
        ];
        
        $this->line(json_encode($output, JSON_PRETTY_PRINT));
    }

    protected function generateMigrations(): void
    {
        $this->info('Generating migrations...');
        
        foreach ($this->issues as $issue) {
            if ($issue['type'] === 'missing_fillable') {
                $this->generateMigrationForMissingColumns($issue);
            }
        }
    }

    protected function generateMigrationForMissingColumns(array $issue): void
    {
        // This would generate migrations for missing columns
        // Implementation would depend on specific requirements
        $this->info("Would generate migration for: {$issue['model']}");
    }
}