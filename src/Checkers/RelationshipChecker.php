<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RelationshipChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Relationship Checker';
    }

    public function getDescription(): string
    {
        return 'Validate model relationships and foreign keys';
    }

    public function check(): array
    {
        $this->info('');
        $this->info('Checking Model Relationships');
        $this->info('===========================');

        $modelsPath = $this->config['model_path'] ?? $this->config['model_directories'][0] ?? app_path('Models');

        if (!$this->fileExists($modelsPath)) {
            $this->warn("Models directory not found: {$modelsPath}");
            return $this->issues;
        }

        $modelFiles = $this->getAllFiles($modelsPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkModelRelationshipsForFile($file);
            }
        }

        // Display results summary
        $this->displayResultsSummary();

        return $this->issues;
    }

    protected function checkModelRelationshipsForFile($file): void
    {
        $namespace = $this->getNamespaceFromFile($file->getPathname());
        $className = $namespace . '\\' . pathinfo($file->getFilename(), PATHINFO_FILENAME);

        if (!class_exists($className)) {
            return;
        }

        try {
            $reflection = new \ReflectionClass($className);
            $content = file_get_contents($file->getPathname());

            // Check for relationship methods
            $this->checkRelationshipMethods($reflection, $content, $file->getPathname());

            // Check foreign key constraints
            $this->checkForeignKeyConstraints($className, $file->getPathname());

            // Check relationship naming conventions
            $this->checkRelationshipNaming($reflection, $content, $file->getPathname());
        } catch (\Exception $e) {
            $this->addIssue('relationship', 'reflection_error', [
                'file' => $file->getPathname(),
                'model' => $className,
                'error' => $e->getMessage(),
                'message' => "Could not analyze model relationships due to reflection error"
            ]);
        }
    }

    protected function checkRelationshipMethods(\ReflectionClass $reflection, string $content, string $filePath): void
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

    protected function checkRelationshipReturnTypes(\ReflectionClass $reflection, string $content, string $filePath): void
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
                        $this->addIssue('relationship', 'invalid_relationship_return', [
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

    protected function checkInverseRelationships(\ReflectionClass $reflection, array $relationships, string $filePath): void
    {
        $modelName = $reflection->getShortName();

        // This is a simplified check - in a real implementation, you'd need to analyze
        // the related models to check for inverse relationships
        $hasManyRelationships = array_filter($relationships, function ($rel) {
            return in_array($rel, ['hasMany', 'belongsToMany', 'morphMany', 'morphToMany']);
        });

        if (!empty($hasManyRelationships)) {
            $this->addIssue('relationship', 'missing_inverse_check', [
                'file' => $filePath,
                'model' => $modelName,
                'relationships' => implode(', ', $hasManyRelationships),
                'message' => "Consider checking for inverse relationships in related models for " . implode(', ', $hasManyRelationships)
            ]);
        }
    }

    protected function checkForeignKeyConstraints(string $className, string $filePath): void
    {
        try {
            // Check if the class is abstract before trying to instantiate it
            $reflection = new \ReflectionClass($className);
            if ($reflection->isAbstract()) {
                // Skip abstract classes as they cannot be instantiated
                return;
            }

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
                        $this->addIssue('relationship', 'guarded_foreign_key', [
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
                        $this->addIssue('relationship', 'missing_foreign_key_fillable', [
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

    protected function checkRelationshipNaming(\ReflectionClass $reflection, string $content, string $filePath): void
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
                    $this->addIssue('relationship', 'poor_relationship_naming', [
                        'file' => $filePath,
                        'model' => $modelName,
                        'method' => $methodName,
                        'message' => "Relationship method name '{$methodName}' is too short. Use descriptive names like 'user', 'posts', 'comments'."
                    ]);
                }

                if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $methodName)) {
                    $this->addIssue('relationship', 'invalid_relationship_naming', [
                        'file' => $filePath,
                        'model' => $modelName,
                        'method' => $methodName,
                        'message' => "Relationship method name '{$methodName}' should use camelCase naming convention."
                    ]);
                }
            }
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

    protected function getNamespaceFromFile(string $filePath): string
    {
        $content = $this->getFileContent($filePath);

        if (preg_match('/namespace\s+([^;]+);/i', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Check if a file or directory exists, using Laravel File facade if available
     */
    protected function fileExists(string $path): bool
    {
        return class_exists('\Illuminate\Support\Facades\File') && method_exists('\Illuminate\Support\Facades\File', 'exists')
            ? \Illuminate\Support\Facades\File::exists($path)
            : file_exists($path);
    }

    /**
     * Get all files in a directory, using Laravel File facade if available
     */
    protected function getAllFiles(string $path): array
    {
        if (class_exists('\Illuminate\Support\Facades\File') && method_exists('\Illuminate\Support\Facades\File', 'allFiles')) {
            return \Illuminate\Support\Facades\File::allFiles($path);
        }

        // Fallback to native PHP using RecursiveDirectoryIterator
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file;
            }
        }
        return $files;
    }

    /**
     * Get file content, using Laravel File facade if available
     */
    protected function getFileContent(string $path): string
    {
        return class_exists('\Illuminate\Support\Facades\File') && method_exists('\Illuminate\Support\Facades\File', 'get')
            ? \Illuminate\Support\Facades\File::get($path)
            : file_get_contents($path);
    }

    /**
     * Display results summary for relationship checking
     */
    protected function displayResultsSummary(): void
    {
        $issueCount = count($this->issues);

        if ($issueCount === 0) {
            $this->info('✅ No relationship issues found!');
            return;
        }

        $this->warn("⚠️  Found {$issueCount} relationship issue(s):");

        foreach ($this->issues as $issue) {
            $this->line("  • " . ($issue['message'] ?? $issue['type']));
            if (isset($issue['file'])) {
                $this->line("    📁 " . $issue['file']);
            }
        }

        $this->newLine();
    }
}
