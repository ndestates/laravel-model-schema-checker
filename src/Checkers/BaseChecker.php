<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use NDEstates\LaravelModelSchemaChecker\Contracts\CheckerInterface;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;

abstract class BaseChecker implements CheckerInterface
{
    protected Command $command;
    protected ?IssueManager $issueManager = null;
    protected array $config;
    protected bool $enabled = true;
    protected array $issues = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get the issue manager instance (lazy loaded)
     */
    protected function getIssueManager(): IssueManager
    {
        if ($this->issueManager === null) {
            // Check if Laravel app is available
            if (function_exists('app')) {
                try {
                    $this->issueManager = app(IssueManager::class);
                } catch (\Exception $e) {
                    // In test environment or when Laravel is not fully initialized
                    $this->issueManager = new IssueManager();
                }
            } else {
                // Fallback for non-Laravel environments
                $this->issueManager = new IssueManager();
            }
        }
        return $this->issueManager;
    }

    public function setCommand(Command $command): self
    {
        $this->command = $command;
        return $this;
    }

    public function isEnabled(): bool
    {
        // Check if this checker is enabled in the rules config
        $ruleName = $this->getRuleName();
        if ($ruleName && isset($this->config['rules']['enabled'][$ruleName])) {
            return $this->config['rules']['enabled'][$ruleName];
        }

        return $this->enabled;
    }

    protected function getRuleName(): ?string
    {
        // Override in subclasses to map to config rule names
        return null;
    }

    protected function shouldSkipFile(string $filePath): bool
    {
        if (function_exists('app')) {
            try {
                return app(\NDEstates\LaravelModelSchemaChecker\Services\CheckerManager::class)->shouldSkipFile($filePath);
            } catch (\Throwable $e) {
                // In test environment or when Laravel is not fully initialized
                return false;
            }
        }
        return false;
    }

    protected function shouldSkipModel(string $modelClass): bool
    {
        if (function_exists('app')) {
            try {
                return app(\NDEstates\LaravelModelSchemaChecker\Services\CheckerManager::class)->shouldSkipModel($modelClass);
            } catch (\Throwable $e) {
                // In test environment, don't skip models
                return false;
            }
        }
        return false;
    }

    protected function isStrictMode(): bool
    {
        return $this->config['strict_mode'] ?? false;
    }

    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * Add an issue to the collection
     */
    protected function addIssue(string $category, string $type, array $data): void
    {
        $issue = array_merge($data, [
            'category' => $category,
            'type' => $type,
            'checker' => $this->getName(),
        ]);

        $this->issues[] = $issue;
        $this->getIssueManager()->addIssue($category, $type, $data);
    }

    /**
     * Get all issues found by this checker
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * Helper method to output info messages
     */
    protected function info(string $message): void
    {
        if (isset($this->command)) {
            $this->command->info($message);
        }
    }

    /**
     * Helper method to output warning messages
     */
    protected function warn(string $message): void
    {
        if (isset($this->command)) {
            $this->command->warn($message);
        }
    }

    /**
     * Helper method to output error messages
     */
    protected function error(string $message): void
    {
        if (isset($this->command)) {
            $this->command->error($message);
        }
    }

    /**
     * Get table columns from database
     */
    protected function getTableColumns(string $tableName): array
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $columns = DB::select("PRAGMA table_info({$tableName})");
                return array_column($columns, 'name');
            } elseif ($driver === 'mysql') {
                $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
                return array_column($columns, 'Field');
            } elseif ($driver === 'pgsql') {
                $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public' ORDER BY ordinal_position", [$tableName]);
                return array_column($columns, 'column_name');
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if a table exists
     */
    protected function tableExists(string $tableName): bool
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $result = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$tableName]);
                return !empty($result);
            } elseif ($driver === 'mysql') {
                $result = DB::select("SHOW TABLES LIKE ?", [$tableName]);
                return !empty($result);
            } elseif ($driver === 'pgsql') {
                $result = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = ?", [$tableName]);
                return !empty($result);
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get foreign key constraints for a table
     */
    protected function getForeignKeyConstraints(string $tableName): array
    {
        try {
            $databaseName = \DB::getDatabaseName();
            $constraints = \DB::select("
                SELECT
                    COLUMN_NAME as `column`,
                    REFERENCED_TABLE_NAME as `references_table`,
                    REFERENCED_COLUMN_NAME as `references_column`
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$databaseName, $tableName]);

            return array_map(function ($constraint) {
                return [
                    'column' => $constraint->column,
                    'references' => $constraint->references_table . '.' . $constraint->references_column,
                ];
            }, $constraints);
        } catch (\Exception $e) {
            return [];
        }
    }
}
