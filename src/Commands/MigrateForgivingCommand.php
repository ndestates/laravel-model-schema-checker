<?php

namespace NDEstates\LaravelModelSchemaChecker\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Helper\ProgressBar;
use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;

class MigrateForgivingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model-schema-checker:migrate-forgiving {--database= : The database connection to use} {--path= : The path to the migrations files to be executed} {--force : Force the operation to run when in production} {--check-migrations : Run migration validation checks before attempting migrations} {--fix-migrations : Automatically fix simple migration issues before running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations with forgiveness - skip migrations that fail due to existing tables (Laravel Model Schema Checker)';

    protected CheckerManager $checkerManager;

    public function __construct(CheckerManager $checkerManager)
    {
        parent::__construct();
        $this->checkerManager = $checkerManager;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // PRODUCTION SAFETY: This tool is designed for development only
        if ($this->isProductionEnvironment()) {
            $this->error('🚫 SECURITY ERROR: Laravel Model Schema Checker is disabled in production environments.');
            $this->error('');
            $this->error('This tool is designed exclusively for development and testing environments.');
            $this->error('Migration operations should only be done during development setup.');
            $this->error('');
            $this->error('If you believe this is an error, please check your APP_ENV setting.');
            return 1;
        }

        $this->info('Running migrations in forgiving mode...');
        $this->info('Migrations that fail due to existing tables will be skipped and marked as complete.');
        $this->newLine();

        // Run migration validation checks if requested
        if ($this->option('check-migrations')) {
            $this->info('🔍 Running migration validation checks...');
            $issues = $this->runMigrationChecks();

            if (!empty($issues)) {
                $this->warn('⚠️  Migration issues detected:');
                $this->displayMigrationIssues($issues);

                // If auto-fix is enabled, attempt to fix issues
                if ($this->option('fix-migrations')) {
                    $this->info('🔧 Attempting to auto-fix migration issues...');
                    $fixedCount = $this->attemptAutoFixes($issues);
                    if ($fixedCount > 0) {
                        $this->info("✅ Auto-fixed {$fixedCount} migration issues.");
                        // Re-run checks to see remaining issues
                        $remainingIssues = $this->runMigrationChecks();
                        if (!empty($remainingIssues)) {
                            $this->warn('⚠️  Some issues could not be auto-fixed. Please review manually:');
                            $this->displayMigrationIssues($remainingIssues);
                            if (!$this->confirm('Continue with migration despite remaining issues?', false)) {
                                return 1;
                            }
                        }
                    } else {
                        $this->warn('❌ No issues could be auto-fixed automatically.');
                        if (!$this->confirm('Continue with migration despite issues?', false)) {
                            return 1;
                        }
                    }
                } else {
                    if (!$this->confirm('Continue with migration despite detected issues?', false)) {
                        $this->info('💡 Tip: Use --fix-migrations to attempt automatic fixes, or fix issues manually.');
                        return 1;
                    }
                }
            } else {
                $this->info('✅ No migration issues detected.');
            }
            $this->newLine();
        }

        $migrator = $this->getMigrator();
        $migrator->setConnection($this->option('database'));

        if (!$migrator->repositoryExists()) {
            $this->error('Migration table does not exist. Please run migrate:install first.');
            return 1;
        }

        $files = $migrator->getMigrationFiles($this->getMigrationPaths());
        $ran = $migrator->getRepository()->getRan();
        $pendingMigrations = Collection::make($files)->reject(function ($file) use ($ran) {
            return in_array($this->getMigrationName($file), $ran);
        });

        if ($pendingMigrations->isEmpty()) {
            $this->info('Nothing to migrate.');
            return 0;
        }

        $progressBar = new ProgressBar($this->output, $pendingMigrations->count());
        $progressBar->setFormat('verbose');

        $results = [
            'successful' => [],
            'skipped_existing' => [],
            'failed_other' => [],
        ];

        foreach ($pendingMigrations as $file) {
            $migrationName = $this->getMigrationName($file);

            try {
                // Try to run the migration
                $migrator->runPending([$file], []);

                // Check if it was actually marked as ran
                $newRan = $migrator->getRepository()->getRan();
                if (in_array($migrationName, $newRan)) {
                    $results['successful'][] = $migrationName;
                    $this->line("✓ <fg=green>{$migrationName}</> - Ran successfully");
                } else {
                    $results['skipped_existing'][] = $migrationName;
                    $this->line("⚠ <fg=yellow>{$migrationName}</> - Skipped (table likely exists)");
                    // Mark as ran even though it didn't run
                    $migrator->getRepository()->log($migrationName, $migrator->getRepository()->getNextBatchNumber());
                }

            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();

                // Check if it's a "table already exists" type error
                if ($this->isTableExistsError($errorMessage)) {
                    $results['skipped_existing'][] = $migrationName;
                    $this->line("⚠ <fg=yellow>{$migrationName}</> - Skipped (table exists): {$errorMessage}");

                    // Mark as ran so it doesn't keep trying
                    $migrator->getRepository()->log($migrationName, $migrator->getRepository()->getNextBatchNumber());
                } else {
                    $results['failed_other'][] = [
                        'migration' => $migrationName,
                        'error' => $errorMessage
                    ];
                    $this->line("✗ <fg=red>{$migrationName}</> - Failed: {$errorMessage}");
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Generate report
        $this->generateReport($results);

        return count($results['failed_other']) > 0 ? 1 : 0;
    }

    /**
     * Get the migrator instance.
     */
    protected function getMigrator(): Migrator
    {
        return $this->laravel['migrator'];
    }

    /**
     * Get the migration paths.
     */
    protected function getMigrationPaths(): array
    {
        if ($this->option('path')) {
            return [$this->option('path')];
        }

        return [database_path('migrations')];
    }

    /**
     * Get the migration name from file path.
     */
    protected function getMigrationName(string $file): string
    {
        return str_replace('.php', '', basename($file));
    }

    /**
     * Check if the error is related to table already existing.
     */
    protected function isTableExistsError(string $errorMessage): bool
    {
        $patterns = config('model-schema-checker.migrations.forgiving.table_exists_patterns', [
            'table.*already exists',
            'already exists',
            'Base table or view already exists',
            'SQLSTATE\[42S01\]',
            'Duplicate entry.*for key.*PRIMARY',
            'SQLSTATE\[23000\].*Duplicate entry',
        ]);

        foreach ($patterns as $pattern) {
            if (stripos($errorMessage, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a report of the migration results.
     */
    protected function generateReport(array $results): void
    {
        $this->info('📊 Migration Report');
        $this->line('==================');

        $this->info("✅ Successful migrations: " . count($results['successful']));
        foreach ($results['successful'] as $migration) {
            $this->line("  • {$migration}");
        }

        $this->newLine();
        $this->warn("⚠️  Skipped (tables exist): " . count($results['skipped_existing']));
        foreach ($results['skipped_existing'] as $migration) {
            $this->line("  • {$migration}");
        }

        if (!empty($results['failed_other'])) {
            $this->newLine();
            $this->error("❌ Failed (other errors): " . count($results['failed_other']));
            foreach ($results['failed_other'] as $failure) {
                $this->line("  • <fg=red>{$failure['migration']}</>: {$failure['error']}");
            }
        }

        $this->newLine();
        $reportPath = config('model-schema-checker.migrations.forgiving.report_path', storage_path('logs')) .
                      '/migration_forgiving_report_' . date('Y-m-d_H-i-s') . '.json';

        // Save detailed report to file
        $reportData = [
            'timestamp' => now()->toISOString(),
            'command' => $this->signature,
            'database' => $this->option('database') ?: config('database.default'),
            'results' => $results,
            'summary' => [
                'successful' => count($results['successful']),
                'skipped_existing' => count($results['skipped_existing']),
                'failed_other' => count($results['failed_other']),
                'total_processed' => count($results['successful']) + count($results['skipped_existing']) + count($results['failed_other'])
            ]
        ];

        File::put($reportPath, json_encode($reportData, JSON_PRETTY_PRINT));
        $this->info('💾 Report saved to: ' . $reportPath);
    }

    /**
     * Check if we're running in a production environment
     * Multiple layers of protection to prevent reverse engineering
     */
    protected function isProductionEnvironment(): bool
    {
        $env = app()->environment();

        // Primary check: standard Laravel environments
        if (in_array(strtolower($env), ['production', 'prod', 'live'])) {
            return true;
        }

        // Secondary check: environment variables that might indicate production
        if (env('APP_ENV') === 'production' || env('APP_ENV') === 'prod' || env('APP_ENV') === 'live') {
            return true;
        }

        // Tertiary check: server environment variables
        if (isset($_SERVER['APP_ENV']) && in_array(strtolower($_SERVER['APP_ENV']), ['production', 'prod', 'live'])) {
            return true;
        }

        // Quaternary check: check for production-like hostnames
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = strtolower($_SERVER['HTTP_HOST']);
            // Common production patterns
            if (strpos($host, '.com') !== false ||
                strpos($host, '.org') !== false ||
                strpos($host, '.net') !== false ||
                !preg_match('/\b(localhost|127\.0\.0\.1|\.local|\.dev|\.test)\b/', $host)) {
                // Additional check: if not clearly development, be conservative
                if (!preg_match('/\b(dev|staging|test|demo)\b/', $host)) {
                    // This is a heuristic - in production deployments, be extra cautious
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Run migration validation checks
     */
    protected function runMigrationChecks(): array
    {
        $issues = [];

        try {
            // Run migration-specific checks
            $migrationChecker = $this->checkerManager->getChecker('migration');
            if ($migrationChecker) {
                $migrationIssues = $migrationChecker->check();
                $issues = array_merge($issues, $migrationIssues);
            }

            // Also run migration quality checks
            $migrationQualityChecker = $this->checkerManager->getChecker('migration_quality');
            if ($migrationQualityChecker) {
                $qualityIssues = $migrationQualityChecker->check();
                $issues = array_merge($issues, $qualityIssues);
            }

        } catch (\Exception $e) {
            $this->warn("Could not run migration checks: " . $e->getMessage());
        }

        return $issues;
    }

    /**
     * Display migration issues in a user-friendly format
     */
    protected function displayMigrationIssues(array $issues): void
    {
        $issueCount = count($issues);
        $this->warn("Found {$issueCount} migration issue(s):");
        $this->newLine();

        foreach ($issues as $issue) {
            $severity = $issue['severity'] ?? 'medium';
            $severityIcon = match($severity) {
                'critical' => '🚨',
                'high' => '🔴',
                'medium' => '🟡',
                'low' => '🟢',
                default => '⚪'
            };

            $this->line("{$severityIcon} <fg={$this->getSeverityColor($severity)}>{$issue['type']}</>: {$issue['message']}");

            if (isset($issue['file'])) {
                $this->line("   📁 File: {$issue['file']}");
            }
            if (isset($issue['table'])) {
                $this->line("   📋 Table: {$issue['table']}");
            }
            if (isset($issue['column'])) {
                $this->line("   📊 Column: {$issue['column']}");
            }
            $this->newLine();
        }
    }

    /**
     * Get color for severity level
     */
    protected function getSeverityColor(string $severity): string
    {
        return match($severity) {
            'critical' => 'red',
            'high' => 'red',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'white'
        };
    }

    /**
     * Attempt to auto-fix migration issues
     */
    protected function attemptAutoFixes(array $issues): int
    {
        $fixedCount = 0;

        foreach ($issues as $issue) {
            if ($this->canAutoFixIssue($issue)) {
                if ($this->applyAutoFix($issue)) {
                    $fixedCount++;
                    $this->line("   ✅ Fixed: {$issue['message']}");
                } else {
                    $this->line("   ❌ Could not fix: {$issue['message']}");
                }
            }
        }

        return $fixedCount;
    }

    /**
     * Check if an issue can be auto-fixed
     */
    protected function canAutoFixIssue(array $issue): bool
    {
        $fixableTypes = [
            'string_without_length',
            'nullable_foreign_key_no_default',
            'malformed_method_call'
        ];

        return in_array($issue['type'] ?? '', $fixableTypes) && isset($issue['file']);
    }

    /**
     * Apply an auto-fix for a migration issue
     */
    protected function applyAutoFix(array $issue): bool
    {
        if (!isset($issue['file']) || !file_exists($issue['file'])) {
            return false;
        }

        $content = file_get_contents($issue['file']);
        if ($content === false) {
            return false;
        }

        $newContent = $content;

        switch ($issue['type']) {
            case 'string_without_length':
                // Fix string columns without length
                $newContent = $this->fixStringWithoutLength($content, $issue);
                break;

            case 'nullable_foreign_key_no_default':
                // Fix nullable foreign keys without default
                $newContent = $this->fixNullableForeignKeyNoDefault($content, $issue);
                break;

            case 'malformed_method_call':
                // Fix malformed method calls
                $newContent = $this->fixMalformedMethodCall($content, $issue);
                break;
        }

        if ($newContent !== $content) {
            return file_put_contents($issue['file'], $newContent) !== false;
        }

        return false;
    }

    /**
     * Fix string columns without length specification
     */
    protected function fixStringWithoutLength(string $content, array $issue): string
    {
        // Look for $table->string('column_name') and add default length
        $pattern = '/\$table->string\(\s*[\'"]([^\'"]+)[\'"]\s*\)/';
        $replacement = '$table->string(\'$1\', 255)';

        return preg_replace($pattern, $replacement, $content);
    }

    /**
     * Fix nullable foreign keys without default values
     */
    protected function fixNullableForeignKeyNoDefault(string $content, array $issue): string
    {
        // Look for nullable foreign keys and add ->default(null)
        $pattern = '/(\$table->foreignId\([^)]+\)->nullable\(\))/';
        $replacement = '$1->default(null)';

        return preg_replace($pattern, $replacement, $content);
    }

    /**
     * Fix malformed method calls (like $table->string('key'(255)) )
     */
    protected function fixMalformedMethodCall(string $content, array $issue): string
    {
        if (isset($issue['malformed_call'])) {
            // Try to fix common malformed calls
            $malformed = $issue['malformed_call'];

            // Fix $table->string('key'(255)) -> $table->string('key', 255)
            if (preg_match('/\$table->(\w+)\(\s*[\'"]([^\'"]+)[\'"]\s*\(\s*(\d+)\s*\)\s*\)/', $malformed, $matches)) {
                $method = $matches[1];
                $column = $matches[2];
                $length = $matches[3];
                $fixed = "\$table->{$method}('{$column}', {$length})";

                return str_replace($malformed, $fixed, $content);
            }
        }

        return $content;
    }
}