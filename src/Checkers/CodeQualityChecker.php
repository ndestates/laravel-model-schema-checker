<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CodeQualityChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Code Quality Checker';
    }

    public function getDescription(): string
    {
        return 'Check Laravel best practices and code quality';
    }

    public function check(): array
    {
        $this->info('');
        $this->info('Checking Code Quality');
        $this->info('====================');

        // Check model files
        $this->checkModelQuality();

        // Check controller files
        $this->checkControllerQuality();

        // Check migration files
        $this->checkMigrationQuality();

        // Check general PHP/Laravel best practices
        $this->checkGeneralBestPractices();

        return $this->issues;
    }

    protected function checkModelQuality(): void
    {
        $modelPath = app_path('Models');

        if (!File::exists($modelPath)) {
            $this->warn("Models directory not found: {$modelPath}");
            return;
        }

        $modelFiles = File::allFiles($modelPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkModelFile($file);
            }
        }
    }

    protected function checkModelFile($file): void
    {
        $content = File::get($file->getPathname());
        $className = $file->getFilenameWithoutExtension();

        // Check for proper namespace
        if (!preg_match('/namespace\s+App\\\\Models;/', $content)) {
            $this->issue(
                "Model {$className} should use App\\Models namespace",
                'medium',
                $file->getPathname()
            );
        }

        // Check for fillable/mass assignment protection
        if (!preg_match('/protected\s+\$fillable\s*=/', $content) &&
            !preg_match('/protected\s+\$guarded\s*=/', $content)) {
            $this->issue(
                "Model {$className} should have fillable or guarded property for mass assignment protection",
                'high',
                $file->getPathname()
            );
        }

        // Check for proper relationship method naming
        $this->checkRelationshipMethods($content, $className, $file->getPathname());

        // Check for use of deprecated methods
        $this->checkDeprecatedMethods($content, $className, $file->getPathname());

        // Check for proper use of scopes
        $this->checkScopeMethods($content, $className, $file->getPathname());
    }

    protected function checkRelationshipMethods(string $content, string $className, string $filePath): void
    {
        // Find relationship methods
        preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $matches);

        foreach ($matches[1] as $method) {
            // Check for proper relationship naming (should be camelCase)
            if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $method)) {
                $this->issue(
                    "Relationship method '{$method}' in {$className} should use camelCase naming",
                    'low',
                    $filePath
                );
            }

            // Check for common relationship patterns
            $methodContent = $this->getMethodContent($content, $method);
            if ($methodContent) {
                $this->validateRelationshipReturn($methodContent, $method, $className, $filePath);
            }
        }
    }

    protected function checkDeprecatedMethods(string $content, string $className, string $filePath): void
    {
        $deprecatedPatterns = [
            'findOrFail\(\s*\d+\s*\)' => 'Use findOrFail with proper type hinting',
            'where\(\s*[\'"]id[\'"]\s*,\s*' => 'Use whereId() instead of where("id", ...)',
            'lists\(' => 'lists() method is deprecated, use pluck() instead',
        ];

        foreach ($deprecatedPatterns as $pattern => $message) {
            if (preg_match("/{$pattern}/", $content)) {
                $this->issue(
                    "Deprecated method usage in {$className}: {$message}",
                    'medium',
                    $filePath
                );
            }
        }
    }

    protected function checkScopeMethods(string $content, string $className, string $filePath): void
    {
        // Find scope methods
        preg_match_all('/public\s+function\s+scope(\w+)\s*\(/', $content, $matches);

        foreach ($matches[1] as $scope) {
            // Check scope naming (should be CamelCase after 'scope')
            if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $scope)) {
                $this->issue(
                    "Scope method 'scope{$scope}' in {$className} should use CamelCase naming",
                    'low',
                    $filePath
                );
            }
        }
    }

    protected function checkControllerQuality(): void
    {
        $controllerPath = app_path('Http/Controllers');

        if (!File::exists($controllerPath)) {
            $this->warn("Controllers directory not found: {$controllerPath}");
            return;
        }

        $controllerFiles = File::allFiles($controllerPath);

        foreach ($controllerFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkControllerFile($file);
            }
        }
    }

    protected function checkControllerFile($file): void
    {
        $content = File::get($file->getPathname());
        $className = $file->getFilenameWithoutExtension();

        // Check for proper validation
        $this->checkValidationUsage($content, $className, $file->getPathname());

        // Check for proper authorization
        $this->checkAuthorizationUsage($content, $className, $file->getPathname());

        // Check for N+1 query prevention
        $this->checkQueryEfficiency($content, $className, $file->getPathname());
    }

    protected function checkValidationUsage(string $content, string $className, string $filePath): void
    {
        // Check if controller uses form requests for complex validation
        if (preg_match('/\$request->validate\(\s*\[[\s\S]*?\]\s*\)/', $content)) {
            $this->issue(
                "Controller {$className} uses inline validation - consider using Form Request classes for complex validation",
                'medium',
                $filePath
            );
        }
    }

    protected function checkAuthorizationUsage(string $content, string $className, string $filePath): void
    {
        // Check for authorization usage
        if (!preg_match('/\$this->authorize\(|Gate::|policy\(\)/', $content)) {
            $this->issue(
                "Controller {$className} may be missing authorization checks",
                'medium',
                $filePath
            );
        }
    }

    protected function checkQueryEfficiency(string $content, string $className, string $filePath): void
    {
        // Check for potential N+1 queries
        if (preg_match('/foreach\s*\([^)]*\)\s*\{[\s\S]*?->\w+\(\)/', $content)) {
            $this->issue(
                "Potential N+1 query detected in {$className} - consider using eager loading",
                'high',
                $filePath
            );
        }
    }

    protected function checkMigrationQuality(): void
    {
        $migrationPath = database_path('migrations');

        if (!File::exists($migrationPath)) {
            return;
        }

        $migrationFiles = File::allFiles($migrationPath);

        foreach ($migrationFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkMigrationFileQuality($file);
            }
        }
    }

    protected function checkMigrationFileQuality($file): void
    {
        $content = File::get($file->getPathname());

        // Check for proper column types
        $this->checkColumnTypes($content, $file->getFilename(), $file->getPathname());

        // Check for proper index usage
        $this->checkIndexUsage($content, $file->getFilename(), $file->getPathname());

        // Check for foreign key constraints
        $this->checkForeignKeyConstraints($content, $file->getFilename(), $file->getPathname());
    }

    protected function checkColumnTypes(string $content, string $fileName, string $filePath): void
    {
        // Check for enum usage (should use proper enum types in Laravel 9+)
        if (preg_match('/->enum\(/', $content)) {
            $this->issue(
                "Migration {$fileName} uses enum - consider using native PHP enums or proper database enums",
                'low',
                $filePath
            );
        }

        // Check for proper string length specifications
        if (preg_match('/->string\(\s*\'[^\']+\'\s*\)(?!->length\()/', $content)) {
            $this->issue(
                "Migration {$fileName} should specify string length for better database compatibility",
                'medium',
                $filePath
            );
        }
    }

    protected function checkIndexUsage(string $content, string $fileName, string $filePath): void
    {
        // Check for indexed foreign keys
        if (preg_match('/->foreign\(\s*[\'"]\w+[\'"]\s*\)/', $content) &&
            !preg_match('/->index\(\s*\)/', $content)) {
            $this->issue(
                "Migration {$fileName} creates foreign key without index - foreign keys should be indexed",
                'high',
                $filePath
            );
        }
    }

    protected function checkForeignKeyConstraints(string $content, string $fileName, string $filePath): void
    {
        // Check for proper foreign key naming
        if (preg_match('/->foreign\(\s*[\'"](\w+)[\'"]\s*\)\s*->references\(\s*[\'"](\w+)[\'"]\s*\)/', $content, $matches)) {
            $table = $matches[1];
            $column = $matches[2];
            $expectedConstraintName = "{$table}_{$column}_foreign";

            if (!preg_match("/->name\(\s*['\"]{$expectedConstraintName}['\"]\s*\)/", $content)) {
                $this->issue(
                    "Migration {$fileName} should use consistent foreign key constraint naming",
                    'low',
                    $filePath
                );
            }
        }
    }

    protected function checkGeneralBestPractices(): void
    {
        // Check for proper use of Laravel helpers
        $this->checkLaravelHelpers();

        // Check for proper exception handling
        $this->checkExceptionHandling();

        // Check for proper logging
        $this->checkLoggingPractices();
    }

    protected function checkLaravelHelpers(): void
    {
        // This would require scanning all PHP files in the app
        $appPath = app_path();

        if (!File::exists($appPath)) {
            return;
        }

        $phpFiles = File::allFiles($appPath);

        foreach ($phpFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = File::get($file->getPathname());

                // Check for use of config() helper instead of direct array access
                if (preg_match('/config\(\s*\)\s*\[/', $content)) {
                    $this->issue(
                        "File {$file->getFilename()} uses array access on config() - use dot notation instead",
                        'low',
                        $file->getPathname()
                    );
                }

                // Check for proper use of collect() helper
                if (preg_match('/new\s+Collection\(/', $content)) {
                    $this->issue(
                        "File {$file->getFilename()} uses 'new Collection()' - use collect() helper instead",
                        'low',
                        $file->getPathname()
                    );
                }
            }
        }
    }

    protected function checkExceptionHandling(): void
    {
        // Check for proper exception handling in controllers
        $controllerPath = app_path('Http/Controllers');

        if (!File::exists($controllerPath)) {
            return;
        }

        $controllerFiles = File::allFiles($controllerPath);

        foreach ($controllerFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = File::get($file->getPathname());

                // Check for try-catch blocks in methods that might throw exceptions
                if (preg_match('/public\s+function\s+\w+\s*\([^)]*\)\s*\{[\s\S]*?\$this->[^}]+}/', $content) &&
                    !preg_match('/try\s*\{[\s\S]*?}\s*catch\s*\(/', $content)) {
                    $this->issue(
                        "Controller method in {$file->getFilename()} may need exception handling",
                        'medium',
                        $file->getPathname()
                    );
                }
            }
        }
    }

    protected function checkLoggingPractices(): void
    {
        // Check for proper logging levels
        $appPath = app_path();

        if (!File::exists($appPath)) {
            return;
        }

        $phpFiles = File::allFiles($appPath);

        foreach ($phpFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = File::get($file->getPathname());

                // Check for use of dd() or dump() in production code
                if (preg_match('/\bdd\(\s*\)|dump\(\s*\)/', $content)) {
                    $this->issue(
                        "File {$file->getFilename()} contains dd() or dump() calls - remove for production",
                        'high',
                        $file->getPathname()
                    );
                }

                // Check for proper log levels
                if (preg_match('/Log::emergency\(|Log::alert\(|Log::critical\(/', $content)) {
                    $this->issue(
                        "File {$file->getFilename()} uses high-priority log levels - ensure appropriate usage",
                        'medium',
                        $file->getPathname()
                    );
                }
            }
        }
    }

    protected function getMethodContent(string $content, string $methodName): ?string
    {
        // Extract method content using regex
        $pattern = "/public\s+function\s+{$methodName}\s*\([^)]*\)\s*\{([\s\S]*?)\}/";
        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function validateRelationshipReturn(string $methodContent, string $methodName, string $className, string $filePath): void
    {
        // Check for proper return types in relationship methods
        $relationshipTypes = [
            'hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'hasOneThrough', 'hasManyThrough'
        ];

        $found = false;
        foreach ($relationshipTypes as $type) {
            if (preg_match("/return\s+\$this->{$type}\(/", $methodContent)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->issue(
                "Relationship method '{$methodName}' in {$className} may not return a proper relationship",
                'medium',
                $filePath
            );
        }
    }
}