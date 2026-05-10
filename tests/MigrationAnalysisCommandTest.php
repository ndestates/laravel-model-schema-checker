<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Commands\ModelSchemaCheckCommand;
use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationGenerator;
use NDEstates\LaravelModelSchemaChecker\Services\DataExporter;
use NDEstates\LaravelModelSchemaChecker\Services\DataImporter;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationCleanup;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * MigrationAnalysisCommandTest - Tests for migration analysis command options
 *
 * Tests the new command-line options for migration criticality analysis,
 * backup creation, data mapping, and execution mapping.
 */
class MigrationAnalysisCommandTest extends TestCase
{
    private Application $application;
    private ModelSchemaCheckCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;
    private string $migrationDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/command_test_' . uniqid();
        $this->migrationDir = $this->tempDir . '/database/migrations';
        mkdir($this->migrationDir, 0755, true);

        // Create mock services
        $checkerManager = $this->createMock(CheckerManager::class);
        $issueManager = $this->createMock(IssueManager::class);
        $migrationGenerator = $this->createMock(MigrationGenerator::class);
        $dataExporter = $this->createMock(DataExporter::class);
        $dataImporter = $this->createMock(DataImporter::class);
        $migrationCleanup = $this->createMock(MigrationCleanup::class);

        $this->command = new ModelSchemaCheckCommand(
            $checkerManager,
            $issueManager,
            $migrationGenerator,
            $dataExporter,
            $dataImporter,
            $migrationCleanup
        );

        $this->application = new Application();
        $this->application->add($this->command);
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            exec("rm -rf " . escapeshellarg($this->tempDir));
        }

        parent::tearDown();
    }

    public function testExecutesMigrationCriticalityAnalysis(): void
    {
        // Create a test migration
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TestAnalysisMigration extends Migration
{
    public function up()
    {
        Schema::create(\'test_table\', function (Blueprint $table) {
            $table->id();
            $table->string(\'name\');
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'test_table\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_test_analysis_migration.php', $migrationContent);

        // Mock the migration path for testing
        putenv('MIGRATION_PATH=' . $this->migrationDir);

        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--analyze-migrations' => true,
            '--dry-run' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Migration Criticality Analysis', $output);
        $this->assertStringContainsString('CRITICAL', $output);
        $this->assertStringContainsString('HIGH', $output);
        $this->assertStringContainsString('MEDIUM', $output);
        $this->assertStringContainsString('LOW', $output);
        $this->assertStringContainsString('LEAST', $output);
    }

    public function testShowsMigrationAnalysisResults(): void
    {
        // Create a migration with issues
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ProblematicMigration extends Migration
{
    public function up()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->dropColumn(\'email\');
        });
    }

    public function down()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->string(\'email\');
        });
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_problematic_migration.php', $migrationContent);

        putenv('MIGRATION_PATH=' . $this->migrationDir);

        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--analyze-migrations' => true,
            '--dry-run' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Migration Criticality Analysis', $output);
        $this->assertStringContainsString('issues found', $output);
        $this->assertStringContainsString('HIGH', $output);
    }

    public function testCreatesDatabaseBackup(): void
    {
        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--create-backup' => true,
            '--dry-run' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Database Backup', $output);
        $this->assertStringContainsString('backup', $output);
    }

    public function testCreatesDataMappingStrategy(): void
    {
        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--map-data' => true,
            '--dry-run' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Data Mapping Strategy', $output);
        $this->assertStringContainsString('mapping', $output);
    }

    public function testExecutesDataMapping(): void
    {
        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--execute-mapping' => true,
            '--dry-run' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Execute Data Mapping', $output);
        $this->assertStringContainsString('mapping', $output);
    }

    public function testSupportsAnalyzeThenBackupWorkflow(): void
    {
        // Create a test migration
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WorkflowTestMigration extends Migration
{
    public function up()
    {
        Schema::create(\'workflow_test\', function (Blueprint $table) {
            $table->id();
            $table->string(\'name\');
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'workflow_test\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_workflow_test_migration.php', $migrationContent);

        putenv('MIGRATION_PATH=' . $this->migrationDir);

        // First analyze
        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--analyze-migrations' => true,
            '--dry-run' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Migration Criticality Analysis', $output);

        // Then create backup
        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--create-backup' => true,
            '--dry-run' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Database Backup', $output);
    }

    public function testShowsNewOptionsInHelpText(): void
    {
        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--help' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('--analyze-migrations', $output);
        $this->assertStringContainsString('--create-backup', $output);
        $this->assertStringContainsString('--map-data', $output);
        $this->assertStringContainsString('--execute-mapping', $output);
    }

    public function testDescribesAnalyzeMigrationsOption(): void
    {
        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--help' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Analyze migration criticality', $output);
    }

    public function testDescribesCreateBackupOption(): void
    {
        $this->commandTester->execute([
            'command' => 'model:schema-check',
            '--help' => true
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Create database backup', $output);
    }
}