<?php

namespace NDEstates\LaravelModelSchemaChecker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationGenerator;
use NDEstates\LaravelModelSchemaChecker\Services\DataExporter;
use NDEstates\LaravelModelSchemaChecker\Services\DataImporter;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationCleanup;

class ModelSchemaCheckCommand extends Command
{
        protected $description = 'Comprehensive Laravel application validation: models, relationships, security, performance, code quality, and form analysis with amendment suggestions';

    protected $signature = 'model:schema-check
                            {--dry-run : Show what would be changed without making changes}
                            {--fix : Fix model fillable properties}
                            {--fix-forms : Automatically fix form issues}
                            {--json : Output results in JSON format}
                            {--all : Run all available checks}
                            {--generate-migrations : Generate Laravel migrations}
                            {--run-migrations : Run generated migrations}
                            {--backup-db : Create database backup before making changes}
                            {--backup : Create database backup}
                            {--analyze : Run comprehensive analysis}
                            {--generate-schema : Generate schema documentation}
                            {--generate-schema-sql : Generate schema SQL}
                            {--check-filament : Check Filament relationships}
                            {--check-security : Check for security vulnerabilities}
                            {--check-relationships : Check model relationships and foreign keys}
                            {--check-migrations : Check migration consistency, indexes, and foreign keys}
                            {--check-validation : Check validation rules against database schema}
                            {--check-performance : Check for N+1 queries and optimization opportunities}
                            {--check-code-quality : Check Laravel best practices and code quality}
                            {--check-code-quality-path=* : Specify paths to check for code quality (can be used multiple times)}
                            {--check-code-quality-exclude=* : Exclude specific paths from code quality checks (can be used multiple times)}
                            {--check-models : Check model quality (fillable, relationships, etc.)}
                            {--check-models-exclude=* : Exclude specific model files from checks (can be used multiple times)}
                            {--check-controllers : Check controller quality and best practices}
                            {--check-controllers-exclude=* : Exclude specific controller files from checks (can be used multiple times)}
                            {--check-migrations-quality : Check migration file quality and best practices}
                            {--check-migrations-quality-exclude=* : Exclude specific migration files from quality checks (can be used multiple times)}
                            {--check-laravel-forms : Check Blade templates and Livewire forms}
                            {--check-encrypted-fields : Check encrypted fields in database, models, controllers, and views}
                            {--sync-migrations : Generate fresh migrations from database schema}
                            {--export-data : Export database data to compressed SQL file}
                            {--import-data : Import database data from compressed SQL file}
                            {--cleanup-migrations : Safely remove old migration files with backup}
                            {--check-all : Run all available checks (alias for --all)}
                            {--fix-migrations : Generate alter migrations to fix detected migration issues}
                            {--rollback-migrations : Rollback the last batch of migrations}
                            {--amend-migrations : Amend existing migration files with proper column specifications}
                            {--save-output : Save results to a dated Markdown file for review}
                            {--step-by-step : Apply fixes interactively one by one instead of all at once}
                            {--rollback-fixes : Rollback previously applied automatic fixes}';
    protected CheckerManager $checkerManager;
    protected IssueManager $issueManager;
    protected MigrationGenerator $migrationGenerator;
    protected DataExporter $dataExporter;
    protected DataImporter $dataImporter;
    protected MigrationCleanup $migrationCleanup;

    public function __construct(CheckerManager $checkerManager, IssueManager $issueManager, MigrationGenerator $migrationGenerator, DataExporter $dataExporter, DataImporter $dataImporter, MigrationCleanup $migrationCleanup)
    {
        parent::__construct();
        $this->checkerManager = $checkerManager;
        $this->issueManager = $issueManager;
        $this->migrationGenerator = $migrationGenerator;
        $this->dataExporter = $dataExporter;
        $this->dataImporter = $dataImporter;
        $this->migrationCleanup = $migrationCleanup;
    }

    public function handle()
    {
        // PRODUCTION SAFETY: This tool is designed for development only
        if ($this->isProductionEnvironment()) {
            $this->error('🚫 SECURITY ERROR: Laravel Model Schema Checker is disabled in production environments.');
            $this->error('');
            $this->error('This tool is designed exclusively for development and testing environments.');
            $this->error('Running schema analysis tools in production can pose significant security risks.');
            $this->error('');
            $this->error('If you believe this is an error, please check your APP_ENV setting.');
            return 1;
        }

        $this->info('Laravel Model Schema Checker v3.0');
        $this->info('=====================================');

        if ($this->option('dry-run')) {
            $this->warn('Running in DRY-RUN mode - no changes will be made');
        }

        // Route to appropriate functionality based on options
        if ($this->option('backup') || $this->option('backup-db')) {
            return $this->handleBackup();
        }

        if ($this->option('generate-migrations')) {
            return $this->handleGenerateMigrations();
        }

        if ($this->option('run-migrations')) {
            return $this->handleRunMigrations();
        }

        if ($this->option('analyze')) {
            return $this->handleAnalyze();
        }

        if ($this->option('generate-schema')) {
            return $this->handleGenerateSchema();
        }

        if ($this->option('generate-schema-sql')) {
            return $this->handleGenerateSchemaSql();
        }

        if ($this->option('check-filament')) {
            return $this->handleCheckFilament();
        }

        if ($this->option('check-security')) {
            return $this->handleCheckSecurity();
        }

        if ($this->option('check-relationships')) {
            return $this->handleCheckRelationships();
        }

        if ($this->option('check-migrations')) {
            return $this->handleCheckMigrations();
        }

        if ($this->option('check-validation')) {
            return $this->handleCheckValidation();
        }

        if ($this->option('check-performance')) {
            return $this->handleCheckPerformance();
        }

        if ($this->option('check-code-quality')) {
            return $this->handleCheckCodeQuality();
        }

        if ($this->option('check-models')) {
            return $this->handleCheckModels();
        }

        if ($this->option('check-controllers')) {
            return $this->handleCheckControllers();
        }

        if ($this->option('check-migrations-quality')) {
            return $this->handleCheckMigrationsQuality();
        }

        if ($this->option('check-laravel-forms')) {
            return $this->handleCheckLaravelForms();
        }

        if ($this->option('check-encrypted-fields')) {
            return $this->handleCheckEncryptedFields();
        }

        if ($this->option('sync-migrations')) {
            return $this->handleSyncMigrations();
        }

        if ($this->option('export-data')) {
            return $this->handleExportData();
        }

        if ($this->option('import-data')) {
            return $this->handleImportData();
        }

        if ($this->option('cleanup-migrations')) {
            return $this->handleCleanupMigrations();
        }

        if ($this->option('fix-migrations')) {
            return $this->handleFixMigrations();
        }

        if ($this->option('rollback-migrations')) {
            return $this->handleRollbackMigrations();
        }

        if ($this->option('amend-migrations')) {
            return $this->handleAmendMigrations();
        }

        if ($this->option('rollback-fixes')) {
            return $this->handleRollbackFixes();
        }

        // Default: run model checks
        return $this->handleModelChecks();
    }

