<?php

namespace Check\Commands;

use Check\Config\CheckConfig;
use Check\Utils\Logger;
use Check\Services\ModelAnalyzer;
use Check\Services\DatabaseAnalyzer;
use Check\Services\MigrationGenerator;
use Check\Services\RelationshipAnalyzer;
use Check\Services\CommandRunner;

class GenerateMigrationsCommand
{
    protected CheckConfig $config;
    protected Logger $logger;
    protected ModelAnalyzer $modelAnalyzer;
    protected DatabaseAnalyzer $dbAnalyzer;
    protected MigrationGenerator $migrationGenerator;
    protected RelationshipAnalyzer $relationshipAnalyzer;
    protected CommandRunner $commandRunner;

    public function __construct(
        CheckConfig $config,
        Logger $logger,
        ModelAnalyzer $modelAnalyzer,
        DatabaseAnalyzer $dbAnalyzer,
        MigrationGenerator $migrationGenerator,
        RelationshipAnalyzer $relationshipAnalyzer,
        CommandRunner $commandRunner
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->modelAnalyzer = $modelAnalyzer;
        $this->dbAnalyzer = $dbAnalyzer;
        $this->migrationGenerator = $migrationGenerator;
        $this->relationshipAnalyzer = $relationshipAnalyzer;
        $this->commandRunner = $commandRunner;
    }

    public function execute(array $flags): void
    {
        $this->logger->section("GENERATING LARAVEL MIGRATIONS");

        if ($this->config->isBackupEnabled()) {
            $this->generateBackupCommands();
        }

        $modelClasses = $this->modelAnalyzer->getAllModelClasses();
        $generatedMigrations = [];
        $migrationTimestamp = date('Y_m_d_His');

        foreach ($modelClasses as $modelClass => $filePath) {
            $tableName = $this->modelAnalyzer->getTableName($modelClass);
            $modelFields = $this->modelAnalyzer->getModelFields($modelClass);
            $dbSchema = $this->dbAnalyzer->getTableSchema($tableName);

            // Handle missing tables
            if (empty($dbSchema) && !$this->dbAnalyzer->tableExists($tableName)) {
                $this->logger->warning("Table '$tableName' does not exist for model '$modelClass'");

                // Get relationships for foreign key generation
                $relationships = []; // Temporarily disabled due to model loading issues
                // $relationships = $this->relationshipAnalyzer->analyzeModelRelationships($modelClass);

                $migrationContent = $this->migrationGenerator->generateCreateTableMigration(
                    $tableName,
                    $modelFields,
                    $relationships
                );

                $timestamp = $migrationTimestamp . str_pad(count($generatedMigrations), 2, '0', STR_PAD_LEFT);
                $migrationFile = $this->migrationGenerator->saveMigration($migrationContent, $tableName, $timestamp);

                if ($migrationFile) {
                    $generatedMigrations[] = $migrationFile;
                }

                continue;
            }

            // For existing tables, generate enhanced schema migrations
            if (!empty($dbSchema)) {
                $migrationContent = $this->migrationGenerator->generateEnhancedMigration($tableName);
                if ($migrationContent) {
                    $timestamp = $migrationTimestamp . str_pad(count($generatedMigrations), 2, '0', STR_PAD_LEFT);
                    $migrationFile = $this->migrationGenerator->saveMigration($migrationContent, $tableName, $timestamp);

                    if ($migrationFile) {
                        $generatedMigrations[] = $migrationFile;
                    }
                }
            }

            // Generate sync migrations for model-schema mismatches
            $comparison = $this->compareModelAndSchema($modelFields, $dbSchema, $modelClass, $tableName);

            if ($comparison['has_changes']) {
                $filteredDbFields = array_diff(array_keys($dbSchema), $this->config->getExcludedFields());
                $missingInDb = array_diff($comparison['corrected_fields'], $filteredDbFields);
                $extraInDb = array_diff($filteredDbFields, $comparison['corrected_fields']);

                if (!empty($missingInDb) || !empty($extraInDb)) {
                    $migrationContent = $this->migrationGenerator->generateSyncTableMigration(
                        $tableName,
                        $missingInDb,
                        $extraInDb
                    );

                    if ($migrationContent) {
                        $timestamp = $migrationTimestamp . str_pad(count($generatedMigrations), 2, '0', STR_PAD_LEFT);
                        $migrationFile = $this->migrationGenerator->saveMigration($migrationContent, $tableName, $timestamp);

                        if ($migrationFile) {
                            $generatedMigrations[] = $migrationFile;
                        }
                    }
                }
            }
        }

        $this->showMigrationSummary($generatedMigrations);

        // Run migrations if requested
        if (isset($flags['run_migrations']) && $flags['run_migrations']) {
            $this->runMigrationsIfRequested($flags);
        }
    }

