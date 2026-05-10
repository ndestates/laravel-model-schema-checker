<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationCriticalityAnalyzer;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationDataMapper;
use NDEstates\LaravelModelSchemaChecker\Checkers\MigrationChecker;

/**
 * MigrationCriticalityAnalysisFeatureTest - End-to-end tests for migration criticality analysis feature
 *
 * Tests the complete workflow of migration criticality analysis including
 * analysis, data mapping, and integration with existing checkers.
 */
class MigrationCriticalityAnalysisFeatureTest extends TestCase
{
    private string $tempDir;
    private string $migrationDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/migration_feature_test_' . uniqid();
        $this->migrationDir = $this->tempDir . '/database/migrations';
        mkdir($this->migrationDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            exec("rm -rf " . escapeshellarg($this->tempDir));
        }

        parent::tearDown();
    }

    public function testHandlesCleanMigrationsWithoutIssues(): void
    {
        // Create clean migrations
        $cleanMigrations = [
            '2024_01_01_000000_create_users_table.php' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create(\'users\', function (Blueprint $table) {
            $table->id();
            $table->string(\'name\');
            $table->string(\'email\')->unique();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'users\');
    }
}',

            '2024_01_01_000001_create_posts_table.php' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    public function up()
    {
        Schema::create(\'posts\', function (Blueprint $table) {
            $table->id();
            $table->string(\'title\');
            $table->text(\'content\');
            $table->foreignId(\'user_id\')->constrained();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'posts\');
    }
}'
        ];

        foreach ($cleanMigrations as $filename => $content) {
            file_put_contents($this->migrationDir . '/' . $filename, $content);
        }

        $analyzer = new MigrationCriticalityAnalyzer();
        $analysis = $analyzer->analyzeMigrations($this->migrationDir);

        $this->assertEquals(2, $analysis['migration_count']);
        $this->assertEquals(0, $analysis['issues_found']);
        $this->assertFalse($analysis['data_mapping_required']);
        $this->assertEquals('MINIMAL', $analysis['rerun_risk_level']);
        $this->assertEmpty($analysis['criticality']['CRITICAL']);
        $this->assertEmpty($analysis['criticality']['HIGH']);
    }

    public function testDetectsAndPrioritizesMultipleTypesOfIssues(): void
    {
        // Create migrations with various issues
        $problematicMigrations = [
            '2024_01_01_000000_critical_syntax.php' => '<?php

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
}',

            '2024_01_01_000001_high_risk_data_loss.php' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HighRiskDataLossMigration extends Migration
{
    public function up()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->dropColumn(\'important_field\');
            $table->dropColumn(\'another_field\');
        });
    }

    public function down()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->string(\'important_field\');
            $table->string(\'another_field\');
        });
    }
}',

            '2024_01_01_000002_medium_foreign_key.php' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MediumForeignKeyMigration extends Migration
{
    public function up()
    {
        Schema::table(\'posts\', function (Blueprint $table) {
            $table->foreign(\'user_id\')->references(\'id\')->on(\'users\');
        });
    }

    public function down()
    {
        Schema::table(\'posts\', function (Blueprint $table) {
            $table->dropForeign([\'user_id\']);
        });
    }
}',

            '2024_01_01_000003_low_performance.php' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LowPerformanceMigration extends Migration
{
    public function up()
    {
        Schema::table(\'large_table\', function (Blueprint $table) {
            $table->string(\'new_field\');
        });
    }

    public function down()
    {
        Schema::table(\'large_table\', function (Blueprint $table) {
            $table->dropColumn(\'new_field\');
        });
    }
}'
        ];

        foreach ($problematicMigrations as $filename => $content) {
            file_put_contents($this->migrationDir . '/' . $filename, $content);
        }

        $analyzer = new MigrationCriticalityAnalyzer();
        $analysis = $analyzer->analyzeMigrations($this->migrationDir);

        $this->assertEquals(4, $analysis['migration_count']);
        $this->assertGreaterThan(0, $analysis['issues_found']);
        $this->assertTrue($analysis['data_mapping_required']);
        $this->assertEquals('EXTREME', $analysis['rerun_risk_level']);

        // Should have issues in multiple categories
        $this->assertNotEmpty($analysis['criticality']['CRITICAL']);
        $this->assertNotEmpty($analysis['criticality']['HIGH']);
        $this->assertNotEmpty($analysis['criticality']['MEDIUM']);

        // Should have appropriate recommendations
        $this->assertNotEmpty($analysis['recommendations']);

        $immediateRecs = array_filter($analysis['recommendations'], fn($r) => $r['priority'] === 'IMMEDIATE');
        $this->assertNotEmpty($immediateRecs);
    }

    public function testProvidesActionableRecommendationsForDifferentRiskLevels(): void
    {
        // Create a high-risk migration
        $highRiskMigration = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HighRiskMigration extends Migration
{
    public function up()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->nullable(\'email\', false)->change();
        });
    }

    public function down()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->nullable(\'email\', true)->change();
        });
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_high_risk.php', $highRiskMigration);

        $analyzer = new MigrationCriticalityAnalyzer();
        $analysis = $analyzer->analyzeMigrations($this->migrationDir);

        $this->assertNotEmpty($analysis['recommendations']);

        // Should have backup recommendation
        $backupRecs = array_filter($analysis['recommendations'], function ($rec) {
            return str_contains(strtolower($rec['action']), 'backup');
        });
        $this->assertNotEmpty($backupRecs);

        // Should have data mapping recommendation
        $mappingRecs = array_filter($analysis['recommendations'], function ($rec) {
            return str_contains(strtolower($rec['action']), 'mapping');
        });
        $this->assertNotEmpty($mappingRecs);
    }

    public function testCreatesComprehensiveMappingStrategyForComplexMigrations(): void
    {
        // Create migrations that require complex data mapping
        $complexMigrations = [
            '2024_01_01_000000_create_initial_schema.php' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInitialSchema extends Migration
{
    public function up()
    {
        Schema::create(\'users\', function (Blueprint $table) {
            $table->id();
            $table->string(\'full_name\');
            $table->string(\'email_address\');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'users\');
    }
}',

            '2024_01_01_000001_refactor_user_fields.php' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RefactorUserFields extends Migration
{
    public function up()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->renameColumn(\'full_name\', \'name\');
            $table->renameColumn(\'email_address\', \'email\');
            $table->dropColumn(\'created_at\');
        });
    }

    public function down()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->renameColumn(\'name\', \'full_name\');
            $table->renameColumn(\'email\', \'email_address\');
            $table->timestamp(\'created_at\');
        });
    }
}',

            '2024_01_01_000002_add_foreign_keys.php' => '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeys extends Migration
{
    public function up()
    {
        Schema::table(\'posts\', function (Blueprint $table) {
            $table->foreign(\'author_id\')->references(\'id\')->on(\'users\');
        });
    }

    public function down()
    {
        Schema::table(\'posts\', function (Blueprint $table) {
            $table->dropForeign([\'author_id\']);
        });
    }
}'
        ];

        foreach ($complexMigrations as $filename => $content) {
            file_put_contents($this->migrationDir . '/' . $filename, $content);
        }

        // Analyze migrations
        $analyzer = new MigrationCriticalityAnalyzer();
        $analysis = $analyzer->analyzeMigrations($this->migrationDir);

        // Create mapping strategy
        $mapper = new MigrationDataMapper($this->tempDir);
        $backup = $mapper->createBackupWithMetadata();
        $strategy = $mapper->createDataMappingStrategy($analysis, $backup['backup_id']);

        $this->assertTrue($strategy['mappings_required']);
        $this->assertNotEmpty($strategy['data_transformations']);
        $this->assertEquals('HIGH', $strategy['risk_assessment']['overall_risk']);
        $this->assertTrue($strategy['risk_assessment']['data_loss_potential']);
    }

    public function testIntegratesCriticalityAnalysisWithExistingChecker(): void
    {
        $config = [
            'migration_validation_mode' => 'migration_files',
            'excluded_files' => [],
            'enable_criticality_analysis' => true,
            'rules' => [
                'enabled' => ['migration_syntax' => true]
            ]
        ];

        $checker = new MigrationChecker($config, $this->migrationDir);

        // Create a migration with multiple issues
        $complexMigration = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ComplexMigration extends Migration
{
    public function up()
    {
        Schema::dropIfExists(\'old_table\');
        Schema::create(\'new_table\', function (Blueprint $table) {
            $table->id();
            $table->string(\'name\');
            $table->foreign(\'ref_id\')->references(\'id\')->on(\'other_table\');
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'new_table\');
        Schema::create(\'old_table\', function (Blueprint $table) {
            $table->id();
        });
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_complex_migration.php', $complexMigration);

        ob_start();
        $issues = $checker->check();
        $output = ob_get_clean();

        // Should contain both regular checking and criticality analysis
        $this->assertStringContainsString('Checking Migration Consistency', $output);
        $this->assertStringContainsString('Migration Criticality Analysis', $output);
        $this->assertStringContainsString('CRITICAL', $output);
        $this->assertIsArray($issues);
    }
}