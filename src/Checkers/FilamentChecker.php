<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\File;
use NDEstates\LaravelModelSchemaChecker\Models\CodeImprovement;

class FilamentChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Filament Checker';
    }

    public function getDescription(): string
    {
        return 'Checks Filament forms and relationships for broken integrity';
    }

    public function check(): array
    {
        $this->info('');
        $this->info('Checking Filament Forms & Relationships');
        $this->info('=========================================');

        // Check if Filament package is installed
        if (!class_exists(\Filament\FilamentServiceProvider::class) && !class_exists(\Filament\Resources\Resource::class)) {
            $this->warn('Filament package not found. Make sure Filament is installed: composer require filament/filament');
            return $this->issues;
        }

        $filamentPath = app_path('Filament');
        if (!File::exists($filamentPath)) {
            $this->warn('No Filament directory found. Skipping Filament checks.');
            return $this->issues;
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

        return $this->issues;
    }

    protected function findFilamentResources(string $path): array
    {
        $files = File::allFiles($path);
        $resources = [];

        foreach ($files as $file) {
            try {
                $class = $this->getClassFromFile($file->getPathname());
                if ($class && class_exists($class, false)) { // Don't autoload
                    // Check if Filament is available before checking subclass
                    if (class_exists(\Filament\Resources\Resource::class) && is_subclass_of($class, \Filament\Resources\Resource::class)) {
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
        // Check if Filament is available
        if (!class_exists(\Filament\Resources\Resource::class)) {
            $this->warn("Filament not installed, skipping resource check for {$resourceClass}");
            return;
        }

        try {
            // Assert that the class exists before using ReflectionClass
            if (!class_exists($resourceClass)) {
                $this->addIssue('filament', 'filament_resource_not_found', [
                    'resource_class' => $resourceClass,
                    'message' => "Filament resource class {$resourceClass} does not exist"
                ]);
                return;
            }

            // Use reflection to get the model class without instantiating the resource
            $reflection = new \ReflectionClass($resourceClass);
            $getModelMethod = $reflection->getMethod('getModel');

            if ($getModelMethod->isStatic()) {
                $modelClass = $resourceClass::getModel();
                $model = new $modelClass();

                $this->checkFilamentMethods($resourceClass, $model, ['form', 'table']);
            } else {
                $this->addIssue('filament', 'filament_invalid_resource', [
                    'resource_class' => $resourceClass,
                    'message' => "Cannot check resource {$resourceClass}: getModel() is not static"
                ]);
            }
        } catch (\Throwable $e) {
            $this->addIssue('filament', 'filament_resource_error', [
                'resource_class' => $resourceClass,
                'message' => "Cannot check resource {$resourceClass}: " . $e->getMessage()
            ]);
            // Continue with other resources instead of failing completely
        }
    }

    protected function checkFilamentMethods(string $resourceClass, \Illuminate\Database\Eloquent\Model $model, array $methodNames): void
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

    protected function findAndCheckRelationshipsInFilamentMethod(\ReflectionMethod $method, \Illuminate\Database\Eloquent\Model $model, string $resourceClass): void
    {
        $filePath = $method->getFileName();
        if ($filePath === false) {
            $this->warn("Cannot get file path for method {$method->getName()}");
            return;
        }

        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = file($filePath);
        $content = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        // Check for ->relationship() calls
        if (preg_match_all('/->relationship\(\s*\'([a-zA-Z0-9_]+)\'/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $relationshipName = $match[0];
                $offset = $match[1];
                $lineNumber = $this->getLineNumberFromOffset($content, $offset) + $startLine - 1;
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

    protected function validateFilamentRelationship(\Illuminate\Database\Eloquent\Model $model, string $relationshipName, string $resourceClass, string $filePath, int $lineNumber): void
    {
        $modelClass = get_class($model);
        if (!method_exists($model, $relationshipName)) {
            $this->addIssue('filament', 'filament_broken_relationship', [
                'relationship' => $relationshipName,
                'model' => $modelClass,
                'resource_class' => $resourceClass,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Broken Relationship: Method '{$relationshipName}' not found on model '{$modelClass}'."
            ]);
            return;
        }

        try {
            $relation = $model->$relationshipName();
            if (!$relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                $this->addIssue('filament', 'filament_invalid_relationship', [
                    'relationship' => $relationshipName,
                    'model' => $modelClass,
                    'resource_class' => $resourceClass,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'message' => "Invalid Relationship Return Type: Method '{$relationshipName}' does not return a valid Eloquent Relation object."
                ]);
            }
        } catch (\Throwable $e) {
            $this->addIssue('filament', 'filament_relationship_error', [
                'relationship' => $relationshipName,
                'model' => $modelClass,
                'resource_class' => $resourceClass,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Error executing relationship method '{$relationshipName}': " . $e->getMessage()
            ]);
        }
    }

    protected function validateFilamentSelectRelationship(\Illuminate\Database\Eloquent\Model $model, string $relationshipName, string $fieldName, string $resourceClass, string $filePath, int $lineNumber, string $componentType = 'Select'): void
    {
        $modelClass = get_class($model);

        // First check if the relationship method exists
        if (!method_exists($model, $relationshipName)) {
            $this->addIssue('filament', 'filament_select_broken_relationship', [
                'component_type' => $componentType,
                'field' => $fieldName,
                'relationship' => $relationshipName,
                'model' => $modelClass,
                'resource_class' => $resourceClass,
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
                $this->addIssue('filament', 'filament_null_relationship', [
                    'component_type' => $componentType,
                    'field' => $fieldName,
                    'relationship' => $relationshipName,
                    'model' => $modelClass,
                    'resource_class' => $resourceClass,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'message' => "Null Relationship: Field '{$fieldName}' relationship '{$relationshipName}' returns null."
                ]);
                return;
            }

            // Check if it's a valid Relation instance
            if (!$relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                $this->addIssue('filament', 'filament_invalid_select_relationship', [
                    'component_type' => $componentType,
                    'field' => $fieldName,
                    'relationship' => $relationshipName,
                    'model' => $modelClass,
                    'resource_class' => $resourceClass,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'message' => "Invalid Relationship: Field '{$fieldName}' relationship '{$relationshipName}' does not return a valid Relation object."
                ]);
                return;
            }

        } catch (\Throwable $e) {
            $this->addIssue('filament', 'filament_select_relationship_error', [
                'component_type' => $componentType,
                'field' => $fieldName,
                'relationship' => $relationshipName,
                'model' => $modelClass,
                'resource_class' => $resourceClass,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Error in Relationship: Field '{$fieldName}' relationship '{$relationshipName}' threw exception: " . $e->getMessage()
            ]);
        }
    }

    protected function checkFilamentFormFieldAlignment(\ReflectionMethod $method, \Illuminate\Database\Eloquent\Model $model, string $resourceClass): void
    {
        $filePath = $method->getFileName();
        if ($filePath === false) {
            $this->warn("Cannot get file path for method {$method->getName()}");
            return;
        }

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
            $this->addIssue('filament', 'filament_field_not_in_database', [
                'field' => $fieldName,
                'model' => $modelClass,
                'resource_class' => $resourceClass,
                'table_columns' => $tableColumns,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Field '{$fieldName}' in Filament form does not exist in database table."
            ]);
            return;
        }

        // Check if field is in fillable array (for mass assignment)
        if (!in_array($fieldName, $fillable)) {
            $this->addIssue('filament', 'filament_field_not_fillable', [
                'field' => $fieldName,
                'model' => $modelClass,
                'resource_class' => $resourceClass,
                'fillable' => $fillable,
                'file' => $filePath,
                'line' => $lineNumber,
                'message' => "Field '{$fieldName}' in Filament form is not in the model's fillable array."
            ]);
        }
    }

    protected function getLineNumberFromOffset(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }

    protected function getClassFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Simple regex to find namespace and class
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            $namespace = $namespaceMatch[1];
            if (preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $classMatch)) {
                return $namespace . '\\' . $classMatch[1];
            }
        }

        return null;
    }
}