    protected function handleModelChecks(): int
    {
        // Set command on checker manager for output
        $this->checkerManager->setCommand($this);

        // Run the checks
        $issues = $this->checkerManager->runAllChecks();

        // Display results
        $this->displayResults();

        // Always return success - don't fail on issues
        return Command::SUCCESS;
    }

    protected function handleGenerateMigrations(): int
    {
        $this->info('Generating migrations...');
        $this->warn('⚠️  Migration generation not yet implemented in v3.0');
        $this->info('This feature will be available in a future update.');
        return Command::SUCCESS;
    }

    protected function handleRunMigrations(): int
    {
        $this->info('Running migrations...');
        $this->warn('⚠️  Migration running not yet implemented in v3.0');
        $this->info('Please run migrations manually: php artisan migrate');
        return Command::SUCCESS;
    }

    protected function handleAnalyze(): int
    {
        $this->info('Running comprehensive analysis...');
        $this->warn('⚠️  Analysis functionality not yet implemented in v3.0');
        $this->info('Running basic model checks instead...');
        return $this->handleModelChecks();
    }

    protected function handleGenerateSchema(): int
    {
        $this->info('Generating schema documentation...');
        $this->warn('⚠️  Schema generation not yet implemented in v3.0');
        $this->info('This feature will be available in a future update.');
        return Command::SUCCESS;
    }

    protected function handleGenerateSchemaSql(): int
    {
        $this->info('Generating schema SQL...');
        $this->warn('⚠️  Schema SQL generation not yet implemented in v3.0');
        $this->info('This feature will be available in a future update.');
        return Command::SUCCESS;
    }

