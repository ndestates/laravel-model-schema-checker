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
                            {--filament-resource= : Check specific Filament resource}
                            {--security : Check for XSS and CSRF vulnerabilities}
                            {--laravel-forms : Check Laravel forms (Blade templates, Livewire)}
                            {--relationships : Check model relationships and foreign keys}
                            {--migrations : Check migration consistency and indexes}
                            {--validation : Check validation rules against schema}
                            {--performance : Check for N+1 queries and optimization opportunities}
                            {--quality : Check code quality and Laravel best practices}
                            {--all : Run all available checks}
                            {--sync-migrations : Generate fresh migrations from current database schema}
                            {--export-data : Export database data to preserve during migration sync}
                            {--import-data : Import previously exported data}
                            {--cleanup-migrations : Remove old migration files safely}';

    protected $description = 'Comprehensive Laravel model, schema, security, and code quality checker with migration synchronization';

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
                'updated_by', 'deleted_by', 'deleted_at'
            ],
            'security_excluded_fields' => [
                'email_verified_at', 'remember_token', 'password', 
                'password_confirmation', 'api_token', 'access_token',
                'refresh_token', 'verification_token'
            ],
            'database_connection' => env('DB_CONNECTION', 'mysql'),
        ]);

        $this->info('Laravel Model Schema Checker');
        $this->info('================================');

        if ($this->option('dry-run')) {
            $this->warn('Running in DRY-RUN mode - no changes will be made');
        }

        $this->checkModels();
        
        if ($this->option('filament') || $this->option('all')) {
            $this->checkFilamentForms();
        }
        
        if ($this->option('security') || $this->option('all')) {
            $this->checkSecurityVulnerabilities();
        }
        
        if ($this->option('laravel-forms') || $this->option('all')) {
            $this->checkLaravelForms();
        }
        
        if ($this->option('relationships') || $this->option('all')) {
            $this->checkModelRelationships();
        }
        
        if ($this->option('migrations') || $this->option('all')) {
            $this->checkMigrationConsistency();
        }
        
        if ($this->option('validation') || $this->option('all')) {
            $this->checkValidationRules();
        }
        
        if ($this->option('performance') || $this->option('all')) {
            $this->checkPerformanceIssues();
        }
        
        if ($this->option('quality') || $this->option('all')) {
            $this->checkCodeQuality();
        }
        
        // Handle data export/import operations first
        if ($this->option('export-data')) {
            $this->exportDatabaseData();
            return Command::SUCCESS;
        }
        
        if ($this->option('import-data')) {
            $this->importDatabaseData();
            return Command::SUCCESS;
        }
        
        // Handle migration synchronization
        if ($this->option('sync-migrations')) {
            $this->syncMigrationsFromDatabase();
            return Command::SUCCESS;
        }
        
        if ($this->option('cleanup-migrations')) {
            $this->cleanupMigrationFiles();
            return Command::SUCCESS;
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
        $securityExcludedFields = $this->config['security_excluded_fields'];

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
            // Separate security-sensitive fields from regular missing fields
            $securityFields = array_intersect($missingFillable, $securityExcludedFields);
            $regularMissingFields = array_diff($missingFillable, $securityExcludedFields);

            // Report regular missing fields that should be in fillable
            if (!empty($regularMissingFields)) {
                $this->addIssue($className, 'missing_fillable', [
                    'fields' => $regularMissingFields,
                    'message' => 'Database columns not in fillable array (should be mass-assignable)'
                ]);
            }

            // Report security-sensitive fields that are correctly excluded
            if (!empty($securityFields)) {
                $this->addIssue($className, 'security_excluded', [
                    'fields' => $securityFields,
                    'message' => 'Security-sensitive fields correctly excluded from fillable array (should NOT be mass-assignable)'
                ]);
            }
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

    protected function addIssue(string $model, string $type, array $data): void
    {
        $this->issues[] = [
            'model' => $model,
            'type' => $type,
            'data' => $data
        ];
        
        // Don't count security_excluded as issues since they're intentionally excluded
        if ($type !== 'security_excluded') {
            $this->stats['issues_found']++;
        }
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
            } elseif (in_array($issue['type'], ['csrf_missing', 'xss_unescaped_output'])) {
                $this->warn("Security Issue: {$issue['model']}");
                $this->line("  Type: {$issue['type']}");
                $this->line("  Message: {$issue['data']['message']}");
                
                if (isset($issue['data']['file'])) {
                    $this->line("  File: {$issue['data']['file']}");
                }
                
                if (isset($issue['data']['unescaped_output'])) {
                    $this->line("  Code: {$issue['data']['unescaped_output']}");
                }
                
                if (isset($issue['data']['form_tag'])) {
                    $this->line("  Form: {$issue['data']['form_tag']}");
                }
            } elseif (str_starts_with($issue['type'], 'filament_field_')) {
                $this->warn("Field Alignment: {$issue['model']}");
                $this->line("  Type: {$issue['type']}");
                $this->line("  Message: {$issue['data']['message']}");
                
                if (isset($issue['data']['field'])) {
                    $this->line("  Field: {$issue['data']['field']}");
                }
                
                if (isset($issue['data']['model'])) {
                    $this->line("  Model: {$issue['data']['model']}");
                }
                
                if (isset($issue['data']['file'])) {
                    $this->line("  File: {$issue['data']['file']}:{$issue['data']['line']}");
                }
            } elseif ($issue['type'] === 'security_excluded') {
                $this->info("✅ Security Check: {$issue['model']}");
                $this->line("  Type: {$issue['type']}");
                $this->line("  Message: {$issue['data']['message']}");
                
                if (isset($issue['data']['fields'])) {
                    $this->line("  Fields: " . implode(', ', $issue['data']['fields']));
                }
            } elseif ($issue['model'] === 'Code Quality') {
                $this->warn("Code Quality: {$issue['type']}");
                $this->line("  File: {$issue['data']['file']}");
                $this->line("  Message: {$issue['data']['message']}");
                
                if (isset($issue['data']['class'])) {
                    $this->line("  Class: {$issue['data']['class']}");
                }
                
                if (isset($issue['data']['method'])) {
                    $this->line("  Method: {$issue['data']['method']}");
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

        // Also check if Filament package is installed
        if (!class_exists(\Filament\FilamentServiceProvider::class) && !class_exists(\Filament\Resources\Resource::class)) {
            $this->warn('Filament package not found. Make sure Filament is installed: composer require filament/filament');
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
                    if ($methodName === 'form') {
                        $this->checkFilamentFormFieldAlignment($method, $model, $resourceClass);
                    }
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

    protected function checkFilamentFormFieldAlignment(\ReflectionMethod $method, $model, string $resourceClass): void
    {
        $filePath = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($filePath);
        $content = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $modelClass = get_class($model);
        $tableName = $model->getTable();
        $tableColumns = $this->getTableColumns($tableName);
        $fillable = $model->getFillable();

        // Find all Filament field definitions like TextInput::make('field_name'), Select::make('field_name'), etc.
        $fieldPatterns = [
            '/(\w+)::make\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*\)/',  // Basic field pattern
            '/(\w+)::make\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*\)\s*->/',  // Field with method chaining
        ];

        $foundFields = [];

        foreach ($fieldPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $fieldName = $matches[2][$i][0];
                    $offset = $matches[2][$i][1];
                    $lineNumber = $this->getLineNumberFromOffset($content, $offset) + $startLine - 1;

                    if (!in_array($fieldName, $foundFields)) {
                        $foundFields[] = $fieldName;
                        $this->validateFilamentField($fieldName, $tableColumns, $fillable, $modelClass, $resourceClass, $filePath, $lineNumber);
                    }
                }
            }
        }
    }

    protected function validateFilamentField(string $fieldName, array $tableColumns, array $fillable, string $modelClass, string $resourceClass, string $filePath, int $lineNumber): void
    {
        // Check if field exists in database
        if (!in_array($fieldName, $tableColumns)) {
            $this->addIssue($resourceClass, 'filament_field_not_in_database', [
                'field' => $fieldName,
                'model' => $modelClass,
                'table_columns' => $tableColumns,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Field '{$fieldName}' in Filament form does not exist in database table."
            ]);
            return;
        }

        // Check if field is in fillable array (for mass assignment)
        if (!in_array($fieldName, $fillable)) {
            $this->addIssue($resourceClass, 'filament_field_not_fillable', [
                'field' => $fieldName,
                'model' => $modelClass,
                'fillable' => $fillable,
                'file' => $filePath,
                                'line' => $lineNumber,
                'message' => "Field '{$fieldName}' in Filament form is not in the model's fillable array."
            ]);
        }
    }

    protected function checkSecurityVulnerabilities(): void
    {
        $this->info('');
        $this->info('Checking Security Vulnerabilities');
        $this->info('=================================');

        // Check for CSRF protection in forms
        $this->checkCSRFProtection();

        // Check for XSS vulnerabilities
        $this->checkXSSVulnerabilities();

        // Check for SQL injection vulnerabilities
        $this->checkSQLInjectionVulnerabilities();

        // Check for path traversal vulnerabilities
        $this->checkPathTraversalVulnerabilities();
    }

    protected function checkCSRFProtection(): void
    {
        $this->info('Checking CSRF Protection...');

        // Check Filament forms (they handle CSRF automatically)
        if (class_exists(\Filament\FilamentServiceProvider::class)) {
            $this->info('✓ Filament forms include automatic CSRF protection');
        }

        // Check Laravel forms in blade templates
        $this->checkBladeCSRFProtection();
    }

    protected function checkXSSVulnerabilities(): void
    {
        $this->info('Checking XSS Vulnerabilities...');

        // Check Filament forms (they handle escaping automatically)
        if (class_exists(\Filament\FilamentServiceProvider::class)) {
            $this->info('✓ Filament forms include automatic XSS protection');
        }

        // Check Laravel blade templates for unescaped output
        $this->checkBladeXSSProtection();
    }

    protected function checkSQLInjectionVulnerabilities(): void
    {
        $this->info('Checking SQL Injection Vulnerabilities...');

        // Check for raw database queries in controllers and models
        $this->checkRawDatabaseQueries();

        // Check for proper use of Eloquent vs raw queries
        $this->checkEloquentUsage();
    }

    protected function checkPathTraversalVulnerabilities(): void
    {
        $this->info('Checking Path Traversal Vulnerabilities...');

        // Check file operations for path traversal issues
        $this->checkFileOperations();

        // Check for unsafe file upload handling
        $this->checkFileUploads();
    }

    protected function checkBladeCSRFProtection(): void
    {
        $viewPath = resource_path('views');
        if (!File::exists($viewPath)) {
            return;
        }

        $bladeFiles = File::allFiles($viewPath);
        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'blade.php') {
                $content = file_get_contents($file->getPathname());

                // Check for forms without CSRF tokens
                if (preg_match_all('/<form[^>]*>/i', $content, $matches)) {
                    foreach ($matches[0] as $formTag) {
                        if (!preg_match('/@csrf|\{\{\s*csrf_token\s*\}\}/', $content)) {
                            $this->addIssue('Blade Template', 'csrf_missing', [
                                'file' => $file->getPathname(),
                                'form_tag' => $formTag,
                                'message' => 'Form found without CSRF token protection'
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function checkBladeXSSProtection(): void
    {
        $viewPath = resource_path('views');
        if (!File::exists($viewPath)) {
            return;
        }

        $bladeFiles = File::allFiles($viewPath);
        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'blade.php') {
                $content = file_get_contents($file->getPathname());

                // Check for unescaped output that could lead to XSS
                if (preg_match_all('/\{\{\{\s*\$[^}]+\s*\}\}\}/', $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $this->addIssue('Blade Template', 'xss_unescaped_output', [
                            'file' => $file->getPathname(),
                            'unescaped_output' => $match,
                            'message' => 'Triple braces {{{ }}} allow unescaped HTML output - potential XSS vulnerability'
                        ]);
                    }
                }
            }
        }
    }

    protected function checkLaravelForms(): void
    {
        $this->info('');
        $this->info('Checking Laravel Forms');
        $this->info('=====================');

        // Check Blade templates for forms
        $this->checkBladeForms();

        // Check Livewire components
        $this->checkLivewireForms();
    }

    protected function checkBladeForms(): void
    {
        $viewPath = resource_path('views');
        if (!File::exists($viewPath)) {
            $this->warn('No views directory found. Skipping Blade form checks.');
            return;
        }

        $bladeFiles = File::allFiles($viewPath);
        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'blade.php') {
                $this->analyzeBladeForm($file->getPathname());
            }
        }
    }

    protected function checkLivewireForms(): void
    {
        $livewirePath = app_path('Livewire');
        if (!File::exists($livewirePath)) {
            $this->warn('No Livewire directory found. Skipping Livewire form checks.');
            return;
        }

        $livewireFiles = File::allFiles($livewirePath);
        foreach ($livewireFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->analyzeLivewireForm($file->getPathname());
            }
        }
    }

    protected function analyzeBladeForm(string $filePath): void
    {
        $content = file_get_contents($filePath);

        // Look for form inputs and try to match them with models
        // This is a simplified check - in practice, this would need more sophisticated parsing
        if (preg_match_all('/name=["\']([^"\']+)["\']/', $content, $matches)) {
            $fieldNames = $matches[1];

            // Try to determine the model from the controller or route
            // This is simplified - real implementation would need better model detection
            $this->info("Found form fields in {$filePath}: " . implode(', ', $fieldNames));
        }
    }

    protected function analyzeLivewireForm(string $filePath): void
    {
        $content = file_get_contents($filePath);

        // Check for public properties that might be form fields
        if (preg_match_all('/public\s+\$([a-zA-Z0-9_]+)\s*;/', $content, $matches)) {
            $properties = $matches[1];
            $this->info("Found Livewire properties in {$filePath}: " . implode(', ', $properties));
        }
    }

    protected function checkRawDatabaseQueries(): void
    {
        // Check controllers for raw database queries
        $controllerPath = app_path('Http/Controllers');
        if (File::exists($controllerPath)) {
            $controllerFiles = File::allFiles($controllerPath);
            foreach ($controllerFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for raw DB::raw(), DB::select(), etc.
                    $rawQueryPatterns = [
                        '/DB::raw\(/',
                        '/DB::select\(/',
                        '/DB::insert\(/',
                        '/DB::update\(/',
                        '/DB::delete\(/',
                    ];

                    foreach ($rawQueryPatterns as $pattern) {
                        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                            foreach ($matches[0] as $match) {
                                $offset = $match[1];
                                $lineNumber = $this->getLineNumberFromString($content, $offset);

                                $this->addIssue('Controller', 'sql_injection_risk', [
                                    'file' => $file->getPathname(),
                                    'line' => $lineNumber,
                                    'query_type' => str_replace(['DB::', '('], '', $match[0]),
                                    'message' => "Raw database query found - potential SQL injection vulnerability. Use parameterized queries or Eloquent instead."
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function checkEloquentUsage(): void
    {
        // Check models for proper Eloquent usage vs raw queries
        $modelPath = app_path('Models');
        if (File::exists($modelPath)) {
            $modelFiles = File::allFiles($modelPath);
            foreach ($modelFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for raw queries in model methods
                    if (preg_match_all('/\bselect\b.*\bwhere\b.*[\'"]\s*\.\s*\$/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $offset = $match[1];
                            $lineNumber = $this->getLineNumberFromString($content, $offset);

                            $this->addIssue('Model', 'sql_injection_string_concat', [
                                'file' => $file->getPathname(),
                                'line' => $lineNumber,
                                'code' => trim($match[0]),
                                'message' => "String concatenation in SQL query - potential SQL injection. Use parameterized queries."
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function checkFileOperations(): void
    {
        // Check for unsafe file operations
        $paths = [app_path('Http/Controllers'), app_path('Models')];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                $files = File::allFiles($path);
                foreach ($files as $file) {
                    if ($file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());

                        // Check for direct file path usage without validation
                        $fileOpPatterns = [
                            '/\bfopen\b.*\$_\w+/',
                            '/\bfile_get_contents\b.*\$_\w+/',
                            '/\bfile_put_contents\b.*\$_\w+/',
                            '/\bunlink\b.*\$_\w+/',
                            '/\binclude\b.*\$_\w+/',
                            '/\brequire\b.*\$_\w+/',
                        ];

                        foreach ($fileOpPatterns as $pattern) {
                            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                                foreach ($matches[0] as $match) {
                                    $offset = $match[1];
                                    $lineNumber = $this->getLineNumberFromString($content, $offset);

                                    $this->addIssue('File Operation', 'path_traversal_risk', [
                                        'file' => $file->getPathname(),
                                        'line' => $lineNumber,
                                        'operation' => trim($match[0]),
                                        'message' => "File operation using user input - potential path traversal vulnerability. Validate and sanitize file paths."
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function checkFileUploads(): void
    {
        // Check controllers for file upload handling
        $controllerPath = app_path('Http/Controllers');
        if (File::exists($controllerPath)) {
            $controllerFiles = File::allFiles($controllerPath);
            foreach ($controllerFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for file upload handling
                    if (preg_match('/\$request->file\(|\$_FILES/', $content)) {
                        // Check if file validation is present
                        if (!preg_match('/validate\(|rules\(/', $content)) {
                            $this->addIssue('File Upload', 'upload_validation_missing', [
                                'file' => $file->getPathname(),
                                'message' => "File upload detected without validation rules. Implement file type, size, and name validation to prevent security issues."
                            ]);
                        }

                        // Check for original filename usage (potential path traversal)
                        if (preg_match('/getClientOriginalName\(|originalName/', $content)) {
                            $this->addIssue('File Upload', 'original_filename_usage', [
                                'file' => $file->getPathname(),
                                'message' => "Using original filename from upload - potential path traversal. Generate safe filenames instead."
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function getLineNumberFromString(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }

    protected function checkModelRelationships(): void
    {
        $this->info('');
        $this->info('Checking Model Relationships');
        $this->info('===========================');

        $modelsPath = $this->config['models_dir'];

        if (!File::exists($modelsPath)) {
            $this->error("Models directory not found: {$modelsPath}");
            return;
        }

        $modelFiles = File::allFiles($modelsPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkModelRelationshipsForFile($file);
            }
        }
    }

    protected function checkModelRelationshipsForFile($file): void
    {
        $namespace = $this->getNamespaceFromFile($file->getPathname());
        $className = $namespace . '\\' . $file->getFilenameWithoutExtension();

        if (!class_exists($className)) {
            return;
        }

        try {
            $reflection = new ReflectionClass($className);
            $content = file_get_contents($file->getPathname());

            // Check for relationship methods
            $this->checkRelationshipMethods($reflection, $content, $file->getPathname());

            // Check foreign key constraints
            $this->checkForeignKeyConstraints($className, $file->getPathname());

            // Check relationship naming conventions
            $this->checkRelationshipNaming($reflection, $content, $file->getPathname());

        } catch (\Exception $e) {
            $this->addIssue('Model', 'reflection_error', [
                'file' => $file->getPathname(),
                'model' => $className,
                'error' => $e->getMessage(),
                'message' => "Could not analyze model relationships due to reflection error"
            ]);
        }
    }

    protected function checkRelationshipMethods(ReflectionClass $reflection, string $content, string $filePath): void
    {
        $relationshipPatterns = [
            '/public function (belongsTo|hasOne|hasMany|belongsToMany|morphTo|morphOne|morphMany|morphToMany)\(/',
            '/public function (belongsTo|hasOne|hasMany|belongsToMany)\w*\(/',
        ];

        $foundRelationships = [];

        foreach ($relationshipPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $relationshipType) {
                    $foundRelationships[] = $relationshipType;
                }
            }
        }

        if (empty($foundRelationships)) {
            return; // No relationships to check
        }

        // Check if relationships return proper relationship instances
        $this->checkRelationshipReturnTypes($reflection, $content, $filePath);

        // Check for missing inverse relationships
        $this->checkInverseRelationships($reflection, $foundRelationships, $filePath);
    }

    protected function checkRelationshipReturnTypes(ReflectionClass $reflection, string $content, string $filePath): void
    {
        $modelName = $reflection->getShortName();

        // Look for relationship methods that don't return relationship instances
        if (preg_match_all('/public function (\w+)\([^}]*return\s+([^;]+);/s', $content, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $methodName = $matches[1][$i];
                $returnValue = trim($matches[2][$i]);

                // Check if it's a relationship method but doesn't return a relationship
                if (in_array($methodName, ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany', 'morphToMany'])) {
                    if (!preg_match('/\$this->(belongsTo|hasOne|hasMany|belongsToMany|morphTo|morphOne|morphMany|morphToMany)\(/', $returnValue)) {
                        $this->addIssue('Relationship', 'invalid_relationship_return', [
                            'file' => $filePath,
                            'model' => $modelName,
                            'method' => $methodName,
                            'return_value' => $returnValue,
                            'message' => "Relationship method '{$methodName}' should return a relationship instance, not '{$returnValue}'"
                        ]);
                    }
                }
            }
        }
    }

    protected function checkInverseRelationships(ReflectionClass $reflection, array $relationships, string $filePath): void
    {
        $modelName = $reflection->getShortName();

        // This is a simplified check - in a real implementation, you'd need to analyze
        // the related models to check for inverse relationships
        $hasManyRelationships = array_filter($relationships, function($rel) {
            return in_array($rel, ['hasMany', 'belongsToMany', 'morphMany', 'morphToMany']);
        });

        if (!empty($hasManyRelationships)) {
            $this->addIssue('Relationship', 'missing_inverse_check', [
                'file' => $filePath,
                'model' => $modelName,
                'relationships' => implode(', ', $hasManyRelationships),
                'message' => "Consider checking if inverse relationships exist in related models for: " . implode(', ', $hasManyRelationships)
            ]);
        }
    }

    protected function checkForeignKeyConstraints(string $className, string $filePath): void
    {
        try {
            $model = new $className();
            $tableName = $model->getTable();

            // Get foreign key constraints from database
            $foreignKeys = $this->getForeignKeyConstraints($tableName);

            foreach ($foreignKeys as $fk) {
                // Check if the foreign key column exists in fillable/guarded
                $fillable = $model->getFillable();
                $guarded = $model->getGuarded();

                if (!empty($guarded) && !in_array('*', $guarded)) {
                    if (in_array($fk['column'], $guarded)) {
                        $this->addIssue('Foreign Key', 'guarded_foreign_key', [
                            'file' => $filePath,
                            'model' => $className,
                            'table' => $tableName,
                            'column' => $fk['column'],
                            'references' => $fk['references'],
                            'message' => "Foreign key column '{$fk['column']}' is guarded. Consider adding it to fillable for proper relationship handling."
                        ]);
                    }
                } elseif (!empty($fillable)) {
                    if (!in_array($fk['column'], $fillable)) {
                        $this->addIssue('Foreign Key', 'missing_foreign_key_fillable', [
                            'file' => $filePath,
                            'model' => $className,
                            'table' => $tableName,
                            'column' => $fk['column'],
                            'references' => $fk['references'],
                            'message' => "Foreign key column '{$fk['column']}' should be in fillable array for proper mass assignment."
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            // Skip if model can't be instantiated or table doesn't exist
        }
    }

    protected function getForeignKeyConstraints(string $tableName): array
    {
        try {
            $databaseName = DB::getDatabaseName();
            $constraints = [];

            // This is database-specific - simplified for common databases
            if (DB::getDriverName() === 'mysql') {
                $results = DB::select("
                    SELECT 
                        COLUMN_NAME as column_name,
                        REFERENCED_TABLE_NAME as referenced_table,
                        REFERENCED_COLUMN_NAME as referenced_column
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                ", [$databaseName, $tableName]);

                foreach ($results as $result) {
                    $constraints[] = [
                        'column' => $result->column_name,
                        'references' => $result->referenced_table . '.' . $result->referenced_column
                    ];
                }
            }

            return $constraints;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function checkRelationshipNaming(ReflectionClass $reflection, string $content, string $filePath): void
    {
        $modelName = $reflection->getShortName();

        // Check for relationship methods with poor naming
        if (preg_match_all('/public function ([a-zA-Z_][a-zA-Z0-9_]*)\(/', $content, $matches)) {
            foreach ($matches[1] as $methodName) {
                // Skip standard relationship types
                if (in_array($methodName, ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany', 'morphToMany'])) {
                    continue;
                }

                // Check naming conventions
                if (strlen($methodName) < 3) {
                    $this->addIssue('Relationship', 'poor_relationship_naming', [
                        'file' => $filePath,
                        'model' => $modelName,
                        'method' => $methodName,
                        'message' => "Relationship method name '{$methodName}' is too short. Use descriptive names like 'user', 'posts', 'comments'."
                    ]);
                }

                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $methodName)) {
                    $this->addIssue('Relationship', 'invalid_relationship_naming', [
                        'file' => $filePath,
                        'model' => $modelName,
                        'method' => $methodName,
                        'message' => "Relationship method name '{$methodName}' should use camelCase naming convention."
                    ]);
                }
            }
        }
    }

    protected function checkMigrationConsistency(): void
    {
        $this->info('');
        $this->info('Checking Migration Consistency');
        $this->info('==============================');

        $migrationPath = database_path('migrations');

        if (!File::exists($migrationPath)) {
            $this->warn("Migrations directory not found: {$migrationPath}");
            return;
        }

        $migrationFiles = File::allFiles($migrationPath);

        foreach ($migrationFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkMigrationFile($file);
            }
        }

        // Check for missing indexes
        $this->checkMissingIndexes();

        // Check migration naming conventions
        $this->checkMigrationNaming($migrationFiles);
    }

    protected function checkMigrationFile($file): void
    {
        $content = file_get_contents($file->getPathname());
        $fileName = $file->getFilename();

        // Extract table name from migration
        if (preg_match('/create_(\w+)_table/', $fileName, $matches)) {
            $tableName = $matches[1];
            $this->checkMigrationContent($content, $tableName, $file->getPathname());
        }
    }

    protected function checkMigrationContent(string $content, string $tableName, string $filePath): void
    {
        // Check for common migration issues

        // Check for nullable foreign keys without default
        if (preg_match_all('/\$table->foreignId\(([^\)]+)\)->nullable\(\)/', $content, $matches)) {
            foreach ($matches[1] as $columnDef) {
                if (!preg_match('/default\(/', $columnDef)) {
                    $this->addIssue('Migration', 'nullable_foreign_key_no_default', [
                        'file' => $filePath,
                        'table' => $tableName,
                        'column' => trim($columnDef, "'\""),
                        'message' => "Nullable foreign key should have a default value (usually null)"
                    ]);
                }
            }
        }

        // Check for string columns without length
        if (preg_match_all('/\$table->string\(([^,)]+)\)/', $content, $matches)) {
            foreach ($matches[1] as $columnName) {
                if (!preg_match('/\d+/', $columnName)) {
                    $this->addIssue('Migration', 'string_without_length', [
                        'file' => $filePath,
                        'table' => $tableName,
                        'column' => trim($columnName, "'\""),
                        'message' => "String column should specify a length (e.g., string('name', 255))"
                    ]);
                }
            }
        }

        // Check for boolean columns with default null
        if (preg_match('/\$table->boolean\([^)]+\)->nullable\(\)/', $content)) {
            $this->addIssue('Migration', 'boolean_nullable', [
                'file' => $filePath,
                'table' => $tableName,
                'message' => "Boolean columns should not be nullable. Use ->default(false) instead."
            ]);
        }

        // Check for missing timestamps
        if (!preg_match('/\$table->timestamps\(\)/', $content)) {
            $this->addIssue('Migration', 'missing_timestamps', [
                'file' => $filePath,
                'table' => $tableName,
                'message' => "Consider adding timestamps() for created_at and updated_at columns"
            ]);
        }
    }

    protected function checkMissingIndexes(): void
    {
        try {
            // Get all tables
            $tables = DB::select('SHOW TABLES');
            $databaseName = DB::getDatabaseName();

            foreach ($tables as $table) {
                $tableName = $table->{'Tables_in_' . $databaseName};

                // Skip Laravel system tables
                if (in_array($tableName, ['migrations', 'failed_jobs', 'cache', 'sessions'])) {
                    continue;
                }

                // Check for foreign keys without indexes
                if (DB::getDriverName() === 'mysql') {
                    $foreignKeys = DB::select("
                        SELECT COLUMN_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                    ", [$databaseName, $tableName]);

                    foreach ($foreignKeys as $fk) {
                        $columnName = $fk->COLUMN_NAME;

                        // Check if there's an index on this column
                        $hasIndex = DB::select("
                            SELECT 1
                            FROM information_schema.STATISTICS
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                        ", [$databaseName, $tableName, $columnName]);

                        if (empty($hasIndex)) {
                            $this->addIssue('Database', 'missing_foreign_key_index', [
                                'table' => $tableName,
                                'column' => $columnName,
                                'message' => "Foreign key column '{$columnName}' in table '{$tableName}' should have an index for performance"
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("Could not check database indexes: " . $e->getMessage());
        }
    }

    protected function checkMigrationNaming($migrationFiles): void
    {
        foreach ($migrationFiles as $file) {
            $fileName = $file->getFilename();

            // Check naming convention: YYYY_MM_DD_HHMMSS_description.php
            if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z][a-z0-9_]*\.php$/', $fileName)) {
                $this->addIssue('Migration', 'invalid_migration_name', [
                    'file' => $file->getPathname(),
                    'filename' => $fileName,
                    'message' => "Migration filename should follow convention: YYYY_MM_DD_HHMMSS_description.php"
                ]);
            }

            // Check for descriptive names
            $description = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $fileName);
            $description = preg_replace('/\.php$/', '', $description);

            if (strlen($description) < 5) {
                $this->addIssue('Migration', 'poor_migration_description', [
                    'file' => $file->getPathname(),
                    'description' => $description,
                    'message' => "Migration description '{$description}' is too short. Use descriptive names like 'create_users_table' or 'add_email_to_users'"
                ]);
            }
        }
    }

    protected function checkValidationRules(): void
    {
        $this->info('');
        $this->info('Checking Validation Rules');
        $this->info('=========================');

        // Check model validation rules
        $this->checkModelValidationRules();

        // Check form request validation
        $this->checkFormRequestValidation();

        // Check controller validation
        $this->checkControllerValidation();
    }

    protected function checkModelValidationRules(): void
    {
        $modelsPath = $this->config['models_dir'];

        if (!File::exists($modelsPath)) {
            return;
        }

        $modelFiles = File::allFiles($modelsPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkModelValidationForFile($file);
            }
        }
    }

    protected function checkModelValidationForFile($file): void
    {
        $namespace = $this->getNamespaceFromFile($file->getPathname());
        $className = $namespace . '\\' . $file->getFilenameWithoutExtension();

        if (!class_exists($className)) {
            return;
        }

        try {
            $model = new $className();
            $tableName = $model->getTable();
            $content = file_get_contents($file->getPathname());

            // Check if model has validation rules
            if (preg_match('/public static \$rules\s*=\s*\[([^\]]+)\]/s', $content, $matches)) {
                $rulesContent = $matches[1];
                $this->validateRulesAgainstSchema($rulesContent, $tableName, $file->getPathname(), $className);
            }

            // Check for validation methods
            $this->checkValidationMethods($content, $file->getPathname(), $className);

        } catch (\Exception $e) {
            // Skip models that can't be instantiated
        }
    }

    protected function validateRulesAgainstSchema(string $rulesContent, string $tableName, string $filePath, string $className): void
    {
        try {
            // Get table columns
            $columns = $this->getTableColumns($tableName);

            // Parse rules (simplified parsing)
            $rules = [];
            if (preg_match_all("/'([^']+)'\s*=>\s*([^,]+),/", $rulesContent, $matches)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $field = $matches[1][$i];
                    $ruleString = trim($matches[2][$i], "'\"");

                    // Check if field exists in database
                    if (!isset($columns[$field])) {
                        $this->addIssue('Validation', 'rule_for_nonexistent_field', [
                            'file' => $filePath,
                            'model' => $className,
                            'field' => $field,
                            'table' => $tableName,
                            'message' => "Validation rule defined for field '{$field}' that doesn't exist in table '{$tableName}'"
                        ]);
                        continue;
                    }

                    $rules[$field] = $ruleString;
                }
            }

            // Check for required fields without validation
            foreach ($columns as $columnName => $columnInfo) {
                if ($columnInfo['nullable'] === false &&
                    !in_array($columnName, ['id', 'created_at', 'updated_at']) &&
                    !isset($rules[$columnName])) {

                    $this->addIssue('Validation', 'missing_required_validation', [
                        'file' => $filePath,
                        'model' => $className,
                        'field' => $columnName,
                        'table' => $tableName,
                        'message' => "Required field '{$columnName}' has no validation rule defined"
                    ]);
                }
            }

            // Check validation rule consistency with column types
            foreach ($rules as $field => $ruleString) {
                if (isset($columns[$field])) {
                    $this->checkRuleConsistency($field, $ruleString, $columns[$field], $filePath, $className);
                }
            }

        } catch (\Exception $e) {
            // Skip if table doesn't exist or can't be queried
        }
    }

    protected function getTableColumns(string $tableName): array
    {
        try {
            $columns = [];

            if (DB::getDriverName() === 'mysql') {
                $results = DB::select("
                    SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                    ORDER BY ORDINAL_POSITION
                ", [DB::getDatabaseName(), $tableName]);

                foreach ($results as $result) {
                    $columns[$result->COLUMN_NAME] = [
                        'type' => $result->DATA_TYPE,
                        'nullable' => $result->IS_NULLABLE === 'YES',
                        'default' => $result->COLUMN_DEFAULT,
                        'length' => $result->CHARACTER_MAXIMUM_LENGTH
                    ];
                }
            }

            return $columns;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function checkRuleConsistency(string $field, string $ruleString, array $columnInfo, string $filePath, string $className): void
    {
        $rules = explode('|', $ruleString);

        // Check string length validation
        if ($columnInfo['type'] === 'varchar' && $columnInfo['length']) {
            $hasMaxRule = false;
            foreach ($rules as $rule) {
                if (preg_match('/max:\d+/', $rule)) {
                    $hasMaxRule = true;
                    if (preg_match('/max:(\d+)/', $rule, $matches)) {
                        $maxLength = (int)$matches[1];
                        if ($maxLength > $columnInfo['length']) {
                            $this->addIssue('Validation', 'max_length_exceeds_column', [
                                'file' => $filePath,
                                'model' => $className,
                                'field' => $field,
                                'rule_max' => $maxLength,
                                'column_max' => $columnInfo['length'],
                                'message' => "Validation max length ({$maxLength}) exceeds column length ({$columnInfo['length']}) for field '{$field}'"
                            ]);
                        }
                    }
                }
            }

            if (!$hasMaxRule && in_array('string', $rules)) {
                $this->addIssue('Validation', 'missing_max_length_validation', [
                    'file' => $filePath,
                    'model' => $className,
                    'field' => $field,
                    'column_length' => $columnInfo['length'],
                    'message' => "String field '{$field}' should have max length validation (column allows {$columnInfo['length']} characters)"
                ]);
            }
        }

        // Check numeric validation for numeric columns
        if (in_array($columnInfo['type'], ['int', 'bigint', 'decimal', 'float', 'double'])) {
            if (!in_array('numeric', $rules) && !in_array('integer', $rules)) {
                $this->addIssue('Validation', 'missing_numeric_validation', [
                    'file' => $filePath,
                    'model' => $className,
                    'field' => $field,
                    'column_type' => $columnInfo['type'],
                    'message' => "Numeric field '{$field}' should have numeric or integer validation"
                ]);
            }
        }

        // Check boolean validation
        if ($columnInfo['type'] === 'tinyint' && $columnInfo['length'] == 1) {
            if (!in_array('boolean', $rules)) {
                $this->addIssue('Validation', 'missing_boolean_validation', [
                    'file' => $filePath,
                    'model' => $className,
                    'field' => $field,
                    'message' => "Boolean field '{$field}' should have boolean validation"
                ]);
            }
        }
    }

    protected function checkValidationMethods(string $content, string $filePath, string $className): void
    {
        // Check for custom validation methods
        if (preg_match_all('/public function validate([A-Z]\w*)\(/', $content, $matches)) {
            foreach ($matches[1] as $methodSuffix) {
                $methodName = 'validate' . $methodSuffix;

                // Check if method has proper return type or throws validation exceptions
                if (!preg_match("/function {$methodName}\([^}]*throws\s+ValidationException/", $content)) {
                    $this->addIssue('Validation', 'validation_method_no_exception', [
                        'file' => $filePath,
                        'model' => $className,
                        'method' => $methodName,
                        'message' => "Custom validation method '{$methodName}' should throw ValidationException on failure"
                    ]);
                }
            }
        }
    }

    protected function checkFormRequestValidation(): void
    {
        $requestPath = app_path('Http/Requests');

        if (!File::exists($requestPath)) {
            return;
        }

        $requestFiles = File::allFiles($requestPath);

        foreach ($requestFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                // Check if Form Request has rules method
                if (!preg_match('/public function rules\(\)/', $content)) {
                    $this->addIssue('Form Request', 'missing_rules_method', [
                        'file' => $file->getPathname(),
                        'message' => "Form Request class should have a rules() method"
                    ]);
                }

                // Check for authorization method
                if (!preg_match('/public function authorize\(\)/', $content)) {
                    $this->addIssue('Form Request', 'missing_authorize_method', [
                        'file' => $file->getPathname(),
                        'message' => "Form Request class should have an authorize() method"
                    ]);
                }
            }
        }
    }

    protected function checkControllerValidation(): void
    {
        $controllerPath = app_path('Http/Controllers');

        if (!File::exists($controllerPath)) {
            return;
        }

        $controllerFiles = File::allFiles($controllerPath);

        foreach ($controllerFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                // Check for inline validation that should be moved to Form Requests
                if (preg_match_all('/\$request->validate\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $offset = $match[1];
                        $lineNumber = $this->getLineNumberFromString($content, $offset);

                        $this->addIssue('Controller', 'inline_validation', [
                            'file' => $file->getPathname(),
                            'line' => $lineNumber,
                            'message' => "Consider moving inline validation to a Form Request class for better organization and reusability"
                        ]);
                    }
                }
            }
        }
    }

    protected function checkPerformanceIssues(): void
    {
        $this->info('');
        $this->info('Checking Performance Issues');
        $this->info('===========================');

        // Check for N+1 query problems
        $this->checkNPlusOneQueries();

        // Check eager loading usage
        $this->checkEagerLoading();

        // Check for missing database indexes
        $this->checkDatabaseIndexes();

        // Check for inefficient queries
        $this->checkInefficientQueries();
    }

    protected function checkNPlusOneQueries(): void
    {
        $controllerPath = app_path('Http/Controllers');

        if (!File::exists($controllerPath)) {
            return;
        }

        $controllerFiles = File::allFiles($controllerPath);

        foreach ($controllerFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                // Look for loops that access relationships
                $this->checkLoopsWithRelationships($content, $file->getPathname());
            }
        }
    }

    protected function checkLoopsWithRelationships(string $content, string $filePath): void
    {
        // Pattern to detect foreach loops accessing relationships
        $loopPatterns = [
            '/foreach\s*\([^}]*as\s+\$[a-zA-Z_][a-zA-Z0-9_]*\)\s*\{[^}]*\$[a-zA-Z_][a-zA-Z0-9_]*->[a-zA-Z_][a-zA-Z0-9_]*\s*;/',
            '/foreach\s*\([^}]*as\s+\$[a-zA-Z_][a-zA-Z0-9_]*\)\s*:\s*[^}]*\$[a-zA-Z_][a-zA-Z0-9_]*->[a-zA-Z_][a-zA-Z0-9_]*\s*;/',
        ];

        foreach ($loopPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $lineNumber = $this->getLineNumberFromString($content, $offset);
                    $codeSnippet = trim(substr($content, $offset, 100));

                    $this->addIssue('Performance', 'potential_n_plus_one', [
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'code' => $codeSnippet,
                        'message' => "Potential N+1 query detected. Consider using eager loading with ->with() or ->load()"
                    ]);
                }
            }
        }

        // Check for collection methods that might cause N+1
        if (preg_match_all('/->each\([^}]*\$[^\)]*->[a-zA-Z_]/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $offset = $match[1];
                $lineNumber = $this->getLineNumberFromString($content, $offset);

                $this->addIssue('Performance', 'n_plus_one_in_each', [
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'code' => trim(substr($content, $offset, 80)),
                    'message' => "N+1 query likely in ->each() closure. Use ->load() before ->each() or eager load relationships"
                ]);
            }
        }
    }

    protected function checkEagerLoading(): void
    {
        $controllerPath = app_path('Http/Controllers');

        if (!File::exists($controllerPath)) {
            return;
        }

        $controllerFiles = File::allFiles($controllerPath);

        foreach ($controllerFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                // Check for queries without eager loading
                $this->checkQueriesWithoutEagerLoading($content, $file->getPathname());
            }
        }
    }

    protected function checkQueriesWithoutEagerLoading(string $content, string $filePath): void
    {
        // Look for model queries that might benefit from eager loading
        $queryPatterns = [
            '/[A-Z][a-zA-Z0-9_]*::where\(/',
            '/[A-Z][a-zA-Z0-9_]*::find\(/',
            '/[A-Z][a-zA-Z0-9_]*::all\(/',
            '/[A-Z][a-zA-Z0-9_]*::get\(/',
        ];

        foreach ($queryPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $lineNumber = $this->getLineNumberFromString($content, $offset);
                    $queryLine = trim(substr($content, $offset, 100));

                    // Check if this query is followed by relationship access
                    $remainingContent = substr($content, $offset + strlen($match[0]));
                    if (preg_match('/^\s*[^}]*->[a-zA-Z_][a-zA-Z0-9_]*\s*;/', $remainingContent)) {
                        $this->addIssue('Performance', 'missing_eager_loading', [
                            'file' => $filePath,
                            'line' => $lineNumber,
                            'query' => $queryLine,
                            'message' => "Query may benefit from eager loading. Consider using ->with('relationship') to prevent N+1 queries"
                        ]);
                    }
                }
            }
        }
    }

    protected function checkDatabaseIndexes(): void
    {
        try {
            $databaseName = DB::getDatabaseName();

            if (DB::getDriverName() === 'mysql') {
                // Get tables with large row counts that might need indexes
                $largeTables = DB::select("
                    SELECT TABLE_NAME, TABLE_ROWS
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = ? AND TABLE_ROWS > 1000
                    ORDER BY TABLE_ROWS DESC
                ", [$databaseName]);

                foreach ($largeTables as $table) {
                    $tableName = $table->TABLE_NAME;

                    // Check for tables with WHERE clauses in code but no indexes
                    $this->checkTableForIndexRecommendations($tableName);
                }
            }
        } catch (\Exception $e) {
            $this->warn("Could not check database indexes: " . $e->getMessage());
        }
    }

    protected function checkTableForIndexRecommendations(string $tableName): void
    {
        // This is a simplified check - in practice, you'd analyze query patterns
        // For now, just check if large tables have any indexes beyond primary key

        try {
            if (DB::getDriverName() === 'mysql') {
                $indexes = DB::select("
                    SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
                    FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                    ORDER BY INDEX_NAME, SEQ_IN_INDEX
                ", [DB::getDatabaseName(), $tableName]);

                $indexCount = count(array_unique(array_column($indexes, 'INDEX_NAME')));

                // If table has only primary key index, it might need more
                if ($indexCount <= 1) {
                    $this->addIssue('Database', 'potential_missing_indexes', [
                        'table' => $tableName,
                        'message' => "Large table '{$tableName}' has minimal indexes. Consider adding indexes on frequently queried columns"
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Skip if can't query indexes
        }
    }

    protected function checkInefficientQueries(): void
    {
        $controllerPath = app_path('Http/Controllers');
        $modelPath = $this->config['models_dir'];

        $paths = [$controllerPath, $modelPath];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for SELECT * queries
                    $this->checkSelectAllQueries($content, $file->getPathname());

                    // Check for queries in loops
                    $this->checkQueriesInLoops($content, $file->getPathname());
                }
            }
        }
    }

    protected function checkSelectAllQueries(string $content, string $filePath): void
    {
        // Look for SELECT * patterns
        $selectAllPatterns = [
            '/select\(/\s*\*\s*\)/',
            '/DB::select\([^)]*\*\s*/',
            '/->get\(\s*\*\s*\)/',
        ];

        foreach ($selectAllPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $lineNumber = $this->getLineNumberFromString($content, $offset);

                    $this->addIssue('Performance', 'select_all_query', [
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'code' => trim(substr($content, $offset, 60)),
                        'message' => "SELECT * query detected. Consider selecting only needed columns for better performance"
                    ]);
                }
            }
        }
    }

    protected function checkQueriesInLoops(string $content, string $filePath): void
    {
        // Look for database queries inside loops
        $loopQueryPatterns = [
            '/for\s*\([^}]*\{[^}]*DB::/',
            '/foreach\s*\([^}]*\{[^}]*DB::/',
            '/while\s*\([^}]*\{[^}]*DB::/',
            '/for\s*\([^}]*:\s*[^}]*DB::/',
            '/foreach\s*\([^}]*:\s*[^}]*DB::/',
        ];

        foreach ($loopQueryPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $lineNumber = $this->getLineNumberFromString($content, $offset);

                    $this->addIssue('Performance', 'query_in_loop', [
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'code' => trim(substr($content, $offset, 80)),
                        'message' => "Database query detected inside a loop. This can cause performance issues - consider restructuring the code"
                    ]);
                }
            }
        }
    }

    protected function checkCodeQuality(): void
    {
        $this->info('');
        $this->info('Checking Code Quality');
        $this->info('=====================');

        // Check namespace usage
        $this->checkNamespaces();

        // Check naming conventions
        $this->checkNamingConventions();

        // Check for deprecated features
        $this->checkDeprecatedFeatures();

        // Check for code smells
        $this->checkCodeSmells();

        // Apply fixes if requested

        if ($this->option('fix') && !$this->option('dry-run')) {
            $this->fixCodeQualityIssues();
        }
    }

    protected function fixCodeQualityIssues(): void
    {
        $this->info('');
        $this->info('Fixing Code Quality Issues');
        $this->info('===========================');

        $fixedCount = 0;

        // Fix class naming issues
        if ($this->config['code_quality']['check_class_naming'] ?? true) {
            foreach ($this->issues as $key => $issue) {
                if ($issue['type'] === 'invalid_class_name' && isset($issue['data']['file'])) {
                    if ($this->fixClassName($issue['data']['file'], $issue['data']['class'])) {
                        $fixedCount++;
                        unset($this->issues[$key]); // Remove from issues array
                    }
                }
            }
        }

        if ($fixedCount > 0) {
            $this->info("Fixed {$fixedCount} class naming issues");
            $this->stats['fixes_applied'] += $fixedCount;
        }
    }

    protected function fixClassName(string $filePath, string $currentClassName): bool
    {
        try {
            $content = file_get_contents($filePath);

            // Convert to PascalCase
            $pascalCaseName = Str::studly($currentClassName);

            if ($pascalCaseName === $currentClassName) {
                // Already in PascalCase
                return false;
            }

            // Replace class declaration
            $pattern = '/(class\s+)' . preg_quote($currentClassName, '/') . '(\s+extends|\s+implements|\s*\{)/';
            $replacement = '${1}' . $pascalCaseName . '${2}';

            if (preg_match($pattern, $content)) {
                $newContent = preg_replace($pattern, $replacement, $content);

                if ($newContent !== $content) {
                    file_put_contents($filePath, $newContent);
                    $this->line("  ✅ Fixed class name: {$currentClassName} → {$pascalCaseName} in {$filePath}");
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->error("Failed to fix class name in {$filePath}: " . $e->getMessage());
            return false;
        }
    }

    protected function checkNamespaces(): void
    {
        $paths = [
            $this->config['models_dir'],
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
        ];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for missing namespace
                    if (!preg_match('/^<\?php\s+namespace\s+[A-Za-z\\\\]+;/m', $content)) {
                        $this->addIssue('Code Quality', 'missing_namespace', [
                            'file' => $file->getPathname(),
                            'message' => "PHP file is missing a namespace declaration"
                        ]);
                    }

                    // Check for proper namespace structure
                    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                        $namespace = $matches[1];
                        $expectedNamespace = $this->getExpectedNamespace($file->getPathname());

                        if ($namespace !== $expectedNamespace) {
                            $this->addIssue('Code Quality', 'incorrect_namespace', [
                                'file' => $file->getPathname(),
                                'current' => $namespace,
                                'expected' => $expectedNamespace,
                                'message' => "Namespace '{$namespace}' doesn't match expected '{$expectedNamespace}' based on file path"
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function getExpectedNamespace(string $filePath): string
    {
        $appPath = app_path();
        $relativePath = str_replace($appPath . '/', '', $filePath);
        $relativePath = preg_replace('/\.php$/', '', $relativePath);

        return 'App\\' . str_replace('/', '\\', $relativePath);
    }

    protected function checkNamingConventions(): void
    {
        $paths = [
            $this->config['models_dir'],
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
        ];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    $className = $file->getFilenameWithoutExtension();

                    // Check class naming (PascalCase)
                    if ($this->config['code_quality']['check_class_naming'] ?? true) {
                        if (!preg_match('/^class\s+[A-Z][a-zA-Z0-9]*(\s+extends|\s+implements|\s*\{)/', $content)) {
                            $this->addIssue('Code Quality', 'invalid_class_name', [
                                'file' => $file->getPathname(),
                                'class' => $className,
                                'message' => "Class name '{$className}' should use PascalCase naming convention"
                            ]);
                        }
                    }

                    // Check method naming (camelCase)
                    if ($this->config['code_quality']['check_method_naming'] ?? true) {
                    if (preg_match_all('/public\s+function\s+([A-Z_][a-zA-Z0-9_]*)\(/', $content, $matches)) {
                        foreach ($matches[1] as $methodName) {
                            if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $methodName)) {
                                $this->addIssue('Code Quality', 'invalid_method_name', [
                                    'file' => $file->getPathname(),
                                    'method' => $methodName,
                                    'message' => "Method name '{$methodName}' should use camelCase naming convention"
                                ]);
                            }
                        }
                    }

                    // Check variable naming
                    $this->checkVariableNaming($content, $file->getPathname());
                }
            }
        }
    }

    protected function checkVariableNaming(string $content, string $filePath): void
    {
        // Check for non-camelCase variable names in methods
        if (preg_match_all('/\$([A-Z_][a-zA-Z0-9_]*)\s*=/', $content, $matches)) {
            foreach ($matches[1] as $variableName) {
                // Skip constants and special cases
                if (strtoupper($variableName) === $variableName || in_array($variableName, ['_token', '_method'])) {
                    continue;
                }

                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $variableName)) {
                    $this->addIssue('Code Quality', 'invalid_variable_name', [
                        'file' => $file->getPathname(),
                        'variable' => $variableName,
                        'message' => "Variable name '{$variableName}' should use camelCase naming convention"
                    ]);
                }
            }
        }
    }

    protected function checkDeprecatedFeatures(): void
    {
        $paths = [
            $this->config['models_dir'],
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
            app_path('Http/Middleware'),
        ];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for deprecated Laravel features
                    $deprecatedPatterns = [
                        'Input::' => 'Input facade is deprecated, use Request instead',
                        'Route::controller' => 'Route::controller is deprecated in Laravel 8+',
                        'str_' => 'str_ helper functions are deprecated, use Str:: instead',
                        'array_' => 'array_ helper functions are deprecated, use Arr:: instead',
                    ];

                    foreach ($deprecatedPatterns as $pattern => $message) {
                        if (preg_match("/{$pattern}/", $content)) {
                            $this->addIssue('Code Quality', 'deprecated_feature', [
                                'file' => $file->getPathname(),
                                'feature' => $pattern,
                                'message' => $message
                            ]);
                        }
                    }

                    // Check for old PHP features that should be avoided
                    if (preg_match('/\bvar\s+\$/m', $content)) {
                        $this->addIssue('Code Quality', 'old_php_syntax', [
                            'file' => $file->getPathname(),
                            'message' => "Using 'var' keyword instead of visibility modifiers (public, private, protected)"
                        ]);
                    }
                }
            }
        }
    }

    protected function checkCodeSmells(): void
    {
        $paths = [
            $this->config['models_dir'],
            app_path('Http/Controllers'),
        ];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for long methods
                    $this->checkMethodLength($content, $file->getPathname());

                    // Check for long classes
                    $this->checkClassLength($content, $file->getPathname());

                    // Check for duplicate code patterns
                    $this->checkDuplicateCode($content, $file->getPathname());

                    // Check for unused imports
                    $this->checkUnusedImports($content, $file->getPathname());
                }
            }
        }
    }

    protected function checkMethodLength(string $content, string $filePath): void
    {
        if (preg_match_all('/public\s+function\s+\w+\([^}]*\}(?=\s*public|\s*protected|\s*private|\s*})/s', $content, $matches)) {
            foreach ($matches[0] as $method) {
                $lineCount = substr_count($method, "\n") + 1;

                if ($lineCount > 50) {
                    $this->addIssue('Code Quality', 'long_method', [
                        'file' => $filePath,
                        'lines' => $lineCount,
                        'message' => "Method is {$lineCount} lines long. Consider breaking it into smaller methods (recommended: < 30 lines)"
                    ]);
                }
            }
        }
    }

    protected function checkClassLength(string $content, string $filePath): void
    {
        $lineCount = substr_count($content, "\n") + 1;

        if ($lineCount > 300) {
            $this->addIssue('Code Quality', 'long_class', [
                'file' => $filePath,
                'lines' => $lineCount,
                'message' => "Class is {$lineCount} lines long. Consider splitting into smaller classes (recommended: < 200 lines)"
            ]);
        }
    }

    protected function checkDuplicateCode(string $content, string $filePath): void
    {
        // Simple duplicate line detection
        $lines = explode("\n", $content);
        $lineCounts = array_count_values($lines);

        $duplicateLines = array_filter($lineCounts, function($count) {
            return $count > 3; // More than 3 identical lines
        });

        if (!empty($duplicateLines)) {
            foreach ($duplicateLines as $line => $count) {
                $line = trim($line);
                if (strlen($line) > 20) { // Only report meaningful duplicate lines
                    $this->addIssue('Code Quality', 'duplicate_code', [
                        'file' => $filePath,
                        'line' => $line,
                        'count' => $count,
                        'message' => "Line appears {$count} times: '{$line}'. Consider extracting to a method or constant"
                    ]);
                }
            }
        }
    }

    protected function checkUnusedImports(string $content, string $filePath): void
    {
        // Extract use statements
        if (preg_match_all('/^use\s+([^;]+);$/m', $content, $matches)) {
            $imports = $matches[1];

            foreach ($imports as $import) {
                // Get the class name from the import
                $className = basename(str_replace('\\', '/', $import));

                // Check if the class is used in the file
                if (!preg_match('/\b' . preg_quote($className, '/') . '\b/', $content)) {
                    $this->addIssue('Code Quality', 'unused_import', [
                        'file' => $filePath,
                        'import' => $import,
                        'message' => "Unused import: {$import}"
                    ]);
                }
            }
        }
    }

    protected function syncMigrationsFromDatabase(): void
    {
        $this->info('');
        $this->info('🔄 Migration Synchronization');
        $this->info('===========================');

        if (!$this->confirm('This will generate fresh migrations from your current database schema. Continue?')) {
            $this->warn('Operation cancelled.');
            return;
        }

        // Step 1: Export data if requested
        if ($this->confirm('Export current data before proceeding? (Recommended)')) {
            $this->exportDatabaseData();
        }

        // Step 2: Analyze current database schema
        $this->info('Analyzing current database schema...');
        $tables = $this->getAllTables();

        // Step 3: Clean up old migrations
        $this->cleanupMigrationFiles();

        // Step 4: Generate fresh migrations
        $this->generateMigrationsFromSchema($tables);

        $this->info('');
        $this->info('✅ Migration synchronization complete!');
        $this->info('Next steps:');
        $this->info('1. Review the generated migrations in database/migrations/');
        $this->info('2. Run: php artisan migrate:fresh');
        $this->info('3. If you exported data, run: php artisan model:schema-check --import-data');
    }

    protected function exportDatabaseData(): void
    {
        $this->info('');
        $this->info('📤 Exporting Database Data');
        $this->info('=========================');

        $exportPath = database_path('exports');
        if (!File::exists($exportPath)) {
            File::makeDirectory($exportPath, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $exportFile = "{$exportPath}/data_export_{$timestamp}.sql.gz";

        try {
            $tables = $this->getAllTables();
            $this->exportDatabaseDataToCompressedFile($exportFile, $tables);

            $this->info("✅ Data exported to: {$exportFile}");
            $this->info("💡 To import later: php artisan model:schema-check --import-data");

        } catch (\Exception $e) {
            $this->error("❌ Data export failed: " . $e->getMessage());
        }
    }

    protected function importDatabaseData(): void
    {
        $this->info('');
        $this->info('📥 Importing Database Data');
        $this->info('==========================');

        $exportPath = database_path('exports');

        if (!File::exists($exportPath)) {
            $this->error("❌ No exports directory found. Run --export-data first.");
            return;
        }

        $exportFiles = File::files($exportPath);

        if (empty($exportFiles)) {
            $this->error("❌ No export files found in {$exportPath}");
            return;
        }

        // Get the most recent export file (prioritize .sql.gz, fallback to .sql)
        $exportFiles = collect(File::files($exportPath))
            ->sortByDesc(function($file) {
                return $file->getMTime();
            });

        $latestExport = $exportFiles->first(function($file) {
            return str_ends_with($file->getFilename(), '.sql.gz') || str_ends_with($file->getFilename(), '.sql');
        });

        if (!$latestExport) {
            $this->error("❌ No export files found in {$exportPath}");
            return;
        }

        if (!$this->confirm("Import data from: {$latestExport->getFilename()}?")) {
            return;
        }

        try {
            $this->importDatabaseDataFromFile($latestExport->getPathname());

            $this->info("✅ Data imported successfully from: {$latestExport->getFilename()}");

        } catch (\Exception $e) {
            $this->error("❌ Data import failed: " . $e->getMessage());
        }
    }

    protected function cleanupMigrationFiles(): void
    {
        $this->info('');
        $this->info('🧹 Cleaning Up Migration Files');
        $this->info('=============================');

        $migrationPath = database_path('migrations');

        if (!File::exists($migrationPath)) {
            $this->warn("No migrations directory found.");
            return;
        }

        $migrationFiles = File::files($migrationPath);

        if (empty($migrationFiles)) {
            $this->info("No migration files to clean up.");
            return;
        }

        // Create backup directory
        $backupPath = database_path('migrations_backup_' . date('Y-m-d_H-i-s'));
        File::makeDirectory($backupPath, 0755, true);

        $this->info("Backing up {$migrationFiles->count()} migration files...");

        // Move files to backup
        foreach ($migrationFiles as $file) {
            $newPath = $backupPath . '/' . $file->getFilename();
            File::move($file->getPathname(), $newPath);
        }

        $this->info("✅ Migration files backed up to: {$backupPath}");
        $this->info("🗑️  Migration directory cleared.");
    }

    protected function getAllTables(): array
    {
        try {
            $databaseName = DB::getDatabaseName();

            if (DB::getDriverName() === 'mysql') {
                $tables = DB::select("
                    SELECT TABLE_NAME as name
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME NOT IN ('migrations', 'failed_jobs', 'cache', 'sessions', 'jobs')
                    ORDER BY TABLE_NAME
                ", [$databaseName]);

                return array_column($tables, 'name');
            }

            return [];
        } catch (\Exception $e) {
            $this->error("Could not retrieve table list: " . $e->getMessage());
            return [];
        }
    }

    protected function generateMigrationsFromSchema(array $tables): void
    {
        $this->info('');
        $this->info('🏗️  Generating Fresh Migrations');
        $this->info('==============================');

        $migrationPath = database_path('migrations');

        if (!File::exists($migrationPath)) {
            File::makeDirectory($migrationPath, 0755, true);
        }

        foreach ($tables as $tableName) {
            $this->generateMigrationForTable($tableName);
        }

        $this->info("✅ Generated migrations for " . count($tables) . " tables.");
    }

    protected function generateMigrationForTable(string $tableName): void
    {
        try {
            $databaseName = DB::getDatabaseName();
            $columns = [];

            if (DB::getDriverName() === 'mysql') {
                $columnData = DB::select("
                    SELECT
                        COLUMN_NAME,
                        DATA_TYPE,
                        IS_NULLABLE,
                        COLUMN_DEFAULT,
                        CHARACTER_MAXIMUM_LENGTH,
                        NUMERIC_PRECISION,
                        NUMERIC_SCALE,
                        COLUMN_KEY,
                        EXTRA
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                    ORDER BY ORDINAL_POSITION
                ", [$databaseName, $tableName]);

                foreach ($columnData as $col) {
                    $columns[$col->COLUMN_NAME] = [
                        'type' => $col->DATA_TYPE,
                        'nullable' => $col->IS_NULLABLE === 'YES',
                        'default' => $col->COLUMN_DEFAULT,
                        'length' => $col->CHARACTER_MAXIMUM_LENGTH,
                        'precision' => $col->NUMERIC_PRECISION,
                        'scale' => $col->NUMERIC_SCALE,
                        'key' => $col->COLUMN_KEY,
                        'extra' => $col->EXTRA
                    ];
                }
            }

            // Get indexes
            $indexes = $this->getTableIndexes($tableName);

            // Get foreign keys
            $foreignKeys = $this->getForeignKeyConstraints($tableName);

            // Generate migration content
            $migrationContent = $this->generateMigrationContent($tableName, $columns, $indexes, $foreignKeys);

            // Create migration file
            $timestamp = date('Y_m_d_His');
            $filename = "{$timestamp}_create_{$tableName}_table.php";
            $filePath = database_path("migrations/{$filename}");

            File::put($filePath, $migrationContent);

            $this->info("  📄 Created: {$filename}");

        } catch (\Exception $e) {
            $this->error("Failed to generate migration for {$tableName}: " . $e->getMessage());
        }
    }

    protected function getTableIndexes(string $tableName): array
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                $indexes = DB::select("
                    SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
                    FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                    ORDER BY INDEX_NAME, SEQ_IN_INDEX
                ", [DB::getDatabaseName(), $tableName]);

                $indexGroups = [];
                foreach ($indexes as $index) {
                    $indexGroups[$index->INDEX_NAME][] = $index;
                }

                return $indexGroups;
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function generateMigrationContent(string $tableName, array $columns, array $indexes, array $foreignKeys): string
    {
        $className = Str::studly($tableName) . 'Table';

        $content = "<?php\n\n";
        $content .= "use Illuminate\Database\Migrations\Migration;\n";
        $content .= "use Illuminate\Database\Schema\Blueprint;\n";
        $content .= "use Illuminate\Support\Facades\Schema;\n\n";
        $content .= "return new class extends Migration\n";
        $content .= "{\n";
        $content .= "    public function up(): void\n";
        $content .= "    {\n";
        $content .= "        Schema::create('{$tableName}', function (Blueprint \$table) {\n";

        // Generate column definitions
        foreach ($columns as $columnName => $columnInfo) {
            $content .= $this->generateColumnDefinition($columnName, $columnInfo);
        }

        // Generate indexes
        foreach ($indexes as $indexName => $indexColumns) {
            if ($indexName === 'PRIMARY') continue; // Primary key is handled by id column

            $columnNames = array_column($indexColumns, 'COLUMN_NAME');
            $isUnique = $indexColumns[0]->NON_UNIQUE == 0;

            if (count($columnNames) === 1) {
                $column = $columnNames[0];
                if ($isUnique) {
                    $content .= "            \$table->unique('{$column}');\n";
                } else {
                    $content .= "            \$table->index('{$column}');\n";
                }
            } else {
                $columnsStr = "['" . implode("', '", $columnNames) . "']";
                if ($isUnique) {
                    $content .= "            \$table->unique({$columnsStr});\n";
                } else {
                    $content .= "            \$table->index({$columnsStr});\n";
                }
            }
        }

        // Generate foreign keys
        foreach ($foreignKeys as $fk) {
            $content .= "            \$table->foreign('{$fk['column']}')->references('{$fk['references']}');\n";
        }

        $content .= "        });\n";
        $content .= "    }\n\n";
        $content .= "    public function down(): void\n";
        $content .= "    {\n";
        $content .= "        Schema::dropIfExists('{$tableName}');\n";
        $content .= "    }\n";
        $content .= "};\n";

        return $content;
    }

    protected function generateColumnDefinition(string $columnName, array $columnInfo): string
    {
        $definition = "            \$table->";

        // Handle special columns
        if ($columnName === 'id' && $columnInfo['key'] === 'PRI') {
            return "            \$table->id();\n";
        }

        if ($columnName === 'created_at') {
            return "            \$table->timestamps();\n";
        }

        if ($columnName === 'updated_at') {
            return ""; // Handled by timestamps()
        }

        // Map MySQL types to Laravel migration types
        $type = $this->mapColumnType($columnInfo['type'], $columnInfo);

        $definition .= $type;

        // Add length/precision for applicable types
        if (isset($columnInfo['length']) && $columnInfo['length'] && in_array($columnInfo['type'], ['varchar', 'char'])) {
            $definition .= "({$columnInfo['length']})";
        }

        if (isset($columnInfo['precision']) && $columnInfo['precision'] && in_array($columnInfo['type'], ['decimal', 'float', 'double'])) {
            $scale = $columnInfo['scale'] ?? 0;
            $definition .= "({$columnInfo['precision']}, {$scale})";
        }

        // Add nullable
        if ($columnInfo['nullable']) {
            $definition .= "->nullable()";
        }

        // Add default
        if ($columnInfo['default'] !== null) {
            $default = $columnInfo['default'];
            if (is_string($default)) {
                $definition .= "->default('{$default}')";
            } elseif (is_numeric($default)) {
                $definition .= "->default({$default})";
            }
        }

        // Add auto increment for primary keys
        if ($columnInfo['extra'] === 'auto_increment') {
            $definition .= "->autoIncrement()";
        }

        $definition .= ";\n";

        return $definition;
    }

    protected function mapColumnType(string $mysqlType, array $columnInfo): string
    {
        $typeMap = [
            'varchar' => 'string',
            'char' => 'char',
            'text' => 'text',
            'mediumtext' => 'mediumText',
            'longtext' => 'longText',
            'int' => 'integer',
            'bigint' => 'bigInteger',
            'smallint' => 'smallInteger',
            'tinyint' => 'tinyInteger',
            'decimal' => 'decimal',
            'float' => 'float',
            'double' => 'double',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
            'time' => 'time',
            'json' => 'json',
            'binary' => 'binary',
            'varbinary' => 'binary',
        ];

        // Special handling for boolean (tinyint(1))
        if ($mysqlType === 'tinyint' && $columnInfo['length'] == 1) {
            return 'boolean';
        }

        return $typeMap[$mysqlType] ?? 'string';
    }

    protected function generateDataExportSQL(array $tables): string
    {
        $sql = "-- Data Export Generated on " . date('Y-m-d H:i:s') . "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $tableName) {
            $sql .= "-- Exporting data from {$tableName}\n";

            try {
                $data = DB::table($tableName)->get();

                if ($data->isEmpty()) {
                    $sql .= "-- Table {$tableName} is empty\n\n";
                    continue;
                }

                foreach ($data as $row) {
                    $columns = [];
                    $values = [];

                    foreach ($row as $column => $value) {
                        $columns[] = "`{$column}`";

                        if ($value === null) {
                            $values[] = "NULL";
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }

                    $columnsStr = implode(', ', $columns);
                    $valuesStr = implode(', ', $values);

                    $sql .= "INSERT INTO `{$tableName}` ({$columnsStr}) VALUES ({$valuesStr});\n";
                }

                $sql .= "\n";

            } catch (\Exception $e) {
                $sql .= "-- Error exporting {$tableName}: " . $e->getMessage() . "\n\n";
            }
        }

        return $sql;
    }

    protected function exportDatabaseDataToCompressedFile(string $filePath, array $tables): void
    {
        $gzFile = gzopen($filePath, 'w9'); // w9 = maximum compression

        if (!$gzFile) {
            throw new \Exception("Could not create compressed file: {$filePath}");
        }

        try {
            // Write header
            $header = "-- Data Export Generated on " . date('Y-m-d H:i:s') . "\n";
            $header .= "-- Compressed with gzip\n";
            $header .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            gzwrite($gzFile, $header);

            $this->info('Exporting tables...');

            foreach ($tables as $tableName) {
                gzwrite($gzFile, "-- Exporting data from {$tableName}\n");

                try {
                    $data = DB::table($tableName)->get();

                    if ($data->isEmpty()) {
                        gzwrite($gzFile, "-- Table {$tableName} is empty\n\n");
                        continue;
                    }

                    $this->info("  📄 Exporting {$data->count()} rows from {$tableName}");

                    foreach ($data as $row) {
                        $columns = [];
                        $values = [];

                        foreach ($row as $column => $value) {
                            $columns[] = "`{$column}`";

                            if ($value === null) {
                                $values[] = "NULL";
                            } elseif (is_numeric($value)) {
                                $values[] = $value;
                            } else {
                                $values[] = "'" . addslashes($value) . "'";
                            }
                        }

                        $columnsStr = implode(', ', $columns);
                        $valuesStr = implode(', ', $values);

                        gzwrite($gzFile, "INSERT INTO `{$tableName}` ({$columnsStr}) VALUES ({$valuesStr});\n");
                    }

                    gzwrite($gzFile, "\n");

                } catch (\Exception $e) {
                    $errorMsg = "-- Error exporting {$tableName}: " . $e->getMessage() . "\n\n";
                    gzwrite($gzFile, $errorMsg);
                    $this->warn("Error exporting {$tableName}: " . $e->getMessage());
                }
            }

            gzwrite($gzFile, "SET FOREIGN_KEY_CHECKS = 1;\n");

        } finally {
            gzclose($gzFile);
        }

        // Get file size for reporting
        $fileSize = filesize($filePath);
        $this->info("📊 Compressed export size: " . $this->formatBytes($fileSize));
    }

    protected function importDatabaseDataFromFile(string $filePath): void
    {
        $isCompressed = str_ends_with($filePath, '.gz');

        if ($isCompressed) {
            $file = gzopen($filePath, 'r');
            $this->info('📖 Reading compressed export file...');
        } else {
            $file = fopen($filePath, 'r');
            $this->info('📖 Reading export file...');
        }

        if (!$file) {
            throw new \Exception("Could not open file: {$filePath}");
        }

        try {
            $buffer = '';
            $statementCount = 0;

            $this->info('Executing import statements...');

            while (!feof($file)) {
                if ($isCompressed) {
                    $line = gzgets($file);
                } else {
                    $line = fgets($file);
                }

                if ($line === false) break;

                $line = trim($line);

                // Skip comments and empty lines
                if (empty($line) || str_starts_with($line, '--')) {
                    continue;
                }

                $buffer .= $line;

                // Check if we have a complete statement (ends with semicolon)
                if (str_ends_with($line, ';')) {
                    $statement = trim($buffer);

                    if (!empty($statement)) {
                        try {
                            DB::statement($statement);
                            $statementCount++;

                            // Progress indicator every 100 statements
                            if ($statementCount % 100 === 0) {
                                $this->info("  📊 Processed {$statementCount} statements...");
                            }
                        } catch (\Exception $e) {
                            $this->warn("Failed to execute statement: " . substr($statement, 0, 100) . "... - " . $e->getMessage());
                        }
                    }

                    $buffer = '';
                }
            }

            $this->info("✅ Successfully executed {$statementCount} statements");

        } finally {
            if ($isCompressed) {
                gzclose($file);
            } else {
                fclose($file);
            }
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}