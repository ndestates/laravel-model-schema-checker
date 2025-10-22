<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PerformanceChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Performance Checker';
    }

    public function getDescription(): string
    {
        return 'Detect N+1 queries and optimization opportunities';
    }

    protected function getRuleName(): ?string
    {
        return 'performance_checks';
    }

    public function check(): array
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

        return $this->issues;
    }

    protected function checkNPlusOneQueries(): void
    {
        $controllerPath = $this->config['controller_path'] ?? app_path('Http/Controllers');

        if (!$this->fileExists($controllerPath)) {
            return;
        }

        $controllerFiles = $this->getAllFiles($controllerPath);

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
            '/foreach\s*\([^}]*as\s+\$[a-zA-Z_][a-zA-Z0-9_]*\)\s*\{.*?\$[a-zA-Z_][a-zA-Z0-9_]*->[a-zA-Z_][a-zA-Z0-9_]*[^\r\n]*;/s',
            '/foreach\s*\([^}]*as\s+\$[a-zA-Z_][a-zA-Z0-9_]*\)\s*:\s*.*?\$[a-zA-Z_][a-zA-Z0-9_]*->[a-zA-Z_][a-zA-Z0-9_]*[^\r\n]*;/s',
        ];

        foreach ($loopPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $lineNumber = $this->getLineNumberFromString($content, $offset);
                    $codeSnippet = trim(substr($content, $offset, 100));

                    $this->addIssue('performance', 'potential_n_plus_one', [
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

                $this->addIssue('performance', 'n_plus_one_in_each', [
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
        $controllerPath = $this->config['controller_path'] ?? app_path('Http/Controllers');

        if (!$this->fileExists($controllerPath)) {
            return;
        }

        $controllerFiles = $this->getAllFiles($controllerPath);

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
                        $this->addIssue('performance', 'missing_eager_loading', [
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
                    $this->addIssue('performance', 'potential_missing_indexes', [
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
        $controllerPath = $this->config['controller_path'] ?? app_path('Http/Controllers');
        $modelPath = $this->config['model_path'] ?? app_path('Models');

        $paths = [$controllerPath, $modelPath];

        foreach ($paths as $path) {
            if (!$this->fileExists($path)) {
                continue;
            }

            $files = $this->getAllFiles($path);

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
            '/select\(\s*\\\*\s*\)/',
            '/DB::select\([^)]*\\\*\s*/',
            '/->get\(\s*\\\*\s*\)/',
        ];

        foreach ($selectAllPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $lineNumber = $this->getLineNumberFromString($content, $offset);

                    $this->addIssue('performance', 'select_all_query', [
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

                    $this->addIssue('performance', 'query_in_loop', [
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'code' => trim(substr($content, $offset, 80)),
                        'message' => "Database query detected inside a loop. This can cause performance issues - consider restructuring the code"
                    ]);
                }
            }
        }
    }

    protected function getLineNumberFromString(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
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
}
