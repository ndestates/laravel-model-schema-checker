<?php<?php<?php<?php<?php



namespace NDEstates\LaravelModelSchemaChecker\Commands;



use Illuminate\Console\Command;namespace NDEstates\LaravelModelSchemaChecker\Commands;

use Illuminate\Support\Facades\DB;

use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;

use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;

use Illuminate\Console\Command;namespace NDEstates\LaravelModelSchemaChecker\Commands;

class ModelSchemaCheckCommand extends Command

{use Illuminate\Support\Facades\DB;

    protected $signature = 'model:schema-check

                            {--dry-run : Show what would be changed without making changes}use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;

                            {--fix : Fix model fillable properties}

                            {--json : Output results in JSON format}use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;

                            {--all : Run all available checks}

                            {--generate-migrations : Generate Laravel migrations}use Illuminate\Console\Command;namespace NDEstates\LaravelModelSchemaChecker\Commands;namespace NDEstates\LaravelModelSchemaChecker\Commands;

                            {--run-migrations : Run generated migrations}

                            {--backup-db : Create database backup before making changes}class ModelSchemaCheckCommand extends Command

                            {--backup : Create database backup}

                            {--analyze : Run comprehensive analysis}{use Illuminate\Support\Facades\DB;

                            {--generate-schema : Generate schema documentation}

                            {--generate-schema-sql : Generate schema SQL}    protected $signature = 'model:schema-check

                            {--check-filament : Check Filament relationships}

                            {--check-all : Run all available checks (alias for --all)}';                            {--dry-run : Show what would be changed without making changes}use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;



    protected $description = 'Laravel Model Schema Checker v3.0 - Modular Architecture';                            {--fix : Fix model fillable properties}



    protected CheckerManager $checkerManager;                            {--json : Output results in JSON format}use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;

    protected IssueManager $issueManager;

                            {--all : Run all available checks}

    public function __construct(CheckerManager $checkerManager, IssueManager $issueManager)

    {                            {--generate-migrations : Generate Laravel migrations}use Illuminate\Console\Command;use Illuminate\Console\Command;

        parent::__construct();

        $this->checkerManager = $checkerManager;                            {--run-migrations : Run generated migrations}

        $this->issueManager = $issueManager;

    }                            {--backup-db : Create database backup before making changes}class ModelSchemaCheckCommand extends Command



    public function handle(): int                            {--backup : Create database backup}

    {

        $this->info('Laravel Model Schema Checker v3.0');                            {--analyze : Run comprehensive analysis}{use Illuminate\Support\Facades\Process;use Illuminate\Support\Facades\Process;

        $this->info('=====================================');

                            {--generate-schema : Generate schema documentation}

        if ($this->option('dry-run')) {

            $this->warn('Running in DRY-RUN mode - no changes will be made');                            {--generate-schema-sql : Generate schema SQL}    protected $signature = 'model:schema-check

        }

                            {--check-filament : Check Filament relationships}

        // Route to appropriate functionality based on options

        if ($this->option('backup') || $this->option('backup-db')) {                            {--check-all : Run all available checks (alias for --all)}';                            {--dry-run : Show what would be changed without making changes}use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;

            return $this->handleBackup();

        }



        if ($this->option('generate-migrations')) {    protected $description = 'Laravel Model Schema Checker v3.0 - Modular Architecture';                            {--fix : Fix model fillable properties}

            return $this->handleGenerateMigrations();

        }



        if ($this->option('run-migrations')) {    protected CheckerManager $checkerManager;                            {--json : Output results in JSON format}use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;

            return $this->handleRunMigrations();

        }    protected IssueManager $issueManager;



        if ($this->option('analyze')) {                            {--all : Run all available checks}

            return $this->handleAnalyze();

        }    public function __construct(CheckerManager $checkerManager, IssueManager $issueManager)



        if ($this->option('generate-schema')) {    {                            {--generate-migrations : Generate Laravel migrations}    {

            return $this->handleGenerateSchema();

        }        parent::__construct();



        if ($this->option('generate-schema-sql')) {        $this->checkerManager = $checkerManager;                            {--run-migrations : Run generated migrations}

            return $this->handleGenerateSchemaSql();

        }        $this->issueManager = $issueManager;



        if ($this->option('check-filament')) {    }                            {--backup-db : Create database backup before making changes}class ModelSchemaCheckCommand extends Command        $this->info("🔄 Running backup command...");

            return $this->handleCheckFilament();

        }



        // Default: run model checks    public function handle(): int                            {--backup : Create database backup}

        return $this->handleModelChecks();

    }    {



    protected function handleModelChecks(): int        $this->info('Laravel Model Schema Checker v3.0');                            {--analyze : Run comprehensive analysis}{

    {

        // Set command on checker manager for output        $this->info('=====================================');

        $this->checkerManager->setCommand($this);

                            {--generate-schema : Generate schema documentation}

        // Run the checks

        $issues = $this->checkerManager->runAllChecks();        if ($this->option('dry-run')) {



        // Display results            $this->warn('Running in DRY-RUN mode - no changes will be made');                            {--generate-schema-sql : Generate schema SQL}    protected $signature = 'model:schema-check        // Use Laravel's process helper for better error handling

        $this->displayResults();

        }

        // Always return success - don't fail on issues

        return Command::SUCCESS;                            {--check-filament : Check Filament relationships}

    }

        // Route to appropriate functionality based on options

    protected function handleBackup(): int

    {        if ($this->option('backup') || $this->option('backup-db')) {                            {--check-all : Run all available checks (alias for --all)}';                            {--dry-run : Show what would be changed without making changes}        $process = Process::run($command);

        $this->info('Creating database backup...');

            return $this->handleBackup();

        try {

            // Get database configuration        }

            $dbConfig = config('database');

            $connection = $dbConfig['default'];

            $dbConnection = $dbConfig['connections'][$connection];

        if ($this->option('generate-migrations')) {    protected $description = 'Laravel Model Schema Checker v3.0 - Modular Architecture';                            {--fix : Fix model fillable properties}

            $backupPath = storage_path('backups');

            if (!file_exists($backupPath)) {            return $this->handleGenerateMigrations();

                mkdir($backupPath, 0755, true);

            }        }



            $timestamp = now()->format('Y-m-d_H-i-s');

            $filename = "backup_{$timestamp}.sql";

            $filepath = $backupPath . '/' . $filename;        if ($this->option('run-migrations')) {    protected CheckerManager $checkerManager;                            {--json : Output results in JSON format}        if (!$process->successful()) {



            $this->info("📁 Backup location: {$filepath}");            return $this->handleRunMigrations();



            switch ($dbConnection['driver']) {        }    protected IssueManager $issueManager;

                case 'mysql':

                case 'mariadb':

                    $this->backupMySQL($dbConnection, $filepath);

                    break;        if ($this->option('analyze')) {                            {--all : Run all available checks}            $this->error("Command output: " . $process->output());



                case 'pgsql':            return $this->handleAnalyze();

                    $this->backupPostgreSQL($dbConnection, $filepath);

                    break;        }    public function __construct(CheckerManager $checkerManager, IssueManager $issueManager)



                case 'sqlite':

                    $this->backupSQLite($dbConnection, $filepath);

                    break;        if ($this->option('generate-schema')) {    {                            {--generate-migrations : Generate Laravel migrations}            $this->error("Command error: " . $process->errorOutput());



                default:            return $this->handleGenerateSchema();

                    $this->error("❌ Unsupported database driver: {$dbConnection['driver']}");

                    $this->info('💡 Supported drivers: MySQL, MariaDB, PostgreSQL, SQLite');        }        parent::__construct();

                    return Command::FAILURE;

            }



            $this->info("✅ Database backup completed successfully!");        if ($this->option('generate-schema-sql')) {        $this->checkerManager = $checkerManager;                            {--run-migrations : Run generated migrations}        }

            $this->info("📄 Backup file: {$filepath}");

            return $this->handleGenerateSchemaSql();

            // Show file size

            if (file_exists($filepath)) {        }        $this->issueManager = $issueManager;

                $size = $this->formatBytes(filesize($filepath));

                $this->info("📊 Backup size: {$size}");

            }

        if ($this->option('check-filament')) {    }                            {--backup-db : Create database backup before making changes}

            return Command::SUCCESS;

            return $this->handleCheckFilament();

        } catch (\Exception $e) {

            $this->error("❌ Backup failed: " . $e->getMessage());        }

            return Command::FAILURE;

        }

    }

        // Default: run model checks    public function handle(): int                            {--backup : Create database backup}        return $process->exitCode();

    protected function backupMySQL(array $config, string $filepath): void

    {        return $this->handleModelChecks();

        $this->info("🔄 Creating MySQL backup...");

    }    {

        // Use Laravel's DB::select to get all table data

        $tables = DB::select('SHOW TABLES');

        $tableKey = 'Tables_in_' . $config['database'];

    protected function handleModelChecks(): int        $this->info('Laravel Model Schema Checker v3.0');                            {--analyze : Run comprehensive analysis}    }ces\IssueManager;

        $sql = "-- Laravel Model Schema Checker Backup\n";

        $sql .= "-- Generated: " . now() . "\n";    {

        $sql .= "-- Database: {$config['database']}\n\n";

        // Set command on checker manager for output        $this->info('=====================================');

        foreach ($tables as $table) {

            $tableName = $table->$tableKey;        $this->checkerManager->setCommand($this);



            // Get CREATE TABLE statement                            {--generate-schema : Generate schema documentation}

            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");

            $sql .= $createTable[0]->{'Create Table'} . ";\n\n";        // Run the checks



            // Get table data        $issues = $this->checkerManager->runAllChecks();        if ($this->option('dry-run')) {

            $rows = DB::table($tableName)->get();

            if ($rows->count() > 0) {

                $sql .= "-- Data for table `{$tableName}`\n";

                foreach ($rows as $row) {        // Display results            $this->warn('Running in DRY-RUN mode - no changes will be made');                            {--generate-schema-sql : Generate schema SQL}class ModelSchemaCheckCommand extends Command

                    $columns = array_keys((array) $row);

                    $values = array_map(function($value) {        $this->displayResults();

                        return $value === null ? 'NULL' : "'" . addslashes($value) . "'";

                    }, (array) $row);        }



                    $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";        // Always return success - don't fail on issues

                }

                $sql .= "\n";        return Command::SUCCESS;                            {--check-filament : Check Filament relationships}{

            }

        }    }



        file_put_contents($filepath, $sql);        // Route to appropriate functionality based on options

    }

    protected function handleBackup(): int

    protected function backupPostgreSQL(array $config, string $filepath): void

    {    {        if ($this->option('backup') || $this->option('backup-db')) {                            {--check-all : Run all available checks (alias for --all)}';    protected $signature = 'model:schema-check

        $this->info("🔄 Creating PostgreSQL backup...");

        $this->info('Creating database backup...');

        // For PostgreSQL, we'll create a simpler backup using pg_dump if available

        // Otherwise, fall back to basic table export            return $this->handleBackup();

        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        try {

        $sql = "-- Laravel Model Schema Checker Backup\n";

        $sql .= "-- Generated: " . now() . "\n";            // Get database configuration        }                            {--dry-run : Show what would be changed without making changes}

        $sql .= "-- Database: {$config['database']}\n\n";

            $dbConfig = config('database');

        foreach ($tables as $table) {

            $tableName = $table->tablename;            $connection = $dbConfig['default'];



            // Get table structure (simplified)            $dbConnection = $dbConfig['connections'][$connection];

            $columns = DB::select("SELECT column_name, data_type, is_nullable, column_default

                                  FROM information_schema.columns        if ($this->option('generate-migrations')) {    protected $description = 'Laravel Model Schema Checker v3.0 - Modular Architecture';                            {--fix : Fix model fillable properties}

                                  WHERE table_name = ? AND table_schema = 'public'

                                  ORDER BY ordinal_position", [$tableName]);            $backupPath = storage_path('backups');



            if (!empty($columns)) {            if (!file_exists($backupPath)) {            return $this->handleGenerateMigrations();

                $sql .= "-- Table structure for {$tableName}\n";

                // Simplified CREATE TABLE for PostgreSQL                mkdir($backupPath, 0755, true);

                $columnDefs = [];

                foreach ($columns as $column) {            }        }                            {--json : Output results in JSON format}

                    $def = "\"{$column->column_name}\" {$column->data_type}";

                    if ($column->is_nullable === 'NO') {

                        $def .= ' NOT NULL';

                    }            $timestamp = now()->format('Y-m-d_H-i-s');

                    if ($column->column_default) {

                        $def .= ' DEFAULT ' . $column->column_default;            $filename = "backup_{$timestamp}.sql";

                    }

                    $columnDefs[] = $def;            $filepath = $backupPath . '/' . $filename;        if ($this->option('run-migrations')) {    protected CheckerManager $checkerManager;                            {--all : Run all available checks}

                }

                $sql .= "CREATE TABLE \"{$tableName}\" (\n  " . implode(",\n  ", $columnDefs) . "\n);\n\n";



                // Get table data            $this->info("📁 Backup location: {$filepath}");            return $this->handleRunMigrations();

                $rows = DB::table($tableName)->get();

                if ($rows->count() > 0) {

                    $sql .= "-- Data for table {$tableName}\n";

                    foreach ($rows as $row) {            switch ($dbConnection['driver']) {        }    protected IssueManager $issueManager;                            {--generate-migrations : Generate Laravel migrations}

                        $columns = array_keys((array) $row);

                        $values = array_map(function($value) {                case 'mysql':

                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";

                        }, (array) $row);                case 'mariadb':



                        $sql .= "INSERT INTO \"{$tableName}\" (\"" . implode('", "', $columns) . "\") VALUES (" . implode(', ', $values) . ");\n";                    $this->backupMySQL($dbConnection, $filepath);

                    }

                    $sql .= "\n";                    break;        if ($this->option('analyze')) {                            {--run-migrations : Run generated migrations}

                }

            }

        }

                case 'pgsql':            return $this->handleAnalyze();

        file_put_contents($filepath, $sql);

    }                    $this->backupPostgreSQL($dbConnection, $filepath);



    protected function backupSQLite(array $config, string $filepath): void                    break;        }    public function __construct(CheckerManager $checkerManager, IssueManager $issueManager)                            {--backup-db : Create database backup before making changes}

    {

        $databasePath = $config['database'];



        if (!file_exists($databasePath)) {                case 'sqlite':

            throw new \Exception("SQLite database file not found: {$databasePath}");

        }                    $this->backupSQLite($dbConnection, $filepath);



        $this->info("🔄 Creating SQLite backup...");                    break;        if ($this->option('generate-schema')) {    {                            {--backup : Show backup recommendations}



        // For SQLite, just copy the file

        copy($databasePath, $filepath);

    }                default:            return $this->handleGenerateSchema();



    protected function formatBytes(int $bytes): string                    $this->error("❌ Unsupported database driver: {$dbConnection['driver']}");

    {

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];                    $this->info('💡 Supported drivers: MySQL, MariaDB, PostgreSQL, SQLite');        }        parent::__construct();                            {--analyze : Run comprehensive analysis}

        $i = 0;

                    return Command::FAILURE;

        while ($bytes >= 1024 && $i < count($units) - 1) {

            $bytes /= 1024;            }

            $i++;

        }



        return round($bytes, 2) . ' ' . $units[$i];            $this->info("✅ Database backup completed successfully!");        if ($this->option('generate-schema-sql')) {        $this->checkerManager = $checkerManager;                            {--generate-schema : Generate schema documentation}

    }

            $this->info("📄 Backup file: {$filepath}");

    protected function handleGenerateMigrations(): int

    {            return $this->handleGenerateSchemaSql();

        $this->info('Generating migrations...');

        $this->warn('⚠️  Migration generation not yet implemented in v3.0');            // Show file size

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;            if (file_exists($filepath)) {        }        $this->issueManager = $issueManager;                            {--generate-schema-sql : Generate schema SQL}

    }

                $size = $this->formatBytes(filesize($filepath));

    protected function handleRunMigrations(): int

    {                $this->info("📊 Backup size: {$size}");

        $this->info('Running migrations...');

        $this->warn('⚠️  Migration running not yet implemented in v3.0');            }

        $this->info('Please run migrations manually: php artisan migrate');

        return Command::SUCCESS;        if ($this->option('check-filament')) {    }                            {--check-filament : Check Filament relationships}

    }

            return Command::SUCCESS;

    protected function handleAnalyze(): int

    {            return $this->handleCheckFilament();

        $this->info('Running comprehensive analysis...');

        $this->warn('⚠️  Analysis functionality not yet implemented in v3.0');        } catch (\Exception $e) {

        $this->info('Running basic model checks instead...');

        return $this->handleModelChecks();            $this->error("❌ Backup failed: " . $e->getMessage());        }                            {--check-all : Run all available checks (alias for --all)}';

    }

            return Command::FAILURE;

    protected function handleGenerateSchema(): int

    {        }

        $this->info('Generating schema documentation...');

        $this->warn('⚠️  Schema generation not yet implemented in v3.0');    }

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;        // Default: run model checks    public function handle(): int

    }

    protected function backupMySQL(array $config, string $filepath): void

    protected function handleGenerateSchemaSql(): int

    {    {        return $this->handleModelChecks();

        $this->info('Generating schema SQL...');

        $this->warn('⚠️  Schema SQL generation not yet implemented in v3.0');        $this->info("🔄 Creating MySQL backup...");

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;    }    {    protected $description = 'Laravel Model Schema Checker v3.0 - Modular Architecture';

    }

        // Use Laravel's DB::select to get all table data

    protected function handleCheckFilament(): int

    {        $tables = DB::select('SHOW TABLES');

        $this->info('Checking Filament relationships...');

        $this->warn('⚠️  Filament checking not yet implemented in v3.0');        $tableKey = 'Tables_in_' . $config['database'];

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;    protected function handleModelChecks(): int        $this->info('Laravel Model Schema Checker v3.0');

    }

        $sql = "-- Laravel Model Schema Checker Backup\n";

    protected function displayResults(): void

    {        $sql .= "-- Generated: " . now() . "\n";    {

        $stats = $this->issueManager->getStats();

        $sql .= "-- Database: {$config['database']}\n\n";

        if ($this->option('json')) {

            $this->outputJson();        // Set command on checker manager for output        $this->info('=====================================');    protected CheckerManager $checkerManager;

            return;

        }        foreach ($tables as $table) {



        $this->info('');            $tableName = $table->$tableKey;        $this->checkerManager->setCommand($this);

        $this->info('Results Summary');

        $this->info('===============');



        if ($stats['total_issues'] === 0) {            // Get CREATE TABLE statement    protected IssueManager $issueManager;

            $this->info('✅ No issues found!');

            return;            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");

        }

            $sql .= $createTable[0]->{'Create Table'} . ";\n\n";        // Run the checks

        $this->warn("⚠️  Found {$stats['total_issues']} issue(s)");



        // Display issues

        $issues = $this->issueManager->getIssues();            // Get table data        $issues = $this->checkerManager->runAllChecks();        if ($this->option('dry-run')) {

        foreach ($issues as $issue) {

            $this->line("  • " . ($issue['message'] ?? $issue['type']));            $rows = DB::table($tableName)->get();

            if (isset($issue['file'])) {

                $this->line("    📁 " . $issue['file']);            if ($rows->count() > 0) {

            }

        }                $sql .= "-- Data for table `{$tableName}`\n";



        // Display code improvements                foreach ($rows as $row) {        // Display results            $this->warn('Running in DRY-RUN mode - no changes will be made');    public function __construct(CheckerManager $checkerManager, IssueManager $issueManager)

        $this->displayCodeImprovements($issues);

    }                    $columns = array_keys((array) $row);



    protected function displayCodeImprovements(array $issues): void                    $values = array_map(function($value) {        $this->displayResults();

    {

        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));                        return $value === null ? 'NULL' : "'" . addslashes($value) . "'";



        if (empty($improvements)) {                    }, (array) $row);        }    {

            return;

        }



        $this->info('');                    $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";        // Always return success - don't fail on issues

        $this->info('💡 Code Improvement Suggestions (' . count($improvements) . '):');

                }

        foreach ($improvements as $issue) {

            $improvement = $issue['improvement'];                $sql .= "\n";        return Command::SUCCESS;        parent::__construct();

            $this->info("  • {$improvement->getTitle()}");

            $this->line("    {$improvement->getDescription()}");            }



            if ($improvement->canAutoFix()) {        }    }

                $this->info("    ✅ Can be automatically fixed");

            }

        }

        file_put_contents($filepath, $sql);        // Route to appropriate functionality based on options        $this->checkerManager = $checkerManager;

        if (!$this->option('dry-run') && $this->confirm('Apply automatic fixes?', false)) {

            $this->applyCodeImprovements($improvements);    }

        }

    }    protected function handleBackup(): int



    protected function applyCodeImprovements(array $improvements): void    protected function backupPostgreSQL(array $config, string $filepath): void

    {

        $applied = 0;    {    {        if ($this->option('backup') || $this->option('backup-db')) {        $this->issueManager = $issueManager;



        foreach ($improvements as $issue) {        $this->info("🔄 Creating PostgreSQL backup...");

            $improvement = $issue['improvement'];

        $this->info('Creating database backup...');

            if ($improvement->canAutoFix() && $improvement->applyFix()) {

                $this->info("✅ Applied: {$improvement->getTitle()}");        // For PostgreSQL, we'll create a simpler backup using pg_dump if available

                $applied++;

            }        // Otherwise, fall back to basic table export            return $this->handleBackup();    }

        }

        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        if ($applied > 0) {

            $this->info('');        try {

            $this->info("🎉 Successfully applied {$applied} automatic fixes!");

        }        $sql = "-- Laravel Model Schema Checker Backup\n";

    }

        $sql .= "-- Generated: " . now() . "\n";            // Get database configuration        }

    protected function outputJson(): void

    {        $sql .= "-- Database: {$config['database']}\n\n";

        $result = [

            'timestamp' => now()->toISOString(),            $dbConfig = config('database');

            'stats' => $this->issueManager->getStats(),

            'issues' => $this->issueManager->getIssues(),        foreach ($tables as $table) {

            'checkers' => $this->checkerManager->getAvailableCheckers(),

        ];            $tableName = $table->tablename;            $connection = $dbConfig['default'];    public function handle(): int



        $this->line(json_encode($result, JSON_PRETTY_PRINT));

    }

}            // Get table structure (simplified)            $dbConnection = $dbConfig['connections'][$connection];

            $columns = DB::select("SELECT column_name, data_type, is_nullable, column_default

                                  FROM information_schema.columns        if ($this->option('generate-migrations')) {    {

                                  WHERE table_name = ? AND table_schema = 'public'

                                  ORDER BY ordinal_position", [$tableName]);            $backupPath = storage_path('backups');



            if (!empty($columns)) {            if (!file_exists($backupPath)) {            return $this->handleGenerateMigrations();        $this->info('Laravel Model Schema Checker v3.0');

                $sql .= "-- Table structure for {$tableName}\n";

                // Simplified CREATE TABLE for PostgreSQL                mkdir($backupPath, 0755, true);

                $columnDefs = [];

                foreach ($columns as $column) {            }        }        $this->info('=====================================');

                    $def = "\"{$column->column_name}\" {$column->data_type}";

                    if ($column->is_nullable === 'NO') {

                        $def .= ' NOT NULL';

                    }            $timestamp = now()->format('Y-m-d_H-i-s');

                    if ($column->column_default) {

                        $def .= ' DEFAULT ' . $column->column_default;            $filename = "backup_{$timestamp}.sql";

                    }

                    $columnDefs[] = $def;            $filepath = $backupPath . '/' . $filename;        if ($this->option('run-migrations')) {        if ($this->option('dry-run')) {

                }

                $sql .= "CREATE TABLE \"{$tableName}\" (\n  " . implode(",\n  ", $columnDefs) . "\n);\n\n";



                // Get table data            $this->info("📁 Backup location: {$filepath}");            return $this->handleRunMigrations();            $this->warn('Running in DRY-RUN mode - no changes will be made');

                $rows = DB::table($tableName)->get();

                if ($rows->count() > 0) {

                    $sql .= "-- Data for table {$tableName}\n";

                    foreach ($rows as $row) {            switch ($dbConnection['driver']) {        }        }

                        $columns = array_keys((array) $row);

                        $values = array_map(function($value) {                case 'mysql':

                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";

                        }, (array) $row);                case 'mariadb':



                        $sql .= "INSERT INTO \"{$tableName}\" (\"" . implode('", "', $columns) . "\") VALUES (" . implode(', ', $values) . ");\n";                    $this->backupMySQL($dbConnection, $filepath);

                    }

                    $sql .= "\n";                    break;        if ($this->option('analyze')) {        // Route to appropriate functionality based on options

                }

            }

        }

                case 'pgsql':            return $this->handleAnalyze();        if ($this->option('backup') || $this->option('backup-db')) {

        file_put_contents($filepath, $sql);

    }                    $this->backupPostgreSQL($dbConnection, $filepath);



    protected function backupSQLite(array $config, string $filepath): void                    break;        }            return $this->handleBackup();

    {

        $databasePath = $config['database'];



        if (!file_exists($databasePath)) {                case 'sqlite':        }

            throw new \Exception("SQLite database file not found: {$databasePath}");

        }                    $this->backupSQLite($dbConnection, $filepath);



        $this->info("🔄 Creating SQLite backup...");                    break;        if ($this->option('generate-schema')) {



        // For SQLite, just copy the file

        copy($databasePath, $filepath);

    }                default:            return $this->handleGenerateSchema();        if ($this->option('generate-migrations')) {



    protected function formatBytes(int $bytes): string                    $this->error("❌ Unsupported database driver: {$dbConnection['driver']}");

    {

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];                    $this->info('💡 Supported drivers: MySQL, MariaDB, PostgreSQL, SQLite');        }            return $this->handleGenerateMigrations();

        $i = 0;

                    return Command::FAILURE;

        while ($bytes >= 1024 && $i < count($units) - 1) {

            $bytes /= 1024;            }        }

            $i++;

        }



        return round($bytes, 2) . ' ' . $units[$i];            $this->info("✅ Database backup completed successfully!");        if ($this->option('generate-schema-sql')) {

    }

            $this->info("📄 Backup file: {$filepath}");

    protected function handleGenerateMigrations(): int

    {            return $this->handleGenerateSchemaSql();        if ($this->option('run-migrations')) {

        $this->info('Generating migrations...');

        $this->warn('⚠️  Migration generation not yet implemented in v3.0');            // Show file size

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;            if (file_exists($filepath)) {        }            return $this->handleRunMigrations();

    }

                $size = $this->formatBytes(filesize($filepath));

    protected function handleRunMigrations(): int

    {                $this->info("📊 Backup size: {$size}");        }

        $this->info('Running migrations...');

        $this->warn('⚠️  Migration running not yet implemented in v3.0');            }

        $this->info('Please run migrations manually: php artisan migrate');

        return Command::SUCCESS;        if ($this->option('check-filament')) {

    }

            return Command::SUCCESS;

    protected function handleAnalyze(): int

    {            return $this->handleCheckFilament();        if ($this->option('analyze')) {

        $this->info('Running comprehensive analysis...');

        $this->warn('⚠️  Analysis functionality not yet implemented in v3.0');        } catch (\Exception $e) {

        $this->info('Running basic model checks instead...');

        return $this->handleModelChecks();            $this->error("❌ Backup failed: " . $e->getMessage());        }            return $this->handleAnalyze();

    }

            return Command::FAILURE;

    protected function handleGenerateSchema(): int

    {        }        }

        $this->info('Generating schema documentation...');

        $this->warn('⚠️  Schema generation not yet implemented in v3.0');    }

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;        // Default: run model checks

    }

    protected function backupMySQL(array $config, string $filepath): void

    protected function handleGenerateSchemaSql(): int

    {    {        return $this->handleModelChecks();        if ($this->option('generate-schema')) {

        $this->info('Generating schema SQL...');

        $this->warn('⚠️  Schema SQL generation not yet implemented in v3.0');        $this->info("🔄 Creating MySQL backup...");

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;    }            return $this->handleGenerateSchema();

    }

        // Use Laravel's DB::select to get all table data

    protected function handleCheckFilament(): int

    {        $tables = DB::select('SHOW TABLES');        }

        $this->info('Checking Filament relationships...');

        $this->warn('⚠️  Filament checking not yet implemented in v3.0');        $tableKey = 'Tables_in_' . $config['database'];

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;    protected function handleModelChecks(): int

    }

        $sql = "-- Laravel Model Schema Checker Backup\n";

    protected function displayResults(): void

    {        $sql .= "-- Generated: " . now() . "\n";    {        if ($this->option('generate-schema-sql')) {

        $stats = $this->issueManager->getStats();

        $sql .= "-- Database: {$config['database']}\n\n";

        if ($this->option('json')) {

            $this->outputJson();        // Set command on checker manager for output            return $this->handleGenerateSchemaSql();

            return;

        }        foreach ($tables as $table) {



        $this->info('');            $tableName = $table->$tableKey;        $this->checkerManager->setCommand($this);        }

        $this->info('Results Summary');

        $this->info('===============');



        if ($stats['total_issues'] === 0) {            // Get CREATE TABLE statement

            $this->info('✅ No issues found!');

            return;            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");

        }

            $sql .= $createTable[0]->{'Create Table'} . ";\n\n";        // Run the checks        if ($this->option('check-filament')) {

        $this->warn("⚠️  Found {$stats['total_issues']} issue(s)");



        // Display issues

        $issues = $this->issueManager->getIssues();            // Get table data        $issues = $this->checkerManager->runAllChecks();            return $this->handleCheckFilament();

        foreach ($issues as $issue) {

            $this->line("  • " . ($issue['message'] ?? $issue['type']));            $rows = DB::table($tableName)->get();

            if (isset($issue['file'])) {

                $this->line("    📁 " . $issue['file']);            if ($rows->count() > 0) {        }

            }

        }                $sql .= "-- Data for table `{$tableName}`\n";



        // Display code improvements                foreach ($rows as $row) {        // Display results

        $this->displayCodeImprovements($issues);

    }                    $columns = array_keys((array) $row);



    protected function displayCodeImprovements(array $issues): void                    $values = array_map(function($value) {        $this->displayResults();        // Default: run model checks

    {

        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));                        return $value === null ? 'NULL' : "'" . addslashes($value) . "'";



        if (empty($improvements)) {                    }, (array) $row);        return $this->handleModelChecks();

            return;

        }



        $this->info('');                    $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";        // Always return success - don't fail on issues    }

        $this->info('💡 Code Improvement Suggestions (' . count($improvements) . '):');

                }

        foreach ($improvements as $issue) {

            $improvement = $issue['improvement'];                $sql .= "\n";        return Command::SUCCESS;

            $this->info("  • {$improvement->getTitle()}");

            $this->line("    {$improvement->getDescription()}");            }



            if ($improvement->canAutoFix()) {        }    }    protected function handleModelChecks(): int

                $this->info("    ✅ Can be automatically fixed");

            }

        }

        file_put_contents($filepath, $sql);    {

        if (!$this->option('dry-run') && $this->confirm('Apply automatic fixes?', false)) {

            $this->applyCodeImprovements($improvements);    }

        }

    }    protected function handleBackup(): int        // Set command on checker manager for output



