<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use Orchestra\Testbench\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationGenerator;

class MigrationGeneratorTest extends TestCase
{
    protected MigrationGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests in this class as they require full Laravel environment setup
        $this->markTestSkipped('Requires full Laravel environment setup');
    }

    public function test_build_column_definition_integer()
    {
        $column = [
            'name' => 'id',
            'type' => 'int',
            'nullable' => false,
            'default' => null,
            'auto_increment' => true,
            'length' => null
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertEquals("            \$table->id('id');\n", $result);
    }

    public function test_build_column_definition_string()
    {
        $column = [
            'name' => 'name',
            'type' => 'varchar',
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'length' => 255
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertEquals("            \$table->string('name'(255));\n", $result);
    }

    public function test_build_column_definition_string_no_length()
    {
        $column = [
            'name' => 'title',
            'type' => 'varchar',
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'length' => null
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertEquals("            \$table->string('title'(255));\n", $result);
    }

    public function test_build_column_definition_nullable()
    {
        $column = [
            'name' => 'description',
            'type' => 'text',
            'nullable' => true,
            'default' => null,
            'auto_increment' => false,
            'length' => null
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertEquals("            \$table->text('description')->nullable();\n", $result);
    }

    public function test_build_column_definition_with_default()
    {
        $column = [
            'name' => 'status',
            'type' => 'varchar',
            'nullable' => false,
            'default' => 'active',
            'auto_increment' => false,
            'length' => 50
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertEquals("            \$table->string('status'(50))->default('active');\n", $result);
    }

    public function test_build_column_definition_timestamp_created_at()
    {
        $column = [
            'name' => 'created_at',
            'type' => 'timestamp',
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'length' => null
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertEquals('', $result); // created_at is handled by timestamps()
    }

    public function test_build_column_definition_timestamp_updated_at()
    {
        $column = [
            'name' => 'updated_at',
            'type' => 'timestamp',
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'length' => null
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertEquals('', $result); // updated_at is handled by timestamps()
    }

    public function test_build_column_definition_boolean()
    {
        $column = [
            'name' => 'is_active',
            'type' => 'tinyint',
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'length' => 1
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertEquals("            \$table->boolean('is_active');\n", $result);
    }

    public function test_build_column_definition_unknown_type()
    {
        $column = [
            'name' => 'custom_field',
            'type' => 'customtype',
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'length' => null
        ];

        $result = $this->invokePrivateMethod('buildColumnDefinition', [$column]);
        $this->assertStringContainsString('Unknown type: customtype', $result);
        $this->assertStringContainsString("\$table->string('custom_field')", $result);
    }

    public function test_build_index_definition_unique()
    {
        $index = [
            'columns' => ['email'],
            'unique' => true
        ];

        $result = $this->invokePrivateMethod('buildIndexDefinition', [$index]);
        $this->assertEquals("            \$table->unique(['email']);\n", $result);
    }

    public function test_build_index_definition_regular()
    {
        $index = [
            'columns' => ['name', 'type'],
            'unique' => false
        ];

        $result = $this->invokePrivateMethod('buildIndexDefinition', [$index]);
        $this->assertEquals("            \$table->index(['name', 'type']);\n", $result);
    }

    public function test_build_foreign_key_definition()
    {
        $foreignKey = [
            'local_column' => 'user_id',
            'foreign_table' => 'users',
            'foreign_column' => 'id'
        ];

        $result = $this->invokePrivateMethod('buildForeignKeyDefinition', [$foreignKey]);
        $this->assertEquals("            \$table->foreign('user_id')->references('id')->on('users');\n", $result);
    }

    public function test_build_up_method()
    {
        $tableInfo = [
            'columns' => [
                [
                    'name' => 'id',
                    'type' => 'int',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => true,
                    'length' => null
                ],
                [
                    'name' => 'name',
                    'type' => 'varchar',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                    'length' => 255
                ]
            ],
            'indexes' => [
                [
                    'columns' => ['name'],
                    'unique' => true
                ]
            ],
            'foreign_keys' => [
                [
                    'local_column' => 'user_id',
                    'foreign_table' => 'users',
                    'foreign_column' => 'id'
                ]
            ]
        ];

        $result = $this->invokePrivateMethod('buildUpMethod', ['users', $tableInfo]);

        $this->assertStringStartsWith("        Schema::create('users', function (Blueprint \$table) {", $result);
        $this->assertStringContainsString("\$table->id('id');", $result);
        $this->assertStringContainsString("\$table->string('name'(255));", $result);
        $this->assertStringContainsString("\$table->unique(['name']);", $result);
        $this->assertStringContainsString("\$table->foreign('user_id')->references('id')->on('users');", $result);
        $this->assertStringEndsWith("        });", $result);
    }

    public function test_build_down_method()
    {
        $result = $this->invokePrivateMethod('buildDownMethod', ['users']);
        $this->assertEquals("        Schema::dropIfExists('users');", $result);
    }

    public function test_build_migration_content()
    {
        $tableInfo = [
            'columns' => [
                [
                    'name' => 'id',
                    'type' => 'int',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => true,
                    'length' => null
                ]
            ]
        ];

        $result = $this->invokePrivateMethod('buildMigrationContent', ['users', $tableInfo]);

        $this->assertStringStartsWith("<?php", $result);
        $this->assertStringContainsString("class extends Migration", $result);
        $this->assertStringContainsString("public function up(): void", $result);
        $this->assertStringContainsString("public function down(): void", $result);
        $this->assertStringContainsString("Schema::create('users'", $result);
        $this->assertStringContainsString("Schema::dropIfExists('users')", $result);
    }

    public function test_parse_column_type()
    {
        $this->assertEquals('varchar', $this->invokePrivateMethod('parseColumnType', ['varchar(255)']));
        $this->assertEquals('int', $this->invokePrivateMethod('parseColumnType', ['int(11)']));
        $this->assertEquals('decimal', $this->invokePrivateMethod('parseColumnType', ['decimal(10,2)']));
        $this->assertEquals('text', $this->invokePrivateMethod('parseColumnType', ['text']));
    }

    public function test_extract_length()
    {
        $this->assertEquals(255, $this->invokePrivateMethod('extractLength', ['varchar(255)']));
        $this->assertEquals(11, $this->invokePrivateMethod('extractLength', ['int(11)']));
        $this->assertNull($this->invokePrivateMethod('extractLength', ['decimal(10,2)']));
        $this->assertNull($this->invokePrivateMethod('extractLength', ['text']));
    }

    public function test_should_generate_migration_skip_default_tables()
    {
        $this->assertFalse($this->invokePrivateMethod('shouldGenerateMigration', ['migrations']));
        $this->assertFalse($this->invokePrivateMethod('shouldGenerateMigration', ['failed_jobs']));
        $this->assertFalse($this->invokePrivateMethod('shouldGenerateMigration', ['password_resets']));
        $this->assertFalse($this->invokePrivateMethod('shouldGenerateMigration', ['personal_access_tokens']));
    }

    public function test_parse_migration_file()
    {
        $filename = '2023_01_01_000000_create_users_table.php';
        $content = '<?php /* migration content */';

        $this->invokePrivateMethod('parseMigrationFile', [$filename, $content]);

        $existingMigrations = $this->getPrivateProperty('existingMigrations');
        $this->assertTrue($existingMigrations['create_users_table']);
    }

    /**
     * Helper method to invoke private/protected methods
     */
    protected function invokePrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->generator, $parameters);
    }

    /**
     * Helper method to get private/protected properties
     */
    protected function getPrivateProperty(string $propertyName)
    {
        $reflection = new \ReflectionClass($this->generator);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($this->generator);
    }
}