    protected function compareModelAndSchema(array $modelFields, array $dbSchema, string $modelClass, string $tableName): array
    {
        $filteredDbFields = array_diff(array_keys($dbSchema), $this->config->getExcludedFields());

        $modelOnly = array_diff($modelFields, $filteredDbFields);
        $dbOnly = array_diff($filteredDbFields, $modelFields);

        return [
            'model_class' => $modelClass,
            'table_name' => $tableName,
            'has_changes' => !empty($modelOnly) || !empty($dbOnly),
            'model_only' => $modelOnly,
            'db_only' => $dbOnly,
            'corrected_fields' => array_unique(array_merge(
                array_intersect($modelFields, $filteredDbFields),
                $dbOnly
            )),
        ];
    }

    protected function generateBackupCommands(): void
    {
        $this->logger->warning("=== DATABASE BACKUP RECOMMENDATIONS ===");
        $this->logger->warning("Before running generated migrations, create a backup:");
        $this->logger->warning("mysqldump -h [host] -u [username] -p[password] [database] > backup.sql");
        $this->logger->warning("Or using Laravel: php artisan db:backup (if you have a backup package)");
        $this->logger->warning("");
    }

    protected function showMigrationSummary(array $generatedMigrations): void
    {
        $this->logger->section("MIGRATION GENERATION SUMMARY");
        $this->logger->info("Generated migrations: " . count($generatedMigrations));

        foreach ($generatedMigrations as $migration) {
            $this->logger->info("  - " . basename($migration));
        }

        if (count($generatedMigrations) > 0) {
            $this->logger->info("");
            $this->logger->info("Next steps:");
            $this->logger->info("1. Review the generated migrations carefully");
            $this->logger->info("2. Adjust data types and constraints as needed");
            $this->logger->info("3. Create a database backup");
            $this->logger->info("4. Run: php artisan migrate");
            $this->logger->info("   Or use: --run-migrations flag to run automatically");

            echo "\n=== MIGRATIONS GENERATED ===\n";
            echo "Count: " . count($generatedMigrations) . "\n";
            echo "Location: " . dirname($generatedMigrations[0] ?? database_path('migrations')) . "\n";
            echo "Next: Review migrations, backup DB, then run 'php artisan migrate'\n";
        }
    }

    protected function runMigrationsIfRequested(array $flags): void
    {
        $this->logger->section("RUNNING MIGRATIONS");

        $this->logger->warning("About to run database migrations automatically...");
        $this->logger->warning("Environment detected: " . $this->commandRunner->getEnvironment());

        // Create database backup if requested
        if (isset($flags['backup_db']) && $flags['backup_db']) {
            $this->logger->info("Creating database backup before running migrations...");
            $backupResult = $this->commandRunner->createDatabaseBackup();

            if (!$backupResult['success']) {
                $this->logger->error("Database backup failed! Aborting migration run.");
                $this->logger->error("Backup command: " . $backupResult['command']);
                foreach ($backupResult['output'] as $line) {
                    $this->logger->error("  $line");
                }
                return;
            } else {
                $this->logger->success("Database backup completed successfully");
            }
        }

        // Confirm with user (in interactive mode this would be better)
        $this->logger->info("Running migrations...");

        $result = $this->commandRunner->runMigrations();

        if ($result['success']) {
            $this->logger->success("Migrations completed successfully!");
            foreach ($result['output'] as $line) {
                $this->logger->info("  $line");
            }
        } else {
            $this->logger->error("Migration failed with exit code: " . $result['return_code']);
            foreach ($result['output'] as $line) {
                $this->logger->error("  $line");
            }
            $this->logger->warning("You may need to run migrations manually or fix issues first");
        }
    }
}