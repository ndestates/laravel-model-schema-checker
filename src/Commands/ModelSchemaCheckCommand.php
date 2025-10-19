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
                            {--check-laravel-forms : Check Blade templates and Livewire forms}
                            {--sync-migrations : Generate fresh migrations from database schema}
                            {--export-data : Export database data to compressed SQL file}
                            {--import-data : Import database data from compressed SQL file}
                            {--cleanup-migrations : Safely remove old migration files with backup}
                            {--check-all : Run all available checks (alias for --all)}';
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

        if ($this->option('check-laravel-forms')) {
            return $this->handleCheckLaravelForms();
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

    protected function handleBackup(): int
    {
        $this->info('Creating database backup...');

        try {
            // Get database configuration
            $dbConfig = config('database');
            $connection = $dbConfig['default'];
            $dbConnection = $dbConfig['connections'][$connection];

            $backupPath = storage_path('backups');
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}.sql";
            $filepath = $backupPath . '/' . $filename;

            $this->info("📁 Backup location: {$filepath}");

            switch ($dbConnection['driver']) {
                case 'mysql':
                case 'mariadb':
                    $this->backupMySQL($dbConnection, $filepath);
                    break;

                case 'pgsql':
                    $this->backupPostgreSQL($dbConnection, $filepath);
                    break;

                case 'sqlite':
                    $this->backupSQLite($dbConnection, $filepath);
                    break;

                default:
                    $this->error("❌ Unsupported database driver: {$dbConnection['driver']}");
                    $this->info('💡 Supported drivers: MySQL, MariaDB, PostgreSQL, SQLite');
                    return Command::FAILURE;
            }

            $this->info("✅ Database backup completed successfully!");
            $this->info("📄 Backup file: {$filepath}");

            // Show file size
            if (file_exists($filepath)) {
                $size = $this->formatBytes(filesize($filepath));
                $this->info("📊 Backup size: {$size}");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Backup failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function backupMySQL(array $config, string $filepath): void
    {
        $this->info("🔄 Creating MySQL backup...");

        // Use Laravel's DB::select to get all table data
        $tables = DB::select('SHOW TABLES');
        $tableKey = 'Tables_in_' . $config['database'];

        $sql = "-- Laravel Model Schema Checker Backup\n";
        $sql .= "-- Generated: " . now() . "\n";
        $sql .= "-- Database: {$config['database']}\n\n";

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;

            // Get CREATE TABLE statement
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $sql .= $createTable[0]->{'Create Table'} . ";\n\n";

            // Get table data
            $rows = DB::table($tableName)->get();
            if ($rows->count() > 0) {
                $sql .= "-- Data for table `{$tableName}`\n";
                foreach ($rows as $row) {
                    $columns = array_keys((array) $row);
                    $values = array_map(function($value) {
                        return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                    }, (array) $row);

                    $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        file_put_contents($filepath, $sql);
    }

    protected function backupPostgreSQL(array $config, string $filepath): void
    {
        $this->info("🔄 Creating PostgreSQL backup...");

        // For PostgreSQL, we'll create a simpler backup using pg_dump if available
        // Otherwise, fall back to basic table export
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        $sql = "-- Laravel Model Schema Checker Backup\n";
        $sql .= "-- Generated: " . now() . "\n";
        $sql .= "-- Database: {$config['database']}\n\n";

        foreach ($tables as $table) {
            $tableName = $table->tablename;

            // Get table structure (simplified)
            $columns = DB::select("SELECT column_name, data_type, is_nullable, column_default
                                  FROM information_schema.columns
                                  WHERE table_name = ? AND table_schema = 'public'
                                  ORDER BY ordinal_position", [$tableName]);

            if (!empty($columns)) {
                $sql .= "-- Table structure for {$tableName}\n";
                // Simplified CREATE TABLE for PostgreSQL
                $columnDefs = [];
                foreach ($columns as $column) {
                    $def = "\"{$column->column_name}\" {$column->data_type}";
                    if ($column->is_nullable === 'NO') {
                        $def .= ' NOT NULL';
                    }
                    if ($column->column_default) {
                        $def .= ' DEFAULT ' . $column->column_default;
                    }
                    $columnDefs[] = $def;
                }
                $sql .= "CREATE TABLE \"{$tableName}\" (\n  " . implode(",\n  ", $columnDefs) . "\n);\n\n";

                // Get table data
                $rows = DB::table($tableName)->get();
                if ($rows->count() > 0) {
                    $sql .= "-- Data for table {$tableName}\n";
                    foreach ($rows as $row) {
                        $columns = array_keys((array) $row);
                        $values = array_map(function($value) {
                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }, (array) $row);

                        $sql .= "INSERT INTO \"{$tableName}\" (\"" . implode('", "', $columns) . "\") VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }
        }

        file_put_contents($filepath, $sql);
    }

    protected function backupSQLite(array $config, string $filepath): void
    {
        $databasePath = $config['database'];

        if (!file_exists($databasePath)) {
            throw new \Exception("SQLite database file not found: {$databasePath}");
        }

        $this->info("🔄 Creating SQLite backup...");

        // For SQLite, just copy the file
        copy($databasePath, $filepath);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
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

        return $this->issueManager->hasIssues() ? Command::FAILURE : Command::SUCCESS;
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

    protected function displayCodeImprovements(array $issues): void
    {
        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));

        if (empty($improvements)) {
            return;
        }

        $this->info('');
        $this->info('💡 Code Improvement Suggestions (' . count($improvements) . '):');

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

    protected function applyCodeImprovements(array $improvements): void
    {
        $applied = 0;

        foreach ($improvements as $issue) {
            $improvement = $issue['improvement'];

            if ($improvement->canAutoFix() && $improvement->applyFix()) {
                $this->info("✅ Applied: {$improvement->getTitle()}");
                $applied++;
            }
        }

        if ($applied > 0) {
            $this->info('');
            $this->info("🎉 Successfully applied {$applied} automatic fixes!");
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

        $this->line(json_encode($result, JSON_PRETTY_PRINT));
    }
}
