<?php

namespace NDEstates\LaravelModelSchemaChecker\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrationCriticalityAnalyzer
{
    public const CRITICAL = 'CRITICAL';
    public const HIGH = 'HIGH';
    public const MEDIUM = 'MEDIUM';
    public const LOW = 'LOW';
    public const LEAST = 'LEAST';

    protected array $criticalityLevels = [
        self::CRITICAL => [],
        self::HIGH => [],
        self::MEDIUM => [],
        self::LOW => [],
        self::LEAST => [],
    ];

    protected array $migrationIssues = [];

    public function analyzeMigrations(string $migrationPath): array
    {
        $this->resetAnalysis();

        if (!file_exists($migrationPath)) {
            return [
                'error' => "Migrations directory not found: {$migrationPath}",
                'criticality' => $this->criticalityLevels,
                'recommendations' => []
            ];
        }

        $migrationFiles = $this->getMigrationFiles($migrationPath);

        foreach ($migrationFiles as $file) {
            $this->analyzeMigrationFile($file);
        }

        $this->analyzeMigrationDependencies($migrationFiles);
        $this->analyzeDataIntegrityRisks();

        return [
            'criticality' => $this->criticalityLevels,
            'migration_count' => count($migrationFiles),
            'issues_found' => count($this->migrationIssues),
            'recommendations' => $this->generateRecommendations(),
            'data_mapping_required' => $this->requiresDataMapping(),
            'rerun_risk_level' => $this->calculateRerunRiskLevel()
        ];
    }

    protected function resetAnalysis(): void
    {
        $this->criticalityLevels = [
            self::CRITICAL => [],
            self::HIGH => [],
            self::MEDIUM => [],
            self::LOW => [],
            self::LEAST => [],
        ];
        $this->migrationIssues = [];
    }

    protected function getMigrationFiles(string $migrationPath): array
    {
        try {
            return File::allFiles($migrationPath);
        } catch (\Throwable $e) {
            return $this->getAllFiles($migrationPath);
        }
    }

    protected function getAllFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function analyzeMigrationFile($file): void
    {
        $filePath = is_string($file) ? $file : $file->getPathname();
        $fileName = is_string($file) ? basename($file) : $file->getFilename();
        $content = file_get_contents($filePath);

        // CRITICAL: Syntax errors that would prevent migration execution
        if ($this->hasSyntaxErrors($content)) {
            $this->addIssue(self::CRITICAL, 'syntax_error', "Migration {$fileName} contains syntax errors");
        }

        // CRITICAL: Destructive operations without proper checks
        if ($this->hasUnsafeDestructiveOperations($content)) {
            $this->addIssue(self::CRITICAL, 'unsafe_drop', "Migration {$fileName} contains unsafe DROP operations");
        }

        // HIGH: Data loss potential
        if ($this->hasDataLossPotential($content)) {
            $this->addIssue(self::HIGH, 'data_loss', "Migration {$fileName} may cause data loss");
        }

        // HIGH: Foreign key constraint issues
        if ($this->hasForeignKeyIssues($content)) {
            $this->addIssue(self::HIGH, 'foreign_key', "Migration {$fileName} has foreign key constraint issues");
        }

        // MEDIUM: Missing indexes on foreign keys
        if ($this->hasMissingIndexes($content)) {
            $this->addIssue(self::MEDIUM, 'missing_index', "Migration {$fileName} creates foreign keys without indexes");
        }

        // MEDIUM: Inconsistent naming conventions
        if ($this->hasNamingConventionIssues($fileName)) {
            $this->addIssue(self::MEDIUM, 'naming', "Migration {$fileName} doesn't follow naming conventions");
        }

        // LOW: Performance issues
        if ($this->hasPerformanceIssues($content)) {
            $this->addIssue(self::LOW, 'performance', "Migration {$fileName} has potential performance issues");
        }

        // LEAST: Code quality issues
        if ($this->hasCodeQualityIssues($content)) {
            $this->addIssue(self::LEAST, 'code_quality', "Migration {$fileName} has code quality issues");
        }
    }

