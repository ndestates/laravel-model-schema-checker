<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class CheckFilamentFormsCommand
{
    protected Logger $logger;
    protected CheckConfig $config;

    public function __construct(Logger $logger, CheckConfig $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function execute(array $flags): void
    {
        $this->logger->section('FILAMENT FORMS & TABLES RELATIONSHIP CHECK');
        
        // Check if a specific resource is requested
        $specificResource = $flags['resource'] ?? null;
        
        if ($specificResource) {
            // Handle different resource name formats
            if (!str_contains($specificResource, '\\')) {
                // If no backslashes, assume it's just the class name in the standard Filament location
                $specificResource = 'App\\Filament\\Admin\\Resources\\' . $specificResource;
            }
            $this->logger->info("Checking specific resource: {$specificResource}");
            try {
                $this->checkResource($specificResource);
            } catch (Throwable $e) {
                $this->logger->error("Error checking resource {$specificResource}: " . $e->getMessage());
            }
            return;
        }
        
        $filamentPath = app_path('Filament');
        $resources = $this->findFilamentResources($filamentPath);

        foreach ($resources as $resourceClass) {
            $this->logger->info("Checking Resource: {$resourceClass}");
            try {
                $this->checkResource($resourceClass);
            } catch (Throwable $e) {
                $this->logger->error("Error checking resource {$resourceClass}: " . $e->getMessage());
            }
        }
    }

    private function findFilamentResources(string $path): array
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
                    $this->logger->warning("Skipping file with invalid class: {$file->getPathname()}");
                }
            } catch (Throwable $e) {
                $this->logger->error("Cannot process file {$file->getPathname()}: " . $e->getMessage());
                // Continue with other files
            }
        }

        return $resources;
    }

    private function checkResource(string $resourceClass): void
    {
        try {
            // Use reflection to get the model class without instantiating the resource
            $reflection = new ReflectionClass($resourceClass);
            $getModelMethod = $reflection->getMethod('getModel');
            
            if ($getModelMethod->isStatic()) {
                $modelClass = $resourceClass::getModel();
                $model = new $modelClass();

                $this->checkMethods($resourceClass, $model, ['form', 'table']);
            } else {
                $this->logger->error("Cannot check resource {$resourceClass}: getModel() is not static");
            }
        } catch (Throwable $e) {
            $this->logger->error("Cannot check resource {$resourceClass}: " . $e->getMessage());
            // Continue with other resources instead of failing completely
        }
    }

    private function checkMethods(string $resourceClass, Model $model, array $methodNames): void
    {
        $reflection = new ReflectionClass($resourceClass);

        foreach ($methodNames as $methodName) {
            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                if ($method->isStatic()) {
                    $this->findAndCheckRelationshipsInMethod($method, $model, $resourceClass);
                }
            }
        }
    }

    private function findAndCheckRelationshipsInMethod(ReflectionMethod $method, Model $model, string $resourceClass): void
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
                $this->validateRelationship($model, $relationshipName, $resourceClass, $filePath, $lineNumber);
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
                $this->validateSelectRelationship($model, $relationshipName, $fieldName, $resourceClass, $filePath, $lineNumber, $componentType);
            }
        }
    }

    private function validateRelationship(Model $model, string $relationshipName, string $resourceClass, string $filePath, int $lineNumber): void
    {
        $modelClass = get_class($model);
        if (!method_exists($model, $relationshipName)) {
            $this->logger->logIssue([
                'level' => 'error',
                'message' => "Broken Relationship: Method '{$relationshipName}' not found on model '{$modelClass}'.",
                'file' => $filePath,
                'line' => $lineNumber,
                'suggestion' => "Add the '{$relationshipName}' method to the '{$modelClass}' model, ensuring it defines a valid Eloquent relationship."
            ]);
            return;
        }

        try {
            $relation = $model->$relationshipName();
            if (!$relation instanceof Relation) {
                $this->logger->logIssue([
                    'level' => 'error',
                    'message' => "Invalid Relationship Return Type: Method '{$relationshipName}' on model '{$modelClass}' does not return a valid Eloquent Relation object.",
                    'file' => (new ReflectionClass($modelClass))->getFileName(),
                    'line' => (new ReflectionMethod($modelClass, $relationshipName))->getStartLine(),
                    'suggestion' => "Ensure the '{$relationshipName}' method in '{$modelClass}' returns a relationship instance (e.g., \$this->belongsTo(...))."
                ]);
            }
        } catch (Throwable $e) {
            $this->logger->logIssue([
                'level' => 'error',
                'message' => "Error executing relationship method '{$relationshipName}' on model '{$modelClass}': " . $e->getMessage(),
                'file' => $filePath,
                'line' => $lineNumber,
                'suggestion' => "Debug the '{$relationshipName}' method in the '{$modelClass}' model. The error occurs when the method is called."
            ]);
        }
    }

    private function validateSelectRelationship(Model $model, string $relationshipName, string $fieldName, string $resourceClass, string $filePath, int $lineNumber, string $componentType = 'Select'): void
    {
        $modelClass = get_class($model);
        
        // First check if the relationship method exists
        if (!method_exists($model, $relationshipName)) {
            $this->logger->logIssue([
                'level' => 'error',
                'message' => "Broken {$componentType} Relationship: {$componentType} field '{$fieldName}' references relationship '{$relationshipName}' which doesn't exist on model '{$modelClass}'.",
                'file' => $filePath,
                'line' => $lineNumber,
                'suggestion' => "Add the '{$relationshipName}' relationship method to the '{$modelClass}' model, or fix the relationship name in the {$componentType} field."
            ]);
            return;
        }

        try {
            $relation = $model->$relationshipName();
            
            // Check if relationship returns null
            if ($relation === null) {
                $this->logger->logIssue([
                    'level' => 'error',
                    'message' => "Null Relationship: {$componentType} field '{$fieldName}' relationship '{$relationshipName}' returns null on model '{$modelClass}'. This will cause runtime errors like 'Argument #1 must be of type Relation, null given'.",
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'suggestion' => "Fix the '{$relationshipName}' method in '{$modelClass}' to return a valid relationship object, not null."
                ]);
                return;
            }
            
            // Check if it's a valid Relation instance
            if (!$relation instanceof Relation) {
                $this->logger->logIssue([
                    'level' => 'error',
                    'message' => "Invalid {$componentType} Relationship: {$componentType} field '{$fieldName}' relationship '{$relationshipName}' on model '{$modelClass}' does not return a valid Eloquent Relation object.",
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'suggestion' => "Ensure the '{$relationshipName}' method in '{$modelClass}' returns a relationship instance (e.g., \$this->belongsTo(...), \$this->hasMany(...))."
                ]);
                return;
            }
            
            // Additional check: try to build the query to catch other issues
            try {
                $query = $relation->getQuery();
                if ($query === null) {
                    $this->logger->logIssue([
                        'level' => 'warning',
                        'message' => "Query Build Issue: {$componentType} field '{$fieldName}' relationship '{$relationshipName}' query returns null on model '{$modelClass}'.",
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'suggestion' => "Check the '{$relationshipName}' relationship definition in '{$modelClass}' for query building issues."
                    ]);
                }
            } catch (Throwable $e) {
                $this->logger->logIssue([
                    'level' => 'warning',
                    'message' => "Relationship Query Error: {$componentType} field '{$fieldName}' relationship '{$relationshipName}' failed to build query: " . $e->getMessage(),
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'suggestion' => "Debug the '{$relationshipName}' relationship in '{$modelClass}'. The relationship may have invalid foreign keys or table references."
                ]);
            }
            
        } catch (Throwable $e) {
            $this->logger->logIssue([
                'level' => 'error',
                'message' => "Error in {$componentType} Relationship: {$componentType} field '{$fieldName}' relationship '{$relationshipName}' on model '{$modelClass}' threw exception: " . $e->getMessage(),
                'file' => $filePath,
                'line' => $lineNumber,
                'suggestion' => "Fix the '{$relationshipName}' method in the '{$modelClass}' model. The error occurs when the relationship method is called."
            ]);
        }
    }

    private function getClassFromFile(string $path): ?string
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

    private function getLineNumberFromOffset(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
}
