<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ValidationChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Validation Checker';
    }

    public function getDescription(): string
    {
        return 'Check validation rules against database schema';
    }

    public function check(): array
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

        return $this->issues;
    }

    protected function checkModelValidationRules(): void
    {
        $modelsPath = app_path('Models');

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

    protected function checkModelValidationForFile(\Symfony\Component\Finder\SplFileInfo $file): void
    {
        $namespace = $this->getNamespaceFromFile($file->getPathname());
        $className = $namespace . '\\' . $file->getFilenameWithoutExtension();

        if (!class_exists($className)) {
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
            $tableName = $model->getTable();
            $content = file_get_contents($file->getPathname());

            if ($content === false) {
                return; // Skip files that cannot be read
            }

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
                        $this->addIssue('validation', 'rule_for_nonexistent_field', [
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
                if (
                    $columnInfo['nullable'] === false &&
                    !in_array($columnName, ['id', 'created_at', 'updated_at']) &&
                    !isset($rules[$columnName])
                ) {
                    $this->addIssue('validation', 'missing_required_validation', [
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

    protected function checkValidationMethods(string $content, string $filePath, string $className): void
    {
        // Check for custom validation methods
        if (preg_match_all('/public function validate([A-Z]\w*)\(/', $content, $matches)) {
            foreach ($matches[1] as $methodSuffix) {
                $methodName = 'validate' . $methodSuffix;

                // Check if method has proper return type or throws validation exceptions
                if (!preg_match("/function {$methodName}\([^}]*throws\s+ValidationException/", $content)) {
                    $this->addIssue('validation', 'validation_method_no_exception', [
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

                if ($content === false) {
                    continue; // Skip files that cannot be read
                }

                // Check if Form Request has rules method
                if (!preg_match('/public function rules\(\)/', $content)) {
                    $this->addIssue('validation', 'missing_rules_method', [
                        'file' => $file->getPathname(),
                        'message' => "Form Request class should have a rules() method"
                    ]);
                }

                // Check for authorization method
                if (!preg_match('/public function authorize\(\)/', $content)) {
                    $this->addIssue('validation', 'missing_authorize_method', [
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

                if ($content === false) {
                    continue; // Skip files that cannot be read
                }

                // Check for inline validation that should be moved to Form Requests
                if (preg_match_all('/\$request->validate\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $offset = $match[1];
                        $lineNumber = $this->getLineNumberFromString($content, $offset);

                        $this->addIssue('validation', 'inline_validation', [
                            'file' => $file->getPathname(),
                            'line' => $lineNumber,
                            'message' => "Consider moving inline validation to a Form Request class for better organization and reusability"
                        ]);
                    }
                }
            }
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
                            $this->addIssue('validation', 'max_length_exceeds_column', [
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
                $this->addIssue('validation', 'missing_max_length_validation', [
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
                $this->addIssue('validation', 'missing_numeric_validation', [
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
                $this->addIssue('validation', 'missing_boolean_validation', [
                    'file' => $filePath,
                    'model' => $className,
                    'field' => $field,
                    'message' => "Boolean field '{$field}' should have boolean validation"
                ]);
            }
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

    protected function getNamespaceFromFile(string $filePath): string
    {
        $content = File::get($filePath);

        if (preg_match('/namespace\s+([^;]+);/i', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function getLineNumberFromString(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
}
