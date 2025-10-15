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
                            {--json : Output results in JSON format}
                            {--filament : Check Filament forms and relationships}
                            {--filament-resource= : Check specific Filament resource}';

    protected $description = 'Check model fillable properties against database schema and validate Filament forms';

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
        
        if ($this->option('filament')) {
            $this->checkFilamentForms();
        }
        
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
            if (str_starts_with($issue['type'], 'filament_')) {
                $this->warn("Resource: {$issue['model']}");
                $this->line("  Type: {$issue['type']}");
                $this->line("  Message: {$issue['data']['message']}");
                
                if (isset($issue['data']['relationship'])) {
                    $this->line("  Relationship: {$issue['data']['relationship']}");
                }
                
                if (isset($issue['data']['field'])) {
                    $this->line("  Field: {$issue['data']['field']}");
                }
                
                if (isset($issue['data']['component_type'])) {
                    $this->line("  Component: {$issue['data']['component_type']}");
                }
                
                if (isset($issue['data']['model'])) {
                    $this->line("  Model: {$issue['data']['model']}");
                }
                
                if (isset($issue['data']['file'])) {
                    $this->line("  File: {$issue['data']['file']}:{$issue['data']['line']}");
                }
            } else {
                $this->warn("Model: {$issue['model']}");
                $this->line("  Type: {$issue['type']}");
                $this->line("  Message: {$issue['data']['message']}");
                
                if (isset($issue['data']['fields'])) {
                    $this->line("  Fields: " . implode(', ', $issue['data']['fields']));
                }
                
                if (isset($issue['data']['table'])) {
                    $this->line("  Table: {$issue['data']['table']}");
                }
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

    protected function checkFilamentForms(): void
    {
        $this->info('');
        $this->info('Checking Filament Forms & Relationships');
        $this->info('=========================================');

        // Check if a specific resource is requested
        $specificResource = $this->option('filament-resource');
        
        if ($specificResource) {
            // Handle different resource name formats
            if (!str_contains($specificResource, '\\')) {
                // If no backslashes, assume it's just the class name in the standard Filament location
                $specificResource = 'App\\Filament\\Admin\\Resources\\' . $specificResource;
            }
            $this->info("Checking specific resource: {$specificResource}");
            try {
                $this->checkFilamentResource($specificResource);
            } catch (\Throwable $e) {
                $this->error("Error checking resource {$specificResource}: " . $e->getMessage());
            }
            return;
        }
        
        $filamentPath = app_path('Filament');
        if (!File::exists($filamentPath)) {
            $this->warn('No Filament directory found. Skipping Filament checks.');
            return;
        }

        $resources = $this->findFilamentResources($filamentPath);

        foreach ($resources as $resourceClass) {
            $this->info("Checking Resource: {$resourceClass}");
            try {
                $this->checkFilamentResource($resourceClass);
            } catch (\Throwable $e) {
                $this->error("Error checking resource {$resourceClass}: " . $e->getMessage());
            }
        }
    }

    protected function findFilamentResources(string $path): array
    {
        $files = File::allFiles($path);
        $resources = [];

        foreach ($files as $file) {
            try {
                $class = $this->getClassFromFile($file->getPathname());
                if ($class && class_exists($class, false)) { // Don't autoload
                    if (is_subclass_of($class, \Filament\Resources\Resource::class)) {
                        $resources[] = $class;
                    }
                } else {
                    $this->warn("Skipping file with invalid class: {$file->getPathname()}");
                }
            } catch (\Throwable $e) {
                $this->error("Cannot process file {$file->getPathname()}: " . $e->getMessage());
                // Continue with other files
            }
        }

        return $resources;
    }

    protected function checkFilamentResource(string $resourceClass): void
    {
        try {
            // Use reflection to get the model class without instantiating the resource
            $reflection = new \ReflectionClass($resourceClass);
            $getModelMethod = $reflection->getMethod('getModel');
            
            if ($getModelMethod->isStatic()) {
                $modelClass = $resourceClass::getModel();
                $model = new $modelClass();

                $this->checkFilamentMethods($resourceClass, $model, ['form', 'table']);
            } else {
                $this->error("Cannot check resource {$resourceClass}: getModel() is not static");
            }
        } catch (\Throwable $e) {
            $this->error("Cannot check resource {$resourceClass}: " . $e->getMessage());
            // Continue with other resources instead of failing completely
        }
    }

    protected function checkFilamentMethods(string $resourceClass, $model, array $methodNames): void
    {
        $reflection = new \ReflectionClass($resourceClass);

        foreach ($methodNames as $methodName) {
            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                if ($method->isStatic()) {
                    $this->findAndCheckRelationshipsInFilamentMethod($method, $model, $resourceClass);
                }
            }
        }
    }

    protected function findAndCheckRelationshipsInFilamentMethod(\ReflectionMethod $method, $model, string $resourceClass): void
    {
        $filePath = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($filePath);
        $content = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        // Check for ->relationship() calls
        if (preg_match_all('/->relationship\(\s*\'([a-zA-Z0-9_]+)\'/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $relationshipName = $match[0];
                $offset = $match[1];
                $lineNumber = $this->getLineNumberFromOffset($content, $offset) + $startLine -1;
                $this->validateFilamentRelationship($model, $relationshipName, $resourceClass, $filePath, $lineNumber);
            }
        }

        // Check for Select::make()->relationship() patterns and other relationship-based components
        if (preg_match_all('/(Select|BelongsToSelect|BelongsToManySelect|HasManySelect|HasManyThroughSelect)::make\(\s*[\'\"]([a-zA-Z0-9_]+)[\'\"]\s*\)\s*->.*?relationship\(\s*[\'\"]([a-zA-Z0-9_]+)[\'\"]/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $componentType = isset($matches[1][$i][0]) ? $matches[1][$i][0] : 'Select';
                $fieldName = isset($matches[2][$i][0]) ? $matches[2][$i][0] : 'unknown';
                $relationshipName = isset($matches[3][$i][0]) ? $matches[3][$i][0] : 'unknown';
                // Use approximate line number
                $lineNumber = $startLine + intval($i * 5);
                $this->validateFilamentSelectRelationship($model, $relationshipName, $fieldName, $resourceClass, $filePath, $lineNumber, $componentType);
            }
        }
    }

    protected function validateFilamentRelationship($model, string $relationshipName, string $resourceClass, string $filePath, int $lineNumber): void
    {
        $modelClass = get_class($model);
        if (!method_exists($model, $relationshipName)) {
            $this->addIssue($resourceClass, 'filament_broken_relationship', [
                'relationship' => $relationshipName,
                'model' => $modelClass,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Broken Relationship: Method '{$relationshipName}' not found on model '{$modelClass}'."
            ]);
            return;
        }

        try {
            $relation = $model->$relationshipName();
            if (!$relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                $this->addIssue($resourceClass, 'filament_invalid_relationship', [
                    'relationship' => $relationshipName,
                    'model' => $modelClass,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'message' => "Invalid Relationship Return Type: Method '{$relationshipName}' does not return a valid Eloquent Relation object."
                ]);
            }
        } catch (\Throwable $e) {
            $this->addIssue($resourceClass, 'filament_relationship_error', [
                'relationship' => $relationshipName,
                'model' => $modelClass,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Error executing relationship method '{$relationshipName}': " . $e->getMessage()
            ]);
        }
    }

    protected function validateFilamentSelectRelationship($model, string $relationshipName, string $fieldName, string $resourceClass, string $filePath, int $lineNumber, string $componentType = 'Select'): void
    {
        $modelClass = get_class($model);
        
        // First check if the relationship method exists
        if (!method_exists($model, $relationshipName)) {
            $this->addIssue($resourceClass, 'filament_select_broken_relationship', [
                'component_type' => $componentType,
                'field' => $fieldName,
                'relationship' => $relationshipName,
                'model' => $modelClass,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Broken {$componentType} Relationship: Field '{$fieldName}' references relationship '{$relationshipName}' which doesn't exist."
            ]);
            return;
        }

        try {
            $relation = $model->$relationshipName();
            
            // Check if relationship returns null
            if ($relation === null) {
                $this->addIssue($resourceClass, 'filament_null_relationship', [
                    'component_type' => $componentType,
                    'field' => $fieldName,
                    'relationship' => $relationshipName,
                    'model' => $modelClass,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'message' => "Null Relationship: Field '{$fieldName}' relationship '{$relationshipName}' returns null."
                ]);
                return;
            }
            
            // Check if it's a valid Relation instance
            if (!$relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                $this->addIssue($resourceClass, 'filament_invalid_select_relationship', [
                    'component_type' => $componentType,
                    'field' => $fieldName,
                    'relationship' => $relationshipName,
                    'model' => $modelClass,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'message' => "Invalid Relationship: Field '{$fieldName}' relationship '{$relationshipName}' does not return a valid Relation object."
                ]);
                return;
            }
            
        } catch (\Throwable $e) {
            $this->addIssue($resourceClass, 'filament_select_relationship_error', [
                'component_type' => $componentType,
                'field' => $fieldName,
                'relationship' => $relationshipName,
                'model' => $modelClass,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Error in Relationship: Field '{$fieldName}' relationship '{$relationshipName}' threw exception: " . $e->getMessage()
            ]);
        }
    }

    protected function getClassFromFile(string $path): ?string
    {
        $content = file_get_contents($path);
        $tokens = token_get_all($content);
        $namespace = '';
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j] === ';') {
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j] === '{') {
                        if (isset($tokens[$i + 2]) && is_array($tokens[$i + 2]) && isset($tokens[$i + 2][1])) {
                            $className = $tokens[$i + 2][1];
                            return $namespace . '\\' . $className;
                        }
                        break;
                    }
                }
            }
        }
        return null;
    }

    protected function getLineNumberFromOffset(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
}