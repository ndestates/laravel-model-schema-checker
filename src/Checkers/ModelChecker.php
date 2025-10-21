<?php

class ModelChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Model Checker';
    }

    public function getDescription(): string
    {
        return 'Check model fillable properties, relationships, and table integrity';
    }

    protected function getRuleName(): ?string
    {
        return 'model_fillable_check';
    }DEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\File;
use NDEstates\LaravelModelSchemaChecker\Models\CodeImprovement;

class ModelChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Model Checker';
    }

    public function getDescription(): string
    {
        return 'Checks Eloquent models for fillable properties, table existence, and schema alignment';
    }

    public function check(): array
    {
        $this->info('');
        $this->info('Checking Models');
        $this->info('================');

        $modelsPath = $this->config['models_dir'] ?? app_path('Models');

        if (!File::exists($modelsPath)) {
            $this->error("Models directory not found: {$modelsPath}");
            return $this->issues;
        }

        $modelFiles = File::allFiles($modelsPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkModelFile($file);
            }
        }

        return $this->issues;
    }

    protected function checkModelFile(\SplFileInfo $file): void
    {
        $namespace = $this->getNamespaceFromFile($file->getPathname());
        $className = $namespace . '\\' . $file->getFilenameWithoutExtension();

        if (!class_exists($className)) {
            $this->addIssue('Model', 'class_not_found', [
                'file' => $file->getPathname(),
                'class' => $className,
                'message' => "Model class '{$className}' could not be loaded"
            ]);
            return;
        }

        try {
            // Check if the class is abstract before trying to instantiate it
            $reflection = new \ReflectionClass($className);
            if ($reflection->isAbstract()) {
                // Skip abstract classes as they cannot be instantiated
                return;
            }

            $model = new $className();

            if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
                return; // Not an Eloquent model
            }

            $this->checkModelFillableProperties($model, $className, $file->getPathname());
            $this->checkModelTable($model, $className, $file->getPathname());

        } catch (\Exception $e) {
            $this->addIssue('Model', 'reflection_error', [
                'file' => $file->getPathname(),
                'model' => $className,
                'error' => $e->getMessage(),
                'message' => "Could not analyze model due to reflection error"
            ]);
        }
    }

    protected function checkModelFillableProperties(\Illuminate\Database\Eloquent\Model $model, string $className, string $filePath): void
    {
        $tableName = $model->getTable();
        $fillable = $model->getFillable();
        $guarded = $model->getGuarded();

        // Get table columns
        $tableColumns = $this->getTableColumns($tableName);

        if (empty($tableColumns)) {
            $this->addIssue('Model', 'table_not_found', [
                'file' => $filePath,
                'model' => $className,
                'table' => $tableName,
                'message' => "Table '{$tableName}' does not exist in database"
            ]);
            return;
        }

        // Check for missing fillable properties
        $missingFillable = [];
        foreach ($tableColumns as $column) {
            if (!in_array($column, $fillable) &&
                !in_array($column, $this->config['excluded_fields'] ?? ['id', 'created_at', 'updated_at'])) {

                // If fillable is empty or guarded contains '*', suggest adding to fillable
                if (empty($fillable) || in_array('*', $guarded)) {
                    $missingFillable[] = $column;
                }
            }
        }

        if (!empty($missingFillable)) {
            $this->addIssue('Model', 'missing_fillable', [
                'file' => $filePath,
                'model' => $className,
                'table' => $tableName,
                'missing_columns' => $missingFillable,
                'message' => "Model is missing fillable properties for columns: " . implode(', ', $missingFillable),
                'fix_available' => true
            ]);

            // Create code improvement suggestion
            $this->createFillableImprovement($filePath, $className, $missingFillable, $fillable);
        }
    }

    protected function checkModelTable(\Illuminate\Database\Eloquent\Model $model, string $className, string $filePath): void
    {
        $tableName = $model->getTable();

        if (!$this->tableExists($tableName)) {
            $this->addIssue('Model', 'table_missing', [
                'file' => $filePath,
                'model' => $className,
                'table' => $tableName,
                'message' => "Model references table '{$tableName}' which does not exist"
            ]);
        }
    }

    protected function createFillableImprovement(string $filePath, string $className, array $missingColumns, array $currentFillable): void
    {
        $newFillable = array_merge($currentFillable, $missingColumns);
        $fillableString = $this->generateFillableString($newFillable);

        $content = file_get_contents($filePath);

        // Try to find existing fillable property
        if (preg_match('/protected\s+\$fillable\s*=\s*\[.*?\];/s', $content, $matches)) {
            $originalCode = $matches[0];
            $improvedCode = "    protected \$fillable = {$fillableString};";
        } else {
            // Add new fillable property after class declaration
            // Find the class declaration and add fillable after it
            if (preg_match('/(class\s+\w+\s+extends\s+Model\s*\{)/s', $content, $matches)) {
                $originalCode = $matches[1] . "\n";
                $improvedCode = $matches[1] . "\n\n    protected \$fillable = {$fillableString};";
            } else {
                // Fallback - can't create improvement
                return;
            }
        }

        $improvement = CodeImprovement::fromSearchReplace(
            $filePath,
            'model',
            'Add missing fillable properties',
            "Add missing fillable properties for columns: " . implode(', ', $missingColumns),
            $originalCode,
            $improvedCode,
            null,
            'medium'
        );

        // Store improvement in IssueManager
        $this->getIssueManager()->attachImprovementToLastIssue($improvement);
    }

    protected function generateFillableString(array $columns): string
    {
        if (empty($columns)) {
            return '[]';
        }

        $formatted = array_map(function($column) {
            return "        '{$column}'";
        }, $columns);

        return "[\n" . implode(",\n", $formatted) . "\n    ]";
    }

    protected function getNamespaceFromFile(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }
}