    protected function hasSyntaxErrors(string $content): bool
    {
        // Basic syntax check - look for common syntax issues
        $patterns = [
            '/\bfunction\s+\w+\s*\([^)]*$/', // Unclosed function parameters
            '/\bclass\s+\w+\s*{[^}]*$/', // Unclosed class
            '/\bif\s*\([^)]*$/', // Unclosed if statement
            '/\bforeach\s*\([^)]*$/', // Unclosed foreach
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    protected function hasUnsafeDestructiveOperations(string $content): bool
    {
        $unsafePatterns = [
            '/->dropIfExists\s*\(\s*[^)]+\s*\)\s*;.*->create\s*\(/s', // Drop and recreate without data migration
            '/DROP\s+TABLE\s+IF\s+EXISTS/i',
            '/TRUNCATE\s+TABLE/i',
            '/DELETE\s+FROM\s+\w+\s+WHERE\s+1\s*=\s*1/i', // Delete all records
        ];

        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    protected function hasDataLossPotential(string $content): bool
    {
        $dataLossPatterns = [
            '/->nullable\s*\(\s*\)\s*->change\s*\(\s*\)/', // Making nullable without data migration
            '/->change\s*\(\s*\)/', // Any column change without explicit data handling
            '/->dropColumn\s*\(/', // Dropping columns
            '/->renameColumn\s*\(/', // Renaming columns
        ];

        foreach ($dataLossPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    protected function hasForeignKeyIssues(string $content): bool
    {
        // Check for foreign keys without proper constraint naming or cascade rules
        $issues = [];

        // Foreign key without constraint name
        if (preg_match('/->foreign\s*\(\s*[^)]+\s*\)\s*->references\s*\(\s*[^)]+\s*\)\s*->on\s*\(\s*[^)]+\s*\)\s*;/', $content)) {
            $issues[] = 'unnamed_foreign_key';
        }

        // Foreign key without cascade/delete rules that might cause constraint violations
        if (preg_match('/->foreign\s*\(\s*[^)]+\s*\)\s*->references\s*\(\s*[^)]+\s*\)\s*->on\s*\(\s*[^)]+\s*\)\s*(?!.*->onDelete|->onUpdate)/s', $content)) {
            $issues[] = 'missing_cascade_rules';
        }

        return !empty($issues);
    }

    protected function hasMissingIndexes(string $content): bool
    {
        // Look for foreign key constraints that might not have corresponding indexes
        return preg_match('/->foreign\s*\(\s*[^)]+\s*\)\s*->references\s*\(\s*[^)]+\s*\)\s*->on\s*\(\s*[^)]+\s*\)/', $content) &&
               !preg_match('/->index\s*\(\s*[^)]*\b(id|.+_id)\b[^)]*\)/', $content);
    }

    protected function hasNamingConventionIssues(string $fileName): bool
    {
        // Check if filename follows Laravel naming conventions
        return !preg_match('/^\d{4}_\d{2}_\d{2}_\d{6,8}(_\d{6})?_[a-z][a-z0-9_]*\.php$/', $fileName);
    }

    protected function hasPerformanceIssues(string $content): bool
    {
        $issues = [];

        // Large data modifications without batching
        if (preg_match('/UPDATE\s+\w+\s+SET\s+[^;]+WHERE\s+[^;]+;/i', $content)) {
            $issues[] = 'unbatched_update';
        }

        // Adding columns without default values to large tables
        if (preg_match('/->addColumn\s*\(\s*[^,]+,\s*[^,]+,\s*[^)]*\)\s*->nullable\s*\(\s*\)/', $content)) {
            $issues[] = 'nullable_without_default';
        }

        return !empty($issues);
    }

    protected function hasCodeQualityIssues(string $content): bool
    {
        $issues = [];

        // Long migration files (potential complexity)
        if (strlen($content) > 5000) {
            $issues[] = 'long_migration';
        }

        // Missing comments for complex operations
        if (preg_match('/->change\s*\(\s*\)/', $content) && !preg_match('/\/\/|\/\*/', $content)) {
            $issues[] = 'missing_comments';
        }

        return !empty($issues);
    }

    protected function analyzeMigrationDependencies(array $migrationFiles): void
    {
        // Analyze dependencies between migrations that could cause issues if rerun out of order
        $tableOperations = [];

        foreach ($migrationFiles as $file) {
            $filePath = is_string($file) ? $file : $file->getPathname();
            $content = file_get_contents($filePath);

            // Track table creation/modification operations
            if (preg_match_all('/(create|table|alter)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i', $content, $matches)) {
                foreach ($matches[2] as $table) {
                    if (!isset($tableOperations[$table])) {
                        $tableOperations[$table] = [];
                    }
                    $tableOperations[$table][] = basename($filePath);
                }
            }
        }

        // Check for tables modified by multiple migrations (potential conflicts)
        foreach ($tableOperations as $table => $migrations) {
            if (count($migrations) > 1) {
                $this->addIssue(self::MEDIUM, 'dependency_conflict',
                    "Table '{$table}' is modified by multiple migrations: " . implode(', ', $migrations));
            }
        }
    }

    protected function analyzeDataIntegrityRisks(): void
    {
        // Analyze overall data integrity risks based on all issues found
        $criticalCount = count($this->criticalityLevels[self::CRITICAL]);
        $highCount = count($this->criticalityLevels[self::HIGH]);

        if ($criticalCount > 0) {
            $this->addIssue(self::CRITICAL, 'data_integrity_critical',
                "Found {$criticalCount} critical issues that could break database integrity");
        }

        if ($highCount > 0) {
            $this->addIssue(self::HIGH, 'data_integrity_high',
                "Found {$highCount} high-risk issues that may cause data loss");
        }
    }

    protected function addIssue(string $level, string $type, string $description): void
    {
        $this->criticalityLevels[$level][] = [
            'type' => $type,
            'description' => $description,
            'timestamp' => now()->toISOString()
        ];

        $this->migrationIssues[] = [
            'level' => $level,
            'type' => $type,
            'description' => $description
        ];
    }

    protected function generateRecommendations(): array
    {
        $recommendations = [];

        if (!empty($this->criticalityLevels[self::CRITICAL])) {
            $recommendations[] = [
                'priority' => 'IMMEDIATE',
                'action' => 'Do not rerun migrations',
                'reason' => 'Critical syntax errors and unsafe operations detected'
            ];
        }

        if (!empty($this->criticalityLevels[self::HIGH])) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'action' => 'Create full database backup before any changes',
                'reason' => 'High-risk operations that may cause data loss'
            ];
        }

        if ($this->requiresDataMapping()) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'action' => 'Implement data mapping strategy',
                'reason' => 'Schema changes require data transformation'
            ];
        }

        $recommendations[] = [
            'priority' => 'LOW',
            'action' => 'Review and fix code quality issues',
            'reason' => 'Improve maintainability and performance'
        ];

        return $recommendations;
    }

    protected function requiresDataMapping(): bool
    {
        // Check if any issues require data mapping/transformation
        $mappingRequiredTypes = ['data_loss', 'foreign_key', 'missing_index'];

        foreach ($this->migrationIssues as $issue) {
            if (in_array($issue['type'], $mappingRequiredTypes)) {
                return true;
            }
        }

        return false;
    }

    protected function calculateRerunRiskLevel(): string
    {
        if (!empty($this->criticalityLevels[self::CRITICAL])) {
            return 'EXTREME';
        }

        if (!empty($this->criticalityLevels[self::HIGH])) {
            return 'HIGH';
        }

        if (!empty($this->criticalityLevels[self::MEDIUM])) {
            return 'MEDIUM';
        }

        if (!empty($this->criticalityLevels[self::LOW])) {
            return 'LOW';
        }

        return 'MINIMAL';
    }

    public function getCriticalityLevels(): array
    {
        return $this->criticalityLevels;
    }

    public function getMigrationIssues(): array
    {
        return $this->migrationIssues;
    }
}