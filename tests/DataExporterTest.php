<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use Orchestra\Testbench\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\DataExporter;

class DataExporterTest extends TestCase
{
    protected DataExporter $exporter;
    protected string $testExportPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary directory for testing
        $this->testExportPath = sys_get_temp_dir() . '/msc_test_exports_' . uniqid();
        mkdir($this->testExportPath, 0755, true);

        $this->exporter = new DataExporter($this->testExportPath);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testExportPath)) {
            $files = glob($this->testExportPath . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->testExportPath);
        }

        parent::tearDown();
    }

    public function test_constructor_sets_export_path()
    {
        $newPath = sys_get_temp_dir() . '/msc_test_new_' . uniqid();
        $exporter = new DataExporter($newPath);

        $this->assertEquals($newPath, $exporter->getExportPath());
    }

    public function test_get_export_path()
    {
        $this->assertEquals($this->testExportPath, $this->exporter->getExportPath());
    }

    public function test_set_export_path()
    {
        $newPath = sys_get_temp_dir() . '/msc_test_set_' . uniqid();
        $result = $this->exporter->setExportPath($newPath);

        $this->assertInstanceOf(DataExporter::class, $result);
        $this->assertEquals($newPath, $this->exporter->getExportPath());
        $this->assertTrue(file_exists($newPath));

        rmdir($newPath);
    }

    public function test_get_excluded_tables()
    {
        $excluded = $this->exporter->getExcludedTables();
        $expected = [
            'migrations',
            'failed_jobs',
            'password_resets',
            'personal_access_tokens',
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
        ];

        $this->assertEquals($expected, $excluded);
    }

    public function test_set_excluded_tables()
    {
        $newExcluded = ['test_table', 'another_table'];
        $result = $this->exporter->setExcludedTables($newExcluded);

        $this->assertInstanceOf(DataExporter::class, $result);
        $this->assertEquals($newExcluded, $this->exporter->getExcludedTables());
    }

    public function test_generate_filename()
    {
        $filename = $this->invokePrivateMethod('generateFilename', ['sql', 'test_prefix']);

        $this->assertStringStartsWith('test_prefix_', $filename);
        $this->assertStringEndsWith('.sql', $filename);
        $this->assertMatchesRegularExpression('/test_prefix_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.sql/', $filename);
    }

    public function test_generate_filename_default_prefix()
    {
        $filename = $this->invokePrivateMethod('generateFilename', ['sql']);

        $this->assertStringStartsWith('database_export_', $filename);
        $this->assertStringEndsWith('.sql', $filename);
    }

    public function test_escape_value_null()
    {
        $result = $this->invokePrivateMethod('escapeValue', [null, ['type' => 'varchar']]);
        $this->assertEquals('NULL', $result);
    }

    public function test_escape_value_integer()
    {
        $result = $this->invokePrivateMethod('escapeValue', [123, ['type' => 'int']]);
        $this->assertEquals('123', $result);
    }

    public function test_escape_value_decimal()
    {
        $result = $this->invokePrivateMethod('escapeValue', [123.45, ['type' => 'decimal']]);
        $this->assertEquals('123.45', $result);
    }

    public function test_escape_value_datetime()
    {
        $datetime = '2023-01-01 12:00:00';
        $result = $this->invokePrivateMethod('escapeValue', [$datetime, ['type' => 'datetime']]);
        $this->assertEquals("'{$datetime}'", $result);
    }

    public function test_escape_value_string()
    {
        $string = "test's string";
        $result = $this->invokePrivateMethod('escapeValue', [$string, ['type' => 'varchar']]);
        $this->assertEquals("'test\\'s string'", $result);
    }

    public function test_parse_column_type()
    {
        $this->assertEquals('varchar', $this->invokePrivateMethod('parseColumnType', ['varchar(255)']));
        $this->assertEquals('int', $this->invokePrivateMethod('parseColumnType', ['int(11)']));
        $this->assertEquals('text', $this->invokePrivateMethod('parseColumnType', ['text']));
    }

    public function test_cleanup_old_exports()
    {
        // Create some test files
        $oldFile = $this->testExportPath . '/old_export.sql';
        $newFile = $this->testExportPath . '/new_export.sql';

        file_put_contents($oldFile, 'test');
        file_put_contents($newFile, 'test');

        // Set old file modification time to 60 days ago
        touch($oldFile, strtotime('-60 days'));
        touch($newFile, strtotime('-1 day'));

        $deletedCount = $this->exporter->cleanupOldExports(30);

        $this->assertEquals(1, $deletedCount);
        $this->assertFalse(file_exists($oldFile));
        $this->assertTrue(file_exists($newFile));
    }

    public function test_cleanup_old_exports_no_old_files()
    {
        // Create a new file
        $newFile = $this->testExportPath . '/new_export.sql';
        file_put_contents($newFile, 'test');
        touch($newFile, strtotime('-1 day'));

        $deletedCount = $this->exporter->cleanupOldExports(30);

        $this->assertEquals(0, $deletedCount);
        $this->assertTrue(file_exists($newFile));
    }

    /**
     * Helper method to invoke private/protected methods
     */
    protected function invokePrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->exporter, $parameters);
    }
}
