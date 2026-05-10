<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests\Feature;

require_once __DIR__ . '/../TestCase.php';

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenerateMissingFieldE2EValidationTest extends TestCase
{
    public function test_generates_add_column_migration_for_missing_fillable_field(): void
    {
        $fixtureDir = dirname(__DIR__) . '/Fixtures';
        if (!is_dir($fixtureDir)) {
            mkdir($fixtureDir, 0755, true);
        }

        config()->set('model-schema-checker.models_dir', $fixtureDir);
        config()->set('model-schema-checker.excluded_models', []);
        config()->set('model-schema-checker.output.allow_file_writes', true);
        config()->set('model-schema-checker.security.allow_code_modification', true);

        $modelFixturePath = $fixtureDir . '/ValidationMissingFieldModel.php';
        file_put_contents($modelFixturePath, <<<'PHP'
<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class ValidationMissingFieldModel extends Model
{
    protected $table = 'validation_missing_field_models';

    protected $fillable = [
        'name',
        'missing_note',
    ];

    protected $casts = [
        'missing_note' => 'string',
    ];
}
PHP);

        require_once $modelFixturePath;

        if (!Schema::hasTable('validation_missing_field_models')) {
            Schema::create('validation_missing_field_models', function ($table) {
                $table->id();
                $table->string('name')->nullable();
            });
        }

        $before = glob(database_path('migrations/*add_missing_fields_to_validation_missing_field_models_table.php')) ?: [];

        $this->artisan('model:schema-check', [
            '--generate-missing-field-migrations' => true,
        ])
            ->expectsConfirmation('Write these migration files to database/migrations?', 'yes')
            ->expectsOutputToContain('Planned migrations: 1')
            ->assertExitCode(0);

        $after = glob(database_path('migrations/*add_missing_fields_to_validation_missing_field_models_table.php')) ?: [];
        $generated = array_values(array_diff($after, $before));

        $this->assertCount(1, $generated, 'Expected one migration file to be generated.');

        $content = file_get_contents($generated[0]);
        $this->assertNotFalse($content);
        $this->assertStringContainsString("Schema::table('validation_missing_field_models'", $content);
        $this->assertStringContainsString('$table->string(\'missing_note\', 255)->nullable();', $content);

        unlink($generated[0]);
        unlink($modelFixturePath);
    }
}
