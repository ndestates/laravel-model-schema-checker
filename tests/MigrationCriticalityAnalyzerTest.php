<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationCriticalityAnalyzer;

/**
 * MigrationCriticalityAnalyzerTest - Comprehensive test suite for MigrationCriticalityAnalyzer functionality
 *
 * Purpose: Validates MigrationCriticalityAnalyzer core functionality including migration analysis,
 * criticality detection, risk assessment, and recommendation generation
 *
 * Test Categories:
 * - Basic analysis functionality
 * - Criticality detection for different issue types
 * - Risk assessment calculations
 * - Recommendation generation
 * - Migration dependency analysis
 */
class MigrationCriticalityAnalyzerTest extends TestCase
{
    private MigrationCriticalityAnalyzer $analyzer;
    private string $tempDir;
    private string $migrationDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/migration_criticality_test_' . uniqid();
        $this->migrationDir = $this->tempDir . '/database/migrations';
        mkdir($this->migrationDir, 0755, true);

        $this->analyzer = new MigrationCriticalityAnalyzer();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            exec("rm -rf " . escapeshellarg($this->tempDir));
        }

        parent::tearDown();
    }

    public function testAnalyzesEmptyMigrationDirectory(): void
    {
        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertArrayHasKey('criticality', $analysis);
        $this->assertArrayHasKey('migration_count', $analysis);
        $this->assertArrayHasKey('issues_found', $analysis);
        $this->assertEquals(0, $analysis['migration_count']);
        $this->assertEquals(0, $analysis['issues_found']);
    }

    public function testAnalyzesDirectoryWithValidMigration(): void
    {
        // Create a valid migration file
        $migrationContent = '<?php

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
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'users\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_create_users_table.php', $migrationContent);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertEquals(1, $analysis['migration_count']);
        $this->assertEquals(0, $analysis['issues_found']);
        $this->assertFalse($analysis['data_mapping_required']);
    }

    public function testDetectsCriticalSyntaxErrors(): void
    {
        // Create a migration with obvious syntax errors
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BrokenMigration extends Migration
{
    public function up()
    {
        Schema::create(\'users\', function (Blueprint $table) {
            $table->id();
            $table->string(\'name\');
            // Syntax error: unclosed string
            $table->string(\'email);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'users\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_broken_migration.php', $migrationContent);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertGreaterThan(0, $analysis['issues_found']);
        $this->assertNotEmpty($analysis['criticality']['CRITICAL']);
    }

    public function testDetectsHighDataLossPotential(): void
    {
        // Create a migration that drops columns
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DataLossMigration extends Migration
{
    public function up()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->dropColumn(\'important_data\');
            $table->dropColumn(\'critical_info\');
        });
    }

    public function down()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->string(\'important_data\');
            $table->text(\'critical_info\');
        });
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000001_data_loss_migration.php', $migrationContent);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertGreaterThan(0, $analysis['issues_found']);
        $this->assertNotEmpty($analysis['criticality']['HIGH']);
        $this->assertTrue($analysis['data_mapping_required']);
    }

    public function testDetectsMediumForeignKeyIssues(): void
    {
        // Create a migration with foreign key issues
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ForeignKeyMigration extends Migration
{
    public function up()
    {
        Schema::create(\'posts\', function (Blueprint $table) {
            $table->id();
            $table->string(\'title\');
            $table->foreignId(\'user_id\')->constrained(); // Foreign key without proper setup
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'posts\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000002_foreign_key_migration.php', $migrationContent);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertGreaterThan(0, $analysis['issues_found']);
        $this->assertNotEmpty($analysis['criticality']['MEDIUM']);
    }

    public function testDetectsMediumNamingConventionIssues(): void
    {
        // Create a migration with invalid filename
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InvalidFilename extends Migration
{
    public function up()
    {
        Schema::create(\'test_table\', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'test_table\');
    }
}';

        file_put_contents($this->migrationDir . '/invalid_filename.php', $migrationContent);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertGreaterThan(0, $analysis['issues_found']);
        $this->assertNotEmpty($analysis['criticality']['MEDIUM']);
    }

    public function testCalculatesMinimalRiskForCleanMigrations(): void
    {
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

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertEquals('MINIMAL', $analysis['rerun_risk_level']);
    }

    public function testCalculatesExtremeRiskForCriticalIssues(): void
    {
        // Create a migration with critical issues
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CriticalMigration extends Migration
{
    public function up()
    {
        // Syntax error: unclosed string
        Schema::create(\'critical_table\', function (Blueprint $table) {
            $table->id();
            $table->string(\'name\');
            $table->string(\'email);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'critical_table\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_critical_migration.php', $migrationContent);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertEquals('EXTREME', $analysis['rerun_risk_level']);
    }

    public function testProvidesImmediateRecommendationsForCriticalIssues(): void
    {
        // Create a migration with critical syntax issues
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CriticalSyntax extends Migration
{
    public function up()
    {
        Schema::create(\'critical_syntax\', function (Blueprint $table) {
            $table->id();
            // Syntax error - unclosed string
            $table->string(\'name\');
            $table->string(\'email);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists(\'critical_syntax\');
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_critical_syntax.php', $migrationContent);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $immediateRecommendations = array_filter($analysis['recommendations'], function ($rec) {
            return $rec['priority'] === 'IMMEDIATE';
        });

        $this->assertNotEmpty($immediateRecommendations);
    }

    public function testProvidesHighPriorityRecommendationsForDataLoss(): void
    {
        // Create a migration with data loss
        $migrationContent = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DataLoss extends Migration
{
    public function up()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->dropColumn(\'email\');
            $table->dropColumn(\'phone\');
        });
    }

    public function down()
    {
        Schema::table(\'users\', function (Blueprint $table) {
            $table->string(\'email\');
            $table->string(\'phone\');
        });
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_data_loss.php', $migrationContent);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $highRecommendations = array_filter($analysis['recommendations'], function ($rec) {
            return $rec['priority'] === 'HIGH';
        });

        $this->assertNotEmpty($highRecommendations);
    }

    public function testDetectsTableConflictsBetweenMigrations(): void
    {
        // Create two migrations that might conflict
        $content1 = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create("users", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists("users");
    }
}';

        $content2 = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailToUsers extends Migration
{
    public function up()
    {
        Schema::table("users", function (Blueprint $table) {
            $table->string("email")->unique();
        });
    }

    public function down()
    {
        Schema::table("users", function (Blueprint $table) {
            $table->dropColumn("email");
        });
    }
}';

        file_put_contents($this->migrationDir . '/2024_01_01_000000_create_users_table.php', $content1);
        file_put_contents($this->migrationDir . '/2024_01_01_000001_add_email_to_users.php', $content2);

        $analysis = $this->analyzer->analyzeMigrations($this->migrationDir);

        $this->assertGreaterThan(0, $analysis['issues_found']);
        $this->assertNotEmpty($analysis['criticality']['MEDIUM']);
    }
}