    protected function applyCodeImprovements(array $improvements): void    protected function backupPostgreSQL(array $config, string $filepath): void

    {

        $applied = 0;    {    {        $this->checkerManager->setCommand($this);



        foreach ($improvements as $issue) {        $this->info("🔄 Creating PostgreSQL backup...");

            $improvement = $issue['improvement'];

        $this->info('Creating database backup...');

            if ($improvement->canAutoFix() && $improvement->applyFix()) {

                $this->info("✅ Applied: {$improvement->getTitle()}");        // For PostgreSQL, we'll create a simpler backup using pg_dump if available

                $applied++;

            }        // Otherwise, fall back to basic table export        // Run the checks

        }

        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        if ($applied > 0) {

            $this->info('');        try {        $issues = $this->checkerManager->runAllChecks();

            $this->info("🎉 Successfully applied {$applied} automatic fixes!");

        }        $sql = "-- Laravel Model Schema Checker Backup\n";

    }

        $sql .= "-- Generated: " . now() . "\n";            // Get database configuration

    protected function outputJson(): void

    {        $sql .= "-- Database: {$config['database']}\n\n";

        $result = [

            'timestamp' => now()->toISOString(),            $dbConfig = config('database');        // Display results

            'stats' => $this->issueManager->getStats(),

            'issues' => $this->issueManager->getIssues(),        foreach ($tables as $table) {

            'checkers' => $this->checkerManager->getAvailableCheckers(),

        ];            $tableName = $table->tablename;            $connection = $dbConfig['default'];        $this->displayResults();



        $this->line(json_encode($result, JSON_PRETTY_PRINT));

    }

}            // Get table structure (simplified)            $dbConnection = $dbConfig['connections'][$connection];

            $columns = DB::select("SELECT column_name, data_type, is_nullable, column_default

                                  FROM information_schema.columns        // Always return success - don't fail on issues

                                  WHERE table_name = ? AND table_schema = 'public'

                                  ORDER BY ordinal_position", [$tableName]);            $backupPath = storage_path('backups');        return Command::SUCCESS;



            if (!empty($columns)) {            if (!file_exists($backupPath)) {    }

                $sql .= "-- Table structure for {$tableName}\n";

                // Simplified CREATE TABLE for PostgreSQL                mkdir($backupPath, 0755, true);

                $columnDefs = [];

                foreach ($columns as $column) {            }    protected function handleBackup(): int

                    $def = "\"{$column->column_name}\" {$column->data_type}";

                    if ($column->is_nullable === 'NO') {    {

                        $def .= ' NOT NULL';

                    }            $timestamp = now()->format('Y-m-d_H-i-s');        $this->info('Creating database backup...');

                    if ($column->column_default) {

                        $def .= ' DEFAULT ' . $column->column_default;            $filename = "backup_{$timestamp}.sql";

                    }

                    $columnDefs[] = $def;            $filepath = $backupPath . '/' . $filename;        try {

                }

                $sql .= "CREATE TABLE \"{$tableName}\" (\n  " . implode(",\n  ", $columnDefs) . "\n);\n\n";            // Get database configuration



                // Get table data            $this->info("📁 Backup location: {$filepath}");            $dbConfig = config('database');

                $rows = DB::table($tableName)->get();

                if ($rows->count() > 0) {            $connection = $dbConfig['default'];

                    $sql .= "-- Data for table {$tableName}\n";

                    foreach ($rows as $row) {            switch ($dbConnection['driver']) {            $dbConnection = $dbConfig['connections'][$connection];

                        $columns = array_keys((array) $row);

                        $values = array_map(function($value) {                case 'mysql':

                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";

                        }, (array) $row);                case 'mariadb':            $backupPath = storage_path('backups');



                        $sql .= "INSERT INTO \"{$tableName}\" (\"" . implode('", "', $columns) . "\") VALUES (" . implode(', ', $values) . ");\n";                    $this->backupMySQL($dbConnection, $filepath);            if (!file_exists($backupPath)) {

                    }

                    $sql .= "\n";                    break;                mkdir($backupPath, 0755, true);

                }

            }            }

        }

                case 'pgsql':

        file_put_contents($filepath, $sql);

    }                    $this->backupPostgreSQL($dbConnection, $filepath);            $timestamp = now()->format('Y-m-d_H-i-s');



    protected function backupSQLite(array $config, string $filepath): void                    break;            $filename = "backup_{$timestamp}.sql";

    {

        $databasePath = $config['database'];            $filepath = $backupPath . '/' . $filename;



        if (!file_exists($databasePath)) {                case 'sqlite':

            throw new \Exception("SQLite database file not found: {$databasePath}");

        }                    $this->backupSQLite($dbConnection, $filepath);            $this->info("� Backup location: {$filepath}");



        $this->info("🔄 Creating SQLite backup...");                    break;



        // For SQLite, just copy the file            switch ($dbConnection['driver']) {

        copy($databasePath, $filepath);

    }                default:                case 'mysql':



    protected function formatBytes(int $bytes): string                    $this->error("❌ Unsupported database driver: {$dbConnection['driver']}");                case 'mariadb':

    {

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];                    $this->info('💡 Supported drivers: MySQL, MariaDB, PostgreSQL, SQLite');                    $this->backupMySQL($dbConnection, $filepath);

        $i = 0;

                    return Command::FAILURE;                    break;

        while ($bytes >= 1024 && $i < count($units) - 1) {

            $bytes /= 1024;            }

            $i++;

        }                case 'pgsql':



        return round($bytes, 2) . ' ' . $units[$i];            $this->info("✅ Database backup completed successfully!");                    $this->backupPostgreSQL($dbConnection, $filepath);

    }

            $this->info("📄 Backup file: {$filepath}");                    break;

    protected function handleGenerateMigrations(): int

    {

        $this->info('Generating migrations...');

        $this->warn('⚠️  Migration generation not yet implemented in v3.0');            // Show file size                case 'sqlite':

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;            if (file_exists($filepath)) {                    $this->backupSQLite($dbConnection, $filepath);

    }

                $size = $this->formatBytes(filesize($filepath));                    break;

    protected function handleRunMigrations(): int

    {                $this->info("📊 Backup size: {$size}");

        $this->info('Running migrations...');

        $this->warn('⚠️  Migration running not yet implemented in v3.0');            }                default:

        $this->info('Please run migrations manually: php artisan migrate');

        return Command::SUCCESS;                    $this->error("❌ Unsupported database driver: {$dbConnection['driver']}");

    }

            return Command::SUCCESS;                    $this->info('💡 Supported drivers: MySQL, MariaDB, PostgreSQL, SQLite');

    protected function handleAnalyze(): int

    {                    return Command::FAILURE;

        $this->info('Running comprehensive analysis...');

        $this->warn('⚠️  Analysis functionality not yet implemented in v3.0');        } catch (\Exception $e) {            }

        $this->info('Running basic model checks instead...');

        return $this->handleModelChecks();            $this->error("❌ Backup failed: " . $e->getMessage());

    }

            return Command::FAILURE;            $this->info("✅ Database backup completed successfully!");

    protected function handleGenerateSchema(): int

    {        }            $this->info("📄 Backup file: {$filepath}");

        $this->info('Generating schema documentation...');

        $this->warn('⚠️  Schema generation not yet implemented in v3.0');    }

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;            // Show file size

    }

    protected function backupMySQL(array $config, string $filepath): void            if (file_exists($filepath)) {

    protected function handleGenerateSchemaSql(): int

    {    {                $size = $this->formatBytes(filesize($filepath));

        $this->info('Generating schema SQL...');

        $this->warn('⚠️  Schema SQL generation not yet implemented in v3.0');        $host = $config['host'] ?? 'localhost';                $this->info("📊 Backup size: {$size}");

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;        $port = $config['port'] ?? 3306;            }

    }

        $database = $config['database'];

    protected function handleCheckFilament(): int

    {        $username = $config['username'];            return Command::SUCCESS;

        $this->info('Checking Filament relationships...');

        $this->warn('⚠️  Filament checking not yet implemented in v3.0');        $password = $config['password'];

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;        } catch (\Exception $e) {

    }

        $command = sprintf(            $this->error("❌ Backup failed: " . $e->getMessage());

    protected function displayResults(): void

    {            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',            return Command::FAILURE;

        $stats = $this->issueManager->getStats();

            escapeshellarg($host),        }

        if ($this->option('json')) {

            $this->outputJson();            escapeshellarg($port),    }

            return;

        }            escapeshellarg($username),



        $this->info('');            escapeshellarg($password),    protected function backupMySQL(array $config, string $filepath): void

        $this->info('Results Summary');

        $this->info('===============');            escapeshellarg($database),    {



        if ($stats['total_issues'] === 0) {            escapeshellarg($filepath)        $host = $config['host'] ?? 'localhost';

            $this->info('✅ No issues found!');

            return;        );        $port = $config['port'] ?? 3306;

        }

        $database = $config['database'];

        $this->warn("⚠️  Found {$stats['total_issues']} issue(s)");

        $this->info("🔄 Executing: mysqldump --host={$host} --port={$port} --user={$username} [password] {$database}");        $username = $config['username'];

        // Display issues

        $issues = $this->issueManager->getIssues();        $password = $config['password'];

        foreach ($issues as $issue) {

            $this->line("  • " . ($issue['message'] ?? $issue['type']));        $exitCode = $this->runShellCommand($command);

            if (isset($issue['file'])) {

                $this->line("    📁 " . $issue['file']);        $command = sprintf(

            }

        }        if ($exitCode !== 0) {            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',



        // Display code improvements            throw new \Exception("mysqldump command failed with exit code {$exitCode}");            escapeshellarg($host),

        $this->displayCodeImprovements($issues);

    }        }            escapeshellarg($port),



    protected function displayCodeImprovements(array $issues): void    }            escapeshellarg($username),

    {

        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));            escapeshellarg($password),



        if (empty($improvements)) {    protected function backupPostgreSQL(array $config, string $filepath): void            escapeshellarg($database),

            return;

        }    {            escapeshellarg($filepath)



        $this->info('');        $host = $config['host'] ?? 'localhost';        );

        $this->info('💡 Code Improvement Suggestions (' . count($improvements) . '):');

        $port = $config['port'] ?? 5432;

        foreach ($improvements as $issue) {

            $improvement = $issue['improvement'];        $database = $config['database'];        $this->info("🔄 Executing: mysqldump --host={$host} --port={$port} --user={$username} [password] {$database}");

            $this->info("  • {$improvement->getTitle()}");

            $this->line("    {$improvement->getDescription()}");        $username = $config['username'];



            if ($improvement->canAutoFix()) {        $password = $config['password'];        $exitCode = $this->runShellCommand($command);

                $this->info("    ✅ Can be automatically fixed");

            }

        }

        // Set PGPASSWORD environment variable        if ($exitCode !== 0) {

        if (!$this->option('dry-run') && $this->confirm('Apply automatic fixes?', false)) {

            $this->applyCodeImprovements($improvements);        putenv("PGPASSWORD={$password}");            throw new \Exception("mysqldump command failed with exit code {$exitCode}");

        }

    }        }



    protected function applyCodeImprovements(array $improvements): void        $command = sprintf(    }

    {

        $applied = 0;            'pg_dump --host=%s --port=%s --username=%s --dbname=%s --no-password > %s',



        foreach ($improvements as $issue) {            escapeshellarg($host),    protected function backupPostgreSQL(array $config, string $filepath): void

            $improvement = $issue['improvement'];

            escapeshellarg($port),    {

            if ($improvement->canAutoFix() && $improvement->applyFix()) {

                $this->info("✅ Applied: {$improvement->getTitle()}");            escapeshellarg($username),        $host = $config['host'] ?? 'localhost';

                $applied++;

            }            escapeshellarg($database),        $port = $config['port'] ?? 5432;

        }

            escapeshellarg($filepath)        $database = $config['database'];

        if ($applied > 0) {

            $this->info('');        );        $username = $config['username'];

            $this->info("🎉 Successfully applied {$applied} automatic fixes!");

        }        $password = $config['password'];

    }

        $this->info("🔄 Executing: pg_dump --host={$host} --port={$port} --username={$username} {$database}");

    protected function outputJson(): void

    {        // Set PGPASSWORD environment variable

        $result = [

            'timestamp' => now()->toISOString(),        $exitCode = $this->runShellCommand($command);        putenv("PGPASSWORD={$password}");

            'stats' => $this->issueManager->getStats(),

            'issues' => $this->issueManager->getIssues(),

            'checkers' => $this->checkerManager->getAvailableCheckers(),

        ];        if ($exitCode !== 0) {        $command = sprintf(



        $this->line(json_encode($result, JSON_PRETTY_PRINT));            throw new \Exception("pg_dump command failed with exit code {$exitCode}");            'pg_dump --host=%s --port=%s --username=%s --dbname=%s --no-password > %s',

    }

}        }            escapeshellarg($host),

    }            escapeshellarg($port),

            escapeshellarg($username),

    protected function backupSQLite(array $config, string $filepath): void            escapeshellarg($database),

    {            escapeshellarg($filepath)

        $databasePath = $config['database'];        );



        if (!file_exists($databasePath)) {        $this->info("🔄 Executing: pg_dump --host={$host} --port={$port} --username={$username} {$database}");

            throw new \Exception("SQLite database file not found: {$databasePath}");

        }        $exitCode = $this->runShellCommand($command);



        $command = sprintf(        if ($exitCode !== 0) {

            'cp %s %s',            throw new \Exception("pg_dump command failed with exit code {$exitCode}");

            escapeshellarg($databasePath),        }

            escapeshellarg($filepath)    }

        );

    protected function backupSQLite(array $config, string $filepath): void

        $this->info("🔄 Executing: cp {$databasePath} {$filepath}");    {

        $databasePath = $config['database'];

        $exitCode = $this->runShellCommand($command);

        if (!file_exists($databasePath)) {

        if ($exitCode !== 0) {            throw new \Exception("SQLite database file not found: {$databasePath}");

            throw new \Exception("SQLite copy command failed with exit code {$exitCode}");        }

        }

    }        $command = sprintf(

            'cp %s %s',

    protected function runShellCommand(string $command): int            escapeshellarg($databasePath),

    {            escapeshellarg($filepath)

        $this->info("🔄 Running backup command...");        );



        // Use Laravel's process helper for better error handling        $this->info("🔄 Executing: cp {$databasePath} {$filepath}");

        $process = Process::run($command);

        $exitCode = $this->runShellCommand($command);

        if (!$process->successful()) {

            $this->error("Command output: " . $process->output());        if ($exitCode !== 0) {

            $this->error("Command error: " . $process->errorOutput());            throw new \Exception("SQLite copy command failed with exit code {$exitCode}");

        }        }

    }

        return $process->exitCode();

    }    protected function runShellCommand(string $command): int

    {

    protected function formatBytes(int $bytes): string        $this->info("� Running backup command...");

    {

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];        // Use Laravel's process helper for better error handling

        $i = 0;        $process = \Illuminate\Support\Facades\Process::run($command);



        while ($bytes >= 1024 && $i < count($units) - 1) {        if (!$process->successful()) {

            $bytes /= 1024;            $this->error("Command output: " . $process->output());

            $i++;            $this->error("Command error: " . $process->errorOutput());

        }        }



        return round($bytes, 2) . ' ' . $units[$i];        return $process->exitCode();

    }    }



    protected function handleGenerateMigrations(): int    protected function formatBytes(int $bytes): string

    {    {

        $this->info('Generating migrations...');        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $this->warn('⚠️  Migration generation not yet implemented in v3.0');        $i = 0;

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;        while ($bytes >= 1024 && $i < count($units) - 1) {

    }            $bytes /= 1024;

            $i++;

    protected function handleRunMigrations(): int        }

    {

        $this->info('Running migrations...');        return round($bytes, 2) . ' ' . $units[$i];

        $this->warn('⚠️  Migration running not yet implemented in v3.0');    }

        $this->info('Please run migrations manually: php artisan migrate');

        return Command::SUCCESS;    protected function handleGenerateMigrations(): int

    }    {

        $this->info('Generating migrations...');

    protected function handleAnalyze(): int        $this->warn('⚠️  Migration generation not yet implemented in v3.0');

    {        $this->info('This feature will be available in a future update.');

        $this->info('Running comprehensive analysis...');        return Command::SUCCESS;

        $this->warn('⚠️  Analysis functionality not yet implemented in v3.0');    }

        $this->info('Running basic model checks instead...');

        return $this->handleModelChecks();    protected function handleRunMigrations(): int

    }    {

        $this->info('Running migrations...');

    protected function handleGenerateSchema(): int        $this->warn('⚠️  Migration running not yet implemented in v3.0');

    {        $this->info('Please run migrations manually: php artisan migrate');

        $this->info('Generating schema documentation...');        return Command::SUCCESS;

        $this->warn('⚠️  Schema generation not yet implemented in v3.0');    }

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;    protected function handleAnalyze(): int

    }    {

        $this->info('Running comprehensive analysis...');

    protected function handleGenerateSchemaSql(): int        $this->warn('⚠️  Analysis functionality not yet implemented in v3.0');

    {        $this->info('Running basic model checks instead...');

        $this->info('Generating schema SQL...');        return $this->handleModelChecks();

        $this->warn('⚠️  Schema SQL generation not yet implemented in v3.0');    }

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;    protected function handleGenerateSchema(): int

    }    {

        $this->info('Generating schema documentation...');

    protected function handleCheckFilament(): int        $this->warn('⚠️  Schema generation not yet implemented in v3.0');

    {        $this->info('This feature will be available in a future update.');

        $this->info('Checking Filament relationships...');        return Command::SUCCESS;

        $this->warn('⚠️  Filament checking not yet implemented in v3.0');    }

        $this->info('This feature will be available in a future update.');

        return Command::SUCCESS;    protected function handleGenerateSchemaSql(): int

    }    {

        $this->info('Generating schema SQL...');

    protected function displayResults(): void        $this->warn('⚠️  Schema SQL generation not yet implemented in v3.0');

    {        $this->info('This feature will be available in a future update.');

        $stats = $this->issueManager->getStats();        return Command::SUCCESS;

    }

        if ($this->option('json')) {

            $this->outputJson();    protected function handleCheckFilament(): int

            return;    {

        }        $this->info('Checking Filament relationships...');

        $this->warn('⚠️  Filament checking not yet implemented in v3.0');

        $this->info('');        $this->info('This feature will be available in a future update.');

        $this->info('Results Summary');        return Command::SUCCESS;

        $this->info('===============');    }



        if ($stats['total_issues'] === 0) {    protected function displayResults(): void

            $this->info('✅ No issues found!');    {

            return;        $stats = $this->issueManager->getStats();

        }

        if ($this->option('json')) {

        $this->warn("⚠️  Found {$stats['total_issues']} issue(s)");            $this->outputJson();

            return;

        // Display issues        }

        $issues = $this->issueManager->getIssues();

        foreach ($issues as $issue) {        $this->info('');

            $this->line("  • " . ($issue['message'] ?? $issue['type']));        $this->info('Results Summary');

            if (isset($issue['file'])) {        $this->info('===============');

                $this->line("    📁 " . $issue['file']);

            }        if ($stats['total_issues'] === 0) {

        }            $this->info('✅ No issues found!');

            return;

        // Display code improvements        }

        $this->displayCodeImprovements($issues);

    }        $this->warn("⚠️  Found {$stats['total_issues']} issue(s)");



    protected function displayCodeImprovements(array $issues): void        // Display issues

    {        $issues = $this->issueManager->getIssues();

        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));        foreach ($issues as $issue) {

            $this->line("  • " . ($issue['message'] ?? $issue['type']));

        if (empty($improvements)) {            if (isset($issue['file'])) {

            return;                $this->line("    📁 " . $issue['file']);

        }            }

        }

        $this->info('');

        $this->info('💡 Code Improvement Suggestions (' . count($improvements) . '):');        // Display code improvements

        $this->displayCodeImprovements($issues);

        foreach ($improvements as $issue) {    }

            $improvement = $issue['improvement'];

            $this->info("  • {$improvement->getTitle()}");    protected function displayCodeImprovements(array $issues): void

            $this->line("    {$improvement->getDescription()}");    {

        $improvements = array_filter($issues, fn($issue) => isset($issue['improvement']));

            if ($improvement->canAutoFix()) {

                $this->info("    ✅ Can be automatically fixed");        if (empty($improvements)) {

            }            return;

        }        }



        if (!$this->option('dry-run') && $this->confirm('Apply automatic fixes?', false)) {        $this->info('');

            $this->applyCodeImprovements($improvements);        $this->info('💡 Code Improvement Suggestions (' . count($improvements) . '):');

        }

    }        foreach ($improvements as $issue) {

            $improvement = $issue['improvement'];

    protected function applyCodeImprovements(array $improvements): void            $this->info("  • {$improvement->getTitle()}");

    {            $this->line("    {$improvement->getDescription()}");

        $applied = 0;

            if ($improvement->canAutoFix()) {

        foreach ($improvements as $issue) {                $this->info("    ✅ Can be automatically fixed");

            $improvement = $issue['improvement'];            }

        }

            if ($improvement->canAutoFix() && $improvement->applyFix()) {

                $this->info("✅ Applied: {$improvement->getTitle()}");        if (!$this->option('dry-run') && $this->confirm('Apply automatic fixes?', false)) {

                $applied++;            $this->applyCodeImprovements($improvements);

            }        }

        }    }



        if ($applied > 0) {    protected function applyCodeImprovements(array $improvements): void

            $this->info('');    {

            $this->info("🎉 Successfully applied {$applied} automatic fixes!");        $applied = 0;

        }

    }        foreach ($improvements as $issue) {

            $improvement = $issue['improvement'];

    protected function outputJson(): void

    {            if ($improvement->canAutoFix() && $improvement->applyFix()) {

        $result = [                $this->info("✅ Applied: {$improvement->getTitle()}");

            'timestamp' => now()->toISOString(),                $applied++;

            'stats' => $this->issueManager->getStats(),            }

            'issues' => $this->issueManager->getIssues(),        }

            'checkers' => $this->checkerManager->getAvailableCheckers(),

        ];        if ($applied > 0) {

            $this->info('');

        $this->line(json_encode($result, JSON_PRETTY_PRINT));            $this->info("🎉 Successfully applied {$applied} automatic fixes!");

    }        }

}    }

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