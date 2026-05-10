<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Checkers\MigrationChecker;

/**
 * MigrationCheckerCriticalityIntegrationTest - Tests integration of criticality analysis with MigrationChecker
 *
 * Tests that the MigrationChecker properly integrates with the MigrationCriticalityAnalyzer
 * service and displays criticality analysis results when enabled.
 */
class MigrationCheckerCriticalityIntegrationTest extends TestCase
{
    private string $tempDir;
    private string $migrationDir;
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/migration_checker_integration_test_' . uniqid();
        $this->migrationDir = $this->tempDir . '/database/migrations';
        mkdir($this->migrationDir, 0755, true);

        $this->config = [
            'migration_validation_mode' => 'migration_files',
            'excluded_files' => [],
            'rules' => [
                'enabled' => ['migration_syntax' => true]
            ]
        ];
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            exec("rm -rf " . escapeshellarg($this->tempDir));
        }

        parent::tearDown();
    }

    public function testEnablesCriticalityAnalysisWhenConfigured(): void
    {
        $config = $this->config;
        $config['enable_criticality_analysis'] = true;

        $checker = new MigrationChecker($config, $this->migrationDir);

        // Create a test migration with issues
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TestMigration extends Migration
{
    public function up()
    {
        Schema::dropIfExists(\'test_table\');
        Schema::create(\'test_table\', function (Blueprint $table) {
            $table->id();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'test_table\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_test_migration.php', $migrationContent);

        $issues = $checker->check();

        // The checker should have processed the criticality analysis
        $this->assertIsArray($issues);
    }

    public function testSkipsCriticalityAnalysisWhenNotConfigured(): void
    {
        $config = $this->config;
        $config['enable_criticality_analysis'] = false;

        $checker = new MigrationChecker($config, $this->migrationDir);

        $issues = $checker->check();

        // Should work normally without criticality analysis
        $this->assertIsArray($issues);
    }

    public function testDisplaysCriticalityResultsInOutput(): void
    {
        $config = $this->config;
        $config['enable_criticality_analysis'] = true;

        $checker = new MigrationChecker($config, $this->migrationDir);

        // Create a migration with high-risk issues
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HighRiskMigration extends Migration
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

        file_put_contents($this->migrationDir . '/2024_01_01_000000_high_risk_migration.php', $migrationContent);

        // Capture output
        ob_start();
        $issues = $checker->check();
        $output = ob_get_clean();

        // Should contain criticality analysis output
        $this->assertStringContainsString('Migration Criticality Analysis', $output);
        $this->assertStringContainsString('HIGH', $output);
        $this->assertIsArray($issues);
    }

    public function testDisplaysCriticalIssuesProminently(): void
    {
        $config = $this->config;
        $config['enable_criticality_analysis'] = true;

        $checker = new MigrationChecker($config, $this->migrationDir);

        // Create a migration with critical syntax error
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CriticalSyntaxMigration extends Migration
{
    public function up()
    {
        Schema::create(\'test\', function (Blueprint $table) {
            $table->id();
            // Missing closing parenthesis and brace
    }

    public function down()
    {
        Schema::dropIfExists(\'test\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_critical_syntax.php', $migrationContent);

        ob_start();
        $issues = $checker->check();
        $output = ob_get_clean();

        $this->assertStringContainsString('CRITICAL', $output);
        $this->assertStringContainsString('DO NOT rerun migrations', $output);
    }

    public function testDisplaysRiskLevelAssessment(): void
    {
        $config = $this->config;
        $config['enable_criticality_analysis'] = true;

        $checker = new MigrationChecker($config, $this->migrationDir);

        // Create a clean migration
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CleanMigration extends Migration
{
    public function up()
    {
        Schema::create(\'clean_table\', function (Blueprint $table) {
            $table->id();
            $table->string(\'name\');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'clean_table\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_clean_migration.php', $migrationContent);

        ob_start();
        $issues = $checker->check();
        $output = ob_get_clean();

        $this->assertStringContainsString('Database Rerun Risk Level', $output);
        $this->assertStringContainsString('MINIMAL', $output);
    }

    public function testDisplaysRecommendations(): void
    {
        $config = $this->config;
        $config['enable_criticality_analysis'] = true;

        $checker = new MigrationChecker($config, $this->migrationDir);

        // Create a migration requiring data mapping
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DataMappingMigration extends Migration
{
    public function up()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->dropColumn(\'old_field\');
        });
    }

    public function down()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->string(\'old_field\');
        });
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_data_mapping.php', $migrationContent);

        ob_start();
        $issues = $checker->check();
        $output = ob_get_clean();

        $this->assertStringContainsString('Recommendations', $output);
        $this->assertStringContainsString('data mapping required', $output);
    }
}