    protected function handleCheckFilament(): int
    {
        $this->info('Checking Filament relationships...');

        $checker = $this->checkerManager->getChecker('filament');
        if (!$checker) {
            $this->error('FilamentChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckSecurity(): int
    {
        $this->info('Checking for security vulnerabilities...');

        $checker = $this->checkerManager->getChecker('security');
        if (!$checker) {
            $this->error('SecurityChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckEncryptedFields(): int
    {
        $this->info('Checking encrypted fields in database, models, controllers, and views...');

        $this->checkEncryptedFieldSizes();
        $this->checkModelEncryption();
        $this->checkControllerEncryption();
        $this->checkViewEncryptionExposure();

        $this->displayResults();

        if ($this->option('fix') && !$this->option('dry-run')) {
            $this->fixEncryptedFieldIssues();
        }

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckRelationships(): int
    {
        $this->info('Checking model relationships...');

        $checker = $this->checkerManager->getChecker('relationship');
        if (!$checker) {
            $this->error('RelationshipChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckMigrations(): int
    {
        $this->info('Checking migration consistency...');

        $checker = $this->checkerManager->getChecker('migration');
        if (!$checker) {
            $this->error('MigrationChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckValidation(): int
    {
        $this->info('Checking validation rules...');

        $checker = $this->checkerManager->getChecker('validation');
        if (!$checker) {
            $this->error('ValidationChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckPerformance(): int
    {
        $this->info('Checking for performance issues...');

        $checker = $this->checkerManager->getChecker('performance');
        if (!$checker) {
            $this->error('PerformanceChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckCodeQuality(): int
    {
        $this->info('Checking code quality...');

        $checker = $this->checkerManager->getChecker('code quality');
        if (!$checker) {
            $this->error('CodeQualityChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        // Set path filtering options
        $includePaths = $this->option('check-code-quality-path') ?: [];
        $excludePaths = $this->option('check-code-quality-exclude') ?: [];

        if (!empty($includePaths) || !empty($excludePaths)) {
            $checker->setPathFilters($includePaths, $excludePaths);
        }

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckModels(): int
    {
        $this->info('Checking model quality...');

        $checker = $this->checkerManager->getChecker('code quality');
        if (!$checker) {
            $this->error('CodeQualityChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        // Automatically include Models path and handle excludes
        $excludePaths = $this->option('check-models-exclude') ?: [];
        $checker->setPathFilters(['Models'], $excludePaths);

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckControllers(): int
    {
        $this->info('Checking controller quality...');

        $checker = $this->checkerManager->getChecker('code quality');
        if (!$checker) {
            $this->error('CodeQualityChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        // Automatically include Controllers path and handle excludes
        $excludePaths = $this->option('check-controllers-exclude') ?: [];
        $checker->setPathFilters(['Http/Controllers'], $excludePaths);

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckMigrationsQuality(): int
    {
        $this->info('Checking migration quality...');

        $checker = $this->checkerManager->getChecker('code quality');
        if (!$checker) {
            $this->error('CodeQualityChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        // Automatically include migrations path and handle excludes
        $excludePaths = $this->option('check-migrations-quality-exclude') ?: [];
        $checker->setPathFilters(['migrations'], $excludePaths);

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function handleCheckLaravelForms(): int
    {
        $this->info('Checking Laravel forms...');

        $checker = $this->checkerManager->getChecker('laravel forms');
        if (!$checker) {
            $this->error('LaravelFormsChecker not found. Make sure it is properly registered.');
            return Command::FAILURE;
        }

        $issues = $checker->check();
        $this->issueManager->addIssues($issues);

        $this->displayResults();

        // Apply automatic fixes if requested
        if ($this->option('fix-forms') && !$this->option('dry-run')) {
            $this->applyFormFixes($checker);
        }

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
    }

    protected function applyFormFixes($checker): void
    {
        if (!method_exists($checker, 'applyAutomaticFixes')) {
            $this->warn('Form checker does not support automatic fixes.');
            return;
        }

        // Check if file writes are allowed in config
        $config = config('model-schema-checker');
        if (!($config['output']['allow_file_writes'] ?? false)) {
            $this->error('❌ Automatic form fixes are disabled in configuration.');
            $this->line('');
            $this->line('To enable automatic fixes, set the following in your config/model-schema-checker.php:');
            $this->line('    \'output\' => [');
            $this->line('        \'allow_file_writes\' => true,');
            $this->line('        // ... other output settings');
            $this->line('    ],');
            $this->line('');
            $this->line('Or set the environment variable: MSC_ALLOW_FILE_WRITES=true');
            return;
        }

        $this->info('');
        $this->info('🔧 Applying Automatic Form Fixes...');

        $results = $checker->applyAutomaticFixes();

        $this->info("✅ Fixed: {$results['fixed']} issues");
        $this->info("⏭️  Skipped: {$results['skipped']} issues");
        $this->info("❌ Errors: {$results['errors']} issues");

        if (!empty($results['details'])) {
            $this->info('');
            $this->info('Fix Details:');
            foreach ($results['details'] as $detail) {
                $this->line("  • {$detail}");
            }
        }
    }

    protected function handleSyncMigrations(): int
    {
        $this->info('Generating migrations from database schema...');

        try {
            $migrations = $this->migrationGenerator->generateMigrationsFromSchema();

            if (empty($migrations)) {
                $this->info('No new migrations needed. All tables already have corresponding migrations.');
                return Command::SUCCESS;
            }

            $this->info("Generated " . count($migrations) . " migration(s):");

            foreach ($migrations as $migration) {
                $this->line("  - {$migration['filename']} (for table: {$migration['table']})");
            }

            if ($this->confirm('Do you want to save these migrations to disk?', true)) {
                $savedFiles = $this->migrationGenerator->saveMigrations();

                $this->info('Migrations saved successfully:');
                foreach ($savedFiles as $file) {
                    $this->line("  - {$file}");
                }

                $this->warn('Remember to review the generated migrations before running them!');
            } else {
                $this->info('Migrations were not saved. Use --sync-migrations again to save them.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to generate migrations: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function handleExportData(): int
    {
        $this->info('Exporting database data...');

        try {
            $exportFile = $this->dataExporter->exportDatabaseDataToCompressedFile();

            $this->info('Database data exported successfully!');
            $this->line("Export file: {$exportFile}");

            $fileSize = File::size($exportFile);
            $this->line("File size: " . number_format($fileSize / 1024 / 1024, 2) . " MB");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to export database data: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function handleImportData(): int
    {
        $filePath = $this->ask('Enter the path to the SQL file to import');

        if (!$filePath) {
            $this->error('No file path provided.');
            return Command::FAILURE;
        }

        if (!File::exists($filePath)) {
            $this->error("File does not exist: {$filePath}");
            return Command::FAILURE;
        }

        $this->info('Validating import file...');

        try {
            $validationIssues = $this->dataImporter->validateImportFile($filePath);

            if (!empty($validationIssues)) {
                $this->error('Import file validation failed:');
                foreach ($validationIssues as $issue) {
                    $this->line("  - {$issue}");
                }
                return Command::FAILURE;
            }

            $this->info('Getting import preview...');
            $preview = $this->dataImporter->getImportPreview($filePath);

            $this->info('Import Preview:');
            $this->line("  - Total statements: {$preview['total_statements']}");
            $this->line("  - Tables to create: " . implode(', ', $preview['tables_to_create']));
            $this->line("  - Tables to import: " . implode(', ', $preview['tables_to_import']));
            $this->line("  - Estimated rows: {$preview['estimated_rows']}");

            if (!empty($preview['warnings'])) {
                $this->warn('Warnings:');
                foreach ($preview['warnings'] as $warning) {
                    $this->line("  - {$warning}");
                }
            }

            if (!$this->confirm('Do you want to proceed with the import?', false)) {
                $this->info('Import cancelled.');
                return Command::SUCCESS;
            }

            $this->info('Starting database import...');

            $options = [];
            if ($this->option('dry-run')) {
                $options['dry_run'] = true;
                $this->warn('Running in dry-run mode - no changes will be made');
            }

            $result = $this->dataImporter->importDatabaseData($filePath, $options);

            if ($result['success']) {
                $this->info('Import completed successfully!');
                $this->line("  - Tables imported: {$result['tables_imported']}");
                $this->line("  - Rows imported: {$result['rows_imported']}");

                if (!empty($result['warnings'])) {
                    $this->warn('Warnings:');
                    foreach ($result['warnings'] as $warning) {
                        $this->line("  - {$warning}");
                    }
                }
            } else {
                $this->error('Import failed:');
                foreach ($result['errors'] as $error) {
                    $this->line("  - {$error}");
                }
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function handleCleanupMigrations(): int
    {
        $this->info('Migration cleanup utility');
        $this->info('=======================');

        // Get cleanup preview
        $preview = $this->migrationCleanup->getCleanupPreview();

        $this->info('Current migration status:');
        $this->line("  - Total migration files: {$preview['total_migration_files']}");
        $this->line("  - Files that can be cleaned: {$preview['files_to_delete']}");
        $this->line("  - Space that can be saved: " . number_format($preview['total_size_to_save'] / 1024, 2) . " KB");

        if ($preview['files_to_delete'] === 0) {
            $this->info('No migration files need cleanup.');
            return Command::SUCCESS;
        }

        // Show files that would be deleted
        $this->info('Files to be cleaned up:');
        foreach ($preview['files'] as $file) {
            $this->line("  - {$file['filename']} (" . number_format($file['size'] / 1024, 2) . " KB)");
        }

        // Ask for cleanup options
        $cleanupType = $this->choice(
            'What type of cleanup would you like to perform?',
            [
                'preview' => 'Show detailed preview only',
                'all' => 'Clean all eligible files',
                'older_than' => 'Clean files older than specified days',
                'larger_than' => 'Clean files larger than specified KB',
                'custom' => 'Custom cleanup criteria'
            ],
            'preview'
        );

        $options = [];

        switch ($cleanupType) {
            case 'older_than':
                $days = (int) $this->ask('Clean files older than how many days?', 30);
                $options['older_than_days'] = $days;
                break;

            case 'larger_than':
                $size = (int) $this->ask('Clean files larger than how many KB?', 100);
                $options['larger_than_kb'] = $size;
                break;

            case 'custom':
                if ($this->confirm('Include system tables (migrations, jobs, etc.)?', false)) {
                    $options['include_system_tables'] = true;
                }
                if ($this->confirm('Skip backup creation?', false)) {
                    $options['no_backup'] = true;
                }
                break;

            case 'preview':
                return Command::SUCCESS;

            case 'all':
            default:
                // Use default options
                break;
        }

        if ($cleanupType !== 'preview') {
            if (!$this->confirm('Do you want to proceed with the cleanup?', false)) {
                $this->info('Cleanup cancelled.');
                return Command::SUCCESS;
            }

            if ($this->option('dry-run')) {
                $options['dry_run'] = true;
                $this->warn('Running in dry-run mode - no files will be deleted');
            }

            $this->info('Starting migration cleanup...');

            try {
                $result = $this->migrationCleanup->cleanupMigrationFiles($options);

                $this->info('Cleanup completed!');
                $this->line("  - Files backed up: {$result['files_backed_up']}");
                $this->line("  - Files deleted: {$result['files_deleted']}");
                $this->line("  - Space saved: " . number_format($result['total_size_saved'] / 1024, 2) . " KB");

                if (!empty($result['warnings'])) {
                    $this->warn('Warnings:');
                    foreach ($result['warnings'] as $warning) {
                        $this->line("  - {$warning}");
                    }
                }

                if (!empty($result['errors'])) {
                    $this->error('Errors:');
                    foreach ($result['errors'] as $error) {
                        $this->line("  - {$error}");
                    }
                }
            } catch (\Exception $e) {
                $this->error("Cleanup failed: {$e->getMessage()}");
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    protected function displayResults(): void
    {
        $stats = $this->issueManager->getStats();

        if ($this->option('json')) {
            $this->outputJson();
            return;
        }

        // Save to file if requested
        if ($this->option('save-output')) {
            $this->saveResultsToFile();
        }

        $this->info('');
        $this->info('Results Summary');
        $this->info('===============');

        if ($stats['total_issues'] === 0) {
            $this->info('✅ No issues found!');
            return;
        }

        $this->warn("⚠️  Found {$stats['total_issues']} issue(s)");

        // Display issues
        $issues = $this->issueManager->getIssues();
        foreach ($issues as $issue) {
            $this->line("  • " . ($issue['message'] ?? $issue['type']));
            if (isset($issue['file'])) {
                $this->line("    📁 " . $issue['file']);
            }
        }

        // Display code improvements
        $this->displayCodeImprovements($issues);
    }

    protected function saveResultsToFile(): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "model-schema-check_{$timestamp}.md";
        $filepath = base_path($filename);

        $content = $this->generateMarkdownContent();

        File::put($filepath, $content);

        $this->info("📄 Results saved to: {$filepath}");
        $this->info("📄 Filename: {$filename}");
        $this->info("💡 This file is saved in your project root directory for easy access");
    }

    protected function generateMarkdownContent(): string
    {
        $stats = $this->issueManager->getStats();
        $issues = $this->issueManager->getIssues();

        $content = "# Laravel Model Schema Checker Results\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";
        $content .= "**Command:** `php artisan model:schema-check`\n\n";
        $content .= "**File Location:** This report is saved in your project root directory for easy access.\n\n";

        $content .= "## Results Summary\n\n";

        if ($stats['total_issues'] === 0) {
            $content .= "✅ **No issues found!**\n\n";
            return $content;
        }

        $content .= "⚠️ **Found {$stats['total_issues']} issue(s)**\n\n";

        // Group issues by type
        $issuesByType = [];
        foreach ($issues as $issue) {
            $type = $issue['type'] ?? 'Unknown';
            if (!isset($issuesByType[$type])) {
                $issuesByType[$type] = [];
            }
            $issuesByType[$type][] = $issue;
        }

        // Display issues grouped by type
        foreach ($issuesByType as $type => $typeIssues) {
            $content .= "### {$type} Issues (" . count($typeIssues) . ")\n\n";
            foreach ($typeIssues as $issue) {
                $content .= "- " . ($issue['message'] ?? $issue['type']) . "\n";
                if (isset($issue['file'])) {
                    $content .= "  - 📁 `{$issue['file']}`\n";
                }
                $content .= "\n";
            }
        }

        // Display code improvements
        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));
        if (!empty($improvements)) {
            $content .= "## Code Improvement Suggestions (" . count($improvements) . ")\n\n";

            foreach ($improvements as $index => $issue) {
                $improvement = $issue['improvement'];
                $content .= "### " . ($index + 1) . ". {$improvement->getTitle()}\n\n";
                $content .= "{$improvement->getDescription()}\n\n";

                if ($improvement->canAutoFix()) {
                    $content .= "**✅ Can be automatically fixed**\n\n";
                }

                if (isset($issue['file'])) {
                    $content .= "**File:** `{$issue['file']}`\n\n";
                }

                $content .= "---\n\n";
            }
        }

        $content .= "## Next Steps\n\n";
        $content .= "1. **Review the issues above** and prioritize fixes based on severity\n";
        $content .= "2. **Apply automatic fixes** where available using `--fix` option\n";
        $content .= "3. **Use step-by-step fixes** with `--step-by-step` for controlled application\n";
        $content .= "4. **Backup before changes** using `--backup-db` option\n";
        $content .= "5. **Rollback if needed** using `--rollback-fixes` option\n\n";

        $content .= "## Command Reference\n\n";
        $content .= "- `php artisan model:schema-check --save-output` - Save results to file\n";
        $content .= "- `php artisan model:schema-check --step-by-step` - Interactive fix application\n";
        $content .= "- `php artisan model:schema-check --fix` - Apply all automatic fixes\n";
        $content .= "- `php artisan model:schema-check --backup-db` - Create database backup\n";
        $content .= "- `php artisan model:schema-check --rollback-fixes` - Rollback applied fixes\n";

        return $content;
    }

    protected function displayCodeImprovements(array $issues): void
    {
        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));

        if (empty($improvements)) {
            return;
        }

        $this->info('');
        $this->info('💡 Code Improvement Suggestions (' . count($improvements) . '):');

        if ($this->option('step-by-step')) {
            $this->applyStepByStepFixes($improvements);
        } else {
            foreach ($improvements as $issue) {
                $improvement = $issue['improvement'];
                $this->info("  • {$improvement->getTitle()}");
                $this->line("    {$improvement->getDescription()}");

                if ($improvement->canAutoFix()) {
                    $this->info("    ✅ Can be automatically fixed");
                }
            }

            if (!$this->option('dry-run') && $this->confirm('Apply automatic fixes?', false)) {
                $this->applyCodeImprovements($improvements);
            }
        }
    }

    protected function applyCodeImprovements(array $improvements): void
    {
        $applied = 0;
        $appliedFixes = [];

        foreach ($improvements as $issue) {
            $improvement = $issue['improvement'];

            if ($improvement->canAutoFix() && $improvement->applyFix()) {
                $this->info("✅ Applied: {$improvement->getTitle()}");
                $applied++;

                // Track applied fix for rollback
                $appliedFixes[] = [
                    'title' => $improvement->getTitle(),
                    'description' => $improvement->getDescription(),
                    'file' => $issue['file'] ?? null,
                    'applied_at' => now()->toISOString(),
                    'improvement_class' => get_class($improvement),
                ];
            }
        }

        if ($applied > 0) {
            $this->info('');
            $this->info("🎉 Successfully applied {$applied} automatic fixes!");

            // Save applied fixes for rollback
            $this->saveAppliedFixes($appliedFixes);
        }
    }

    protected function applyStepByStepFixes(array $improvements): void
    {
        $this->info('');
        $this->info('🔧 Step-by-step fix mode enabled');
        $this->info('You will be prompted for each fix individually.');

        $applied = 0;
        $skipped = 0;
        $appliedFixes = [];

        foreach ($improvements as $index => $issue) {
            $improvement = $issue['improvement'];
            $number = $index + 1;

            $this->info('');
            $this->info("--- Fix {$number} of " . count($improvements) . " ---");
            $this->info("📋 {$improvement->getTitle()}");
            $this->line("   {$improvement->getDescription()}");

            if (isset($issue['file'])) {
                $this->line("   📁 {$issue['file']}");
            }

            if ($improvement->canAutoFix()) {
                $this->info("   ✅ Can be automatically fixed");

                $apply = $this->choice(
                    "Apply this fix?",
                    ['yes' => 'Yes, apply this fix', 'no' => 'No, skip this fix', 'quit' => 'Quit step-by-step mode'],
                    'no'
                );

                if ($apply === 'quit') {
                    $this->info('🛑 Step-by-step mode cancelled by user.');
                    break;
                }

                if ($apply === 'yes') {
                    if (!$this->option('dry-run') && $improvement->applyFix()) {
                        $this->info("✅ Applied: {$improvement->getTitle()}");
                        $applied++;

                        // Track applied fix for rollback
                        $appliedFixes[] = [
                            'title' => $improvement->getTitle(),
                            'description' => $improvement->getDescription(),
                            'file' => $issue['file'] ?? null,
                            'applied_at' => now()->toISOString(),
                            'improvement_class' => get_class($improvement),
                        ];
                    } else {
                        $this->warn("❌ Failed to apply: {$improvement->getTitle()}");
                    }
                } else {
                    $this->line("⏭️  Skipped: {$improvement->getTitle()}");
                    $skipped++;
                }
            } else {
                $this->warn("   ⚠️  Manual fix required");
                $this->line("   Please fix manually as described above.");
                $skipped++;
            }
        }

        $this->info('');
        $this->info("📊 Step-by-step results:");
        $this->info("   ✅ Applied: {$applied}");
        $this->info("   ⏭️  Skipped: {$skipped}");

        if ($applied > 0) {
            $this->info('');
            $this->info("🎉 Successfully applied {$applied} fixes!");

            // Save applied fixes for rollback
            $this->saveAppliedFixes($appliedFixes);
        }
    }

    protected function outputJson(): void
    {
        $result = [
            'timestamp' => now()->toISOString(),
            'stats' => $this->issueManager->getStats(),
            'issues' => $this->issueManager->getIssues(),
            'checkers' => $this->checkerManager->getAvailableCheckers(),
        ];

        $jsonResult = json_encode($result, JSON_PRETTY_PRINT);
        if ($jsonResult === false) {
            $this->error('Failed to encode result to JSON');
            return;
        }

        $this->line($jsonResult);
    }

    protected function checkEncryptedFieldSizes(): void
    {
        $this->info('  🔍 Checking database schema for encrypted field sizes...');

        $tables = DB::select('SHOW TABLES');
        $databaseName = DB::getDatabaseName();

        foreach ($tables as $table) {
            $tableName = $table->{'Tables_in_' . $databaseName};

            // Skip system tables and common non-encrypted tables
            if (in_array($tableName, ['migrations', 'failed_jobs', 'cache', 'sessions', 'jobs'])) {
                continue;
            }

            $columns = DB::select("
                SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
            ", [$databaseName, $tableName]);

            foreach ($columns as $column) {
                $columnName = $column->COLUMN_NAME;
                $dataType = $column->DATA_TYPE;
                $maxLength = $column->CHARACTER_MAXIMUM_LENGTH;
                $columnType = $column->COLUMN_TYPE;

                // Check for potentially encrypted fields based on naming patterns
                $encryptedFieldPatterns = [
                    '/encrypted/i',
                    '/cipher/i',
                    '/token/i',
                    '/secret/i',
                    '/key/i',
                    '/password/i',
                    '/ssn/i',
                    '/social_security/i',
                    '/credit_card/i',
                    '/api_key/i'
                ];

                $isLikelyEncrypted = false;
                foreach ($encryptedFieldPatterns as $pattern) {
                    if (preg_match($pattern, $columnName)) {
                        $isLikelyEncrypted = true;
                        break;
                    }
                }

                if ($isLikelyEncrypted) {
                    // Check if field size is adequate for encrypted data
                    $recommendedMinSize = 255; // Laravel encrypted fields need at least 255 chars

                    if ($dataType === 'varchar' && $maxLength < $recommendedMinSize) {
                        $this->issueManager->addIssue('Encrypted Fields', 'insufficient_field_size', [
                            'table' => $tableName,
                            'column' => $columnName,
                            'current_size' => $maxLength,
                            'recommended_size' => $recommendedMinSize,
                            'message' => "Encrypted field '{$columnName}' in table '{$tableName}' has insufficient size ({$maxLength} chars). Encrypted data requires at least {$recommendedMinSize} characters.",
                            'fix_explanation' => 'Laravel encryption adds significant overhead. Encrypted data can be 2-3x larger than plaintext due to base64 encoding and encryption metadata.',
                            'security_note' => 'Always encrypt sensitive data before storing. Use Laravel\'s encrypt() helper or model casts for automatic encryption/decryption. Encrypt at the model level using $casts or mutators, not in controllers or views, to ensure data is never exposed in transit.'
                        ]);
                    } elseif ($dataType === 'text' && in_array(strtolower($columnType), ['tinytext', 'text'])) {
                        $this->issueManager->addIssue('Encrypted Fields', 'suboptimal_field_type', [
                            'table' => $tableName,
                            'column' => $columnName,
                            'current_type' => $columnType,
                            'recommended_type' => 'MEDIUMTEXT or LONGTEXT',
                            'message' => "Encrypted field '{$columnName}' in table '{$tableName}' uses '{$columnType}' which may be too small for encrypted data.",
                            'fix_explanation' => 'Use MEDIUMTEXT (16MB) or LONGTEXT (4GB) for encrypted fields to accommodate variable encrypted data sizes.',
                            'security_note' => 'Always encrypt sensitive data before storing. Use Laravel\'s encrypt() helper or model casts for automatic encryption/decryption. Use MEDIUMTEXT (16MB) or LONGTEXT (4GB) for encrypted fields to accommodate variable encrypted data sizes.'
                        ]);
                    }
                }
            }
        }
    }

    protected function checkModelEncryption(): void
    {
        $this->info('  🔍 Checking models for proper encryption implementation...');

        $modelPath = app_path('Models');
        if (!File::exists($modelPath)) {
            return;
        }

        $modelFiles = File::allFiles($modelPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                if ($content === false) {
                    continue; // Skip files that cannot be read
                }

                $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                // Check for encrypted casts
                if (preg_match('/protected\s+\$casts\s*=\s*\[([^\]]*)\]/s', $content, $matches)) {
                    $castsContent = $matches[1];

                    // Look for encrypted casts
                    if (preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*[\'"]encrypted[\'"]/', $castsContent, $castMatches)) {
                        foreach ($castMatches[1] as $fieldName) {
                            // Check if the field exists in the database
                            $tableName = $this->getTableNameFromModel($content, $className);
                            if ($tableName && !$this->columnExists($tableName, $fieldName)) {
                                $this->issueManager->addIssue('Model Encryption', 'missing_encrypted_column', [
                                    'model' => $className,
                                    'field' => $fieldName,
                                    'table' => $tableName,
                                    'message' => "Model '{$className}' defines encrypted cast for '{$fieldName}' but column doesn't exist in table '{$tableName}'.",
                                    'fix_explanation' => 'Create a migration to add the encrypted column with sufficient size (VARCHAR(255) minimum, TEXT recommended).'
                                ]);
                            }
                        }
                    }
                }

                // Check for manual encryption in mutators/accessors
                if (preg_match_all('/function\s+set(\w+)Attribute\s*\(/', $content, $mutatorMatches)) {
                    foreach ($mutatorMatches[1] as $fieldName) {
                        $fieldNameLower = lcfirst($fieldName);
                        if (preg_match('/encrypt\(/', $content)) {
                            // Check if decryption is also implemented
                            if (!preg_match('/function\s+get' . $fieldName . 'Attribute\s*\(/', $content)) {
                                $this->issueManager->addIssue('Model Encryption', 'missing_decrypt_accessor', [
                                    'model' => $className,
                                    'field' => $fieldNameLower,
                                    'message' => "Model '{$className}' encrypts '{$fieldNameLower}' but doesn't provide a getter to decrypt it.",
                                    'fix_explanation' => 'Add a get' . $fieldName . 'Attribute accessor that calls decrypt() to automatically decrypt the value when accessed.'
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function checkControllerEncryption(): void
    {
        $this->info('  🔍 Checking controllers for encryption security issues...');

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

                $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                // Check for direct encryption in controllers (security risk)
                if (preg_match('/encrypt\s*\(/', $content)) {
                    $this->issueManager->addIssue('Controller Security', 'encryption_in_controller', [
                        'controller' => $className,
                        'file' => $file->getPathname(),
                        'message' => "Controller '{$className}' contains direct encryption calls, which is a security risk.",
                        'fix_explanation' => 'Move encryption logic to model mutators/accessors or use encrypted casts. Controllers should never handle raw encrypted data.',
                        'security_note' => 'Always encrypt sensitive data before storing. Use Laravel\'s encrypt() helper or model casts for automatic encryption/decryption. Encrypting in controllers exposes sensitive data in transit between model and view. Always encrypt at the model level.'
                    ]);
                }

                // Check for decrypt calls in controllers (also risky)
                if (preg_match('/decrypt\s*\(/', $content)) {
                    $this->issueManager->addIssue('Controller Security', 'decryption_in_controller', [
                        'controller' => $className,
                        'file' => $file->getPathname(),
                        'message' => "Controller '{$className}' contains direct decryption calls.",
                        'fix_explanation' => 'Use model accessors or encrypted casts for automatic decryption. Avoid manual decrypt operations in controllers.',
                        'security_note' => 'Always encrypt sensitive data before storing. Use Laravel\'s encrypt() helper or model casts for automatic encryption/decryption. Data should be decrypted as close to usage as possible, preferably in models or accessors, not controllers.'
                    ]);
                }

                // Check for sensitive data being passed to views
                $sensitivePatterns = [
                    '/compact\s*\([^)]*(password|secret|token|key|ssn|credit_card)[^)]*\)/i',
                    '/with\s*\([^)]*(password|secret|token|key|ssn|credit_card)[^)]*\)/i',
                    '/->(\w*(password|secret|token|key|ssn|credit_card)\w*)/i'
                ];

                foreach ($sensitivePatterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $this->issueManager->addIssue('Controller Security', 'sensitive_data_to_view', [
                            'controller' => $className,
                            'file' => $file->getPathname(),
                            'message' => "Controller '{$className}' appears to be passing sensitive data to views.",
                            'fix_explanation' => 'Ensure sensitive data is encrypted before storage and decrypted only when needed. Never pass raw sensitive data to views.',
                            'security_note' => 'Always encrypt sensitive data before storing. Use Laravel\'s encrypt() helper or model casts for automatic encryption/decryption. Views should never receive sensitive data. Use encrypted casts in models to handle encryption/decryption automatically.'
                        ]);
                        break; // Only report once per controller
                    }
                }
            }
        }
    }

    protected function checkViewEncryptionExposure(): void
    {
        $this->info('  🔍 Checking views for potential encryption exposure...');

        $viewPath = resource_path('views');
        if (!File::exists($viewPath)) {
            return;
        }

        $viewFiles = File::allFiles($viewPath);

        foreach ($viewFiles as $file) {
            if (in_array($file->getExtension(), ['blade.php', 'php'])) {
                $content = file_get_contents($file->getPathname());

                if ($content === false) {
                    continue; // Skip files that cannot be read
                }

                $relativePath = str_replace(resource_path('views') . '/', '', $file->getPathname());

                // Check for decrypt calls in views (major security issue)
                if (preg_match('/decrypt\s*\(/', $content)) {
                    $this->issueManager->addIssue('View Security', 'decryption_in_view', [
                        'view' => $relativePath,
                        'file' => $file->getPathname(),
                        'message' => "View '{$relativePath}' contains decryption calls, exposing sensitive data.",
                        'fix_explanation' => 'Never decrypt sensitive data in views. Use model accessors or encrypted casts to decrypt data before it reaches the view.',
                        'security_note' => 'Always encrypt sensitive data before storing. Use Laravel\'s encrypt() helper or model casts for automatic encryption/decryption. Views are client-side code. Decrypting in views exposes encryption keys and sensitive data to users.'
                    ]);
                }

                // Check for sensitive field names in views
                $sensitivePatterns = [
                    '/\$(\w*(password|secret|token|key|ssn|credit_card)\w*)/i',
                    '/{{.*(\w*(password|secret|token|key|ssn|credit_card)\w*).*}}/i'
                ];

                foreach ($sensitivePatterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $this->issueManager->addIssue('View Security', 'sensitive_data_in_view', [
                            'view' => $relativePath,
                            'file' => $file->getPathname(),
                            'message' => "View '{$relativePath}' appears to contain sensitive data fields.",
                            'fix_explanation' => 'Ensure sensitive data is properly encrypted and only decrypted when absolutely necessary. Consider using masked or redacted values in views.',
                            'security_note' => 'Always encrypt sensitive data before storing. Use Laravel\'s encrypt() helper or model casts for automatic encryption/decryption. Sensitive data in views can be exposed to users through browser dev tools, network inspection, or page source.'
                        ]);
                        break; // Only report once per view
                    }
                }
            }
        }
    }

    protected function fixEncryptedFieldIssues(): void
    {
        $this->info('🔧 Fixing encrypted field issues...');

        $issues = $this->issueManager->getIssuesByType('Encrypted Fields');

        foreach ($issues as $issue) {
            if ($issue['type'] === 'insufficient_field_size') {
                $this->fixFieldSize($issue['data']);
            } elseif ($issue['type'] === 'suboptimal_field_type') {
                $this->fixFieldType($issue['data']);
            }
        }

        $modelIssues = $this->issueManager->getIssuesByType('Model Encryption');
        foreach ($modelIssues as $issue) {
            if ($issue['type'] === 'missing_encrypted_column') {
                $this->createEncryptedColumnMigration($issue['data']);
            }
        }
    }

    protected function fixFieldSize(array $data): void
    {
        if ($this->option('dry-run')) {
            $this->info("  📝 Would change {$data['table']}.{$data['column']} from VARCHAR({$data['current_size']}) to VARCHAR({$data['recommended_size']})");
            return;
        }

        try {
            $tableName = $data['table'];
            $columnName = $data['column'];
            $newSize = $data['recommended_size'];

            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `{$columnName}` VARCHAR({$newSize})");

            $this->info("  ✅ Changed {$tableName}.{$columnName} to VARCHAR({$newSize})");
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to modify {$data['table']}.{$data['column']}: " . $e->getMessage());
        }
    }

    protected function fixFieldType(array $data): void
    {
        if ($this->option('dry-run')) {
            $this->info("  📝 Would change {$data['table']}.{$data['column']} from {$data['current_type']} to MEDIUMTEXT");
            return;
        }

        try {
            $tableName = $data['table'];
            $columnName = $data['column'];

            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `{$columnName}` MEDIUMTEXT");

            $this->info("  ✅ Changed {$tableName}.{$columnName} to MEDIUMTEXT");
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to modify {$data['table']}.{$data['column']}: " . $e->getMessage());
        }
    }

    protected function createEncryptedColumnMigration(array $data): void
    {
        if ($this->option('dry-run')) {
            $this->info("  📝 Would create migration to add encrypted column {$data['table']}.{$data['field']}");
            return;
        }

        try {
            $tableName = $data['table'];
            $columnName = $data['field'];
            $timestamp = now()->format('Y_m_d_His');
            $migrationName = "add_encrypted_{$columnName}_to_{$tableName}_table";

            $migrationContent = "<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            \$table->text('{$columnName}')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            \$table->dropColumn('{$columnName}');
        });
    }
};";

            $migrationPath = database_path("migrations/{$timestamp}_{$migrationName}.php");
            file_put_contents($migrationPath, $migrationContent);

            $this->info("  ✅ Created migration: {$timestamp}_{$migrationName}.php");
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to create migration for {$data['table']}.{$data['field']}: " . $e->getMessage());
        }
    }

    protected function getTableNameFromModel(string $content, string $className): ?string
    {
        // Try to find table name in the model
        if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }

        // Default to pluralized class name
        return Str::snake(Str::plural($className));
    }

    protected function columnExists(string $tableName, string $columnName): bool
    {
        try {
            $columns = DB::getSchemaBuilder()->getColumnListing($tableName);
            return in_array($columnName, $columns);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function handleBackup(): int
    {
        $this->info('💾 Creating database backup...');

        try {
            $backupPath = $this->dataExporter->exportDatabaseDataToCompressedFile([
                'include_structure' => true,
                'include_data' => true,
            ]);

            $this->info("✅ Database backup created successfully!");
            $this->info("📁 Backup location: {$backupPath}");

            // Get file size
            $size = File::size($backupPath);
            $formattedSize = $this->formatBytes($size);
            $this->info("📊 Backup size: {$formattedSize}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Failed to create database backup: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function handleFixMigrations(): int
    {
        $this->info('🔧 Generating alter migrations to fix detected issues...');
        $this->warn('⚠️  WARNING: Alter migrations can be risky and may cause data loss!');
        $this->warn('⚠️  Always backup your database before running generated migrations.');

        // Check if backup is requested
        if ($this->option('backup-db')) {
            $this->info('💾 Creating database backup before proceeding...');
            $backupResult = $this->handleBackup();
            if ($backupResult !== Command::SUCCESS) {
                $this->error('❌ Backup failed. Aborting migration generation.');
                return Command::FAILURE;
            }
            $this->line('');
        }

        // First run checks to identify issues
        $this->checkerManager->setCommand($this);
        $issues = $this->checkerManager->runAllChecks();

        // Filter for migration-related issues that can be fixed
        $migrationIssues = array_filter($issues, function ($issue) {
            return isset($issue['type']) && str_starts_with($issue['type'], 'migration_');
        });

        if (empty($migrationIssues)) {
            $this->info('✅ No migration issues found that can be automatically fixed.');
            return Command::SUCCESS;
        }

        $this->info("🔍 Found " . count($migrationIssues) . " migration issues that can be fixed.");

        // Generate alter migrations with safety checks
        $alterMigrations = $this->migrationGenerator->generateAlterMigrations($migrationIssues);

        if (empty($alterMigrations)) {
            $this->warn('⚠️  No alter migrations could be generated.');
            return Command::SUCCESS;
        }

        $this->info("📋 Generated " . count($alterMigrations) . " alter migrations:");

        // Display safety information and get user confirmation
        $this->displayMigrationSafetyInfo($alterMigrations);

        if (!$this->option('dry-run')) {
            // Ask about rollback option
            $createRollback = $this->confirm('Create rollback migrations for safety?', true);
            $proceed = $this->confirm('Do you want to proceed with generating these alter migrations?', false);

            if (!$proceed) {
                $this->info('❌ Operation cancelled by user.');
                return Command::SUCCESS;
            }
        }

        foreach ($alterMigrations as $migration) {
            $this->line("📄 <comment>{$migration['name']}.php</comment> - Fixes: {$migration['issue_type']}");

            if (!$this->option('dry-run')) {
                $this->migrationGenerator->saveMigrations();
                $this->info("✅ Migration saved to database/migrations/{$migration['name']}.php");

                // Generate rollback migration if requested
                if (isset($createRollback) && $createRollback) {
                    $rollbackName = 'rollback_' . $migration['name'];
                    $rollbackContent = $this->generateRollbackMigration($migration);
                    $this->saveRollbackMigration($rollbackName, $rollbackContent);
                    $this->info("↩️  Rollback migration saved to database/migrations/{$rollbackName}.php");
                }
            } else {
                $this->line("   <comment>Preview:</comment>");
                $this->line("   " . str_replace("\n", "\n   ", substr($migration['content'], 0, 200)) . "...");
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('🔍 This was a dry-run. Use --fix-migrations without --dry-run to actually create the migration files.');
            $this->info('💡 Tip: Always backup your database before running alter migrations!');
        } else {
            $this->info('✅ Alter migrations generated successfully!');
            if (isset($createRollback) && $createRollback) {
                $this->info('↩️  Rollback migrations created for safety.');
            }
            $this->warn('⚠️  Remember to review and backup before running these migrations!');
            $this->info('💡 To rollback if needed: php artisan migrate:rollback');
        }

        return Command::SUCCESS;
    }

    protected function handleRollbackMigrations(): int
    {
        $this->info('↩️  Rolling back the last batch of migrations...');
        $this->warn('⚠️  WARNING: This will undo the last migration batch!');
        $this->warn('⚠️  Make sure you have a backup before proceeding.');

        // Confirm rollback
        if (!$this->confirm('Are you sure you want to rollback the last migration batch?', false)) {
            $this->info('❌ Rollback cancelled by user.');
            return Command::SUCCESS;
        }

        try {
            // Create backup before rollback
            $this->info('💾 Creating backup before rollback...');
            $backupResult = $this->handleBackup();
            if ($backupResult !== Command::SUCCESS) {
                $this->error('❌ Backup failed. Rollback aborted for safety.');
                return Command::FAILURE;
            }

            // Execute rollback
            $this->info('🔄 Executing migration rollback...');
            $this->call('migrate:rollback');

            $this->info('✅ Migration rollback completed successfully!');
            $this->info('💡 If you need to re-run the migrations: php artisan migrate');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Rollback failed: " . $e->getMessage());
            $this->warn('💡 You may need to manually fix the database state.');
            return Command::FAILURE;
        }
    }

    protected function displayMigrationSafetyInfo(array $alterMigrations): void
    {
        $this->warn('🚨 MIGRATION SAFETY INFORMATION:');
        $this->line('   • These migrations will ALTER existing database tables');
        $this->line('   • Some operations may cause data loss (DROP COLUMN, etc.)');
        $this->line('   • Always backup your database before proceeding');
        $this->line('   • Test migrations on a development database first');
        $this->line('');

        // Analyze risk levels
        $highRiskCount = 0;
        $mediumRiskCount = 0;
        $lowRiskCount = 0;

        foreach ($alterMigrations as $migration) {
            $riskLevel = $migration['risk_level'] ?? 'medium';
            switch ($riskLevel) {
                case 'high':
                    $highRiskCount++;
                    break;
                case 'medium':
                    $mediumRiskCount++;
                    break;
                case 'low':
                    $lowRiskCount++;
                    break;
            }
        }

        if ($highRiskCount > 0) {
            $this->error("🔴 HIGH RISK: {$highRiskCount} migrations may cause data loss");
        }
        if ($mediumRiskCount > 0) {
            $this->warn("🟡 MEDIUM RISK: {$mediumRiskCount} migrations need careful review");
        }
        if ($lowRiskCount > 0) {
            $this->info("🟢 LOW RISK: {$lowRiskCount} migrations are generally safe");
        }

        $this->line('');
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    protected function generateRollbackMigration(array $migration): string
    {
        // Generate a basic rollback migration that reverses the alter operations
        // This is a simplified version - in practice, rollback logic would be more complex
        $tableName = $migration['table'] ?? 'unknown_table';
        $timestamp = date('Y_m_d_His');

        $rollbackContent = "<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     * This rollback was auto-generated for safety.
     * Please review and modify as needed before running.
     */
    public function down(): void
    {
        // TODO: Implement rollback logic for: {$migration['issue_type']}
        // This is a placeholder rollback migration.
        // You may need to manually implement the reverse operations.

        \$this->command->warn('⚠️  Please implement the rollback logic manually for: {$migration['issue_type']}');
    }
};
";

        return $rollbackContent;
    }

    protected function saveRollbackMigration(string $name, string $content): void
    {
        $migrationsPath = database_path('migrations');
        $filename = $name . '.php';
        $filepath = $migrationsPath . '/' . $filename;

        File::put($filepath, $content);
    }

    protected function handleAmendMigrations(): int
    {
        $this->info('Amending existing migration files with proper specifications...');
        $this->warn('⚠️  Migration amendment feature is not yet implemented.');
        $this->info('This feature will allow amending existing migration files to add missing column lengths, indexes, etc.');
        $this->info('For now, use --fix-migrations to generate new alter migrations.');

        return Command::SUCCESS;
    }

    protected function handleRollbackFixes(): int
    {
        $this->info('Rolling back previously applied automatic fixes...');

        $appliedFixes = $this->loadAppliedFixes();

        if (empty($appliedFixes)) {
            $this->warn('⚠️  No applied fixes found to rollback.');
            $this->info('No fixes have been tracked for rollback.');
            return Command::SUCCESS;
        }

        $this->info("Found " . count($appliedFixes) . " applied fixes to potentially rollback.");

        // For now, we'll show what would be rolled back
        // In a full implementation, we'd need to implement rollback logic for each improvement type
        $this->warn('⚠️  Automatic rollback is not yet fully implemented.');
        $this->info('This is a preview of what would be rolled back:');

        foreach ($appliedFixes as $index => $fix) {
            $this->info('');
            $this->info("--- Fix " . ($index + 1) . " ---");
            $this->info("📋 {$fix['title']}");
            $this->line("   Applied: {$fix['applied_at']}");
            if (isset($fix['file'])) {
                $this->line("   📁 {$fix['file']}");
            }
        }

        $this->info('');
        $this->warn('To implement full rollback functionality:');
        $this->info('1. Each CodeImprovement class needs a rollback() method');
        $this->info('2. Backup original file contents before applying fixes');
        $this->info('3. Restore from backups during rollback');

        if ($this->confirm('Clear the applied fixes tracking (recommended after manual rollback)?', false)) {
            $this->clearAppliedFixes();
            $this->info('✅ Applied fixes tracking cleared.');
        }

        return Command::SUCCESS;
    }

    protected function saveAppliedFixes(array $fixes): void
    {
        if (empty($fixes)) {
            return;
        }

        $storagePath = storage_path('app');
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        $filepath = storage_path('app/applied-fixes.json');

        $existingFixes = $this->loadAppliedFixes();
        $allFixes = array_merge($existingFixes, $fixes);

        $jsonContent = json_encode($allFixes, JSON_PRETTY_PRINT);
        if ($jsonContent === false) {
            $this->error('Failed to encode applied fixes to JSON');
            return;
        }

        File::put($filepath, $jsonContent);
    }

    protected function loadAppliedFixes(): array
    {
        $filepath = storage_path('app/applied-fixes.json');

        if (!File::exists($filepath)) {
            return [];
        }

        $content = File::get($filepath);
        $fixes = json_decode($content, true);

        return is_array($fixes) ? $fixes : [];
    }

    protected function clearAppliedFixes(): void
    {
        $filepath = storage_path('app/applied-fixes.json');

        if (File::exists($filepath)) {
            File::delete($filepath);
        }
    }

    /**
     * Check if we're running in a production environment
     * Multiple layers of protection to prevent reverse engineering
     */
    protected function isProductionEnvironment(): bool
    {
        // Check for DDEV environment FIRST (always treat as development)
        if (isset($_SERVER['DDEV_PROJECT']) || isset($_SERVER['DDEV_HOSTNAME']) || 
            getenv('DDEV_PROJECT') || getenv('IS_DDEV_PROJECT') ||
            (isset($_SERVER['IS_DDEV_PROJECT']) && $_SERVER['IS_DDEV_PROJECT'])) {
            return false; // DDEV is always development
        }

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
                !preg_match('/\b(localhost|127\.0\.0\.1|\.local|\.dev|\.test|\.ddev\.site)\b/', $host)) {
                // Additional check: if not clearly development, be conservative
                if (!preg_match('/\b(dev|staging|test|demo|\.ddev)\b/', $host)) {
                    // This is a heuristic - in production deployments, be extra cautious
                    return true;
                }
            }
        }

        return false;
    }
}
