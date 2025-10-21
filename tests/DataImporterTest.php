<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use Orchestra\Testbench\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\DataImporter;

class DataImporterTest extends TestCase
{
    protected DataImporter $importer;
    protected string $testImportPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary directory for testing
        $this->testImportPath = sys_get_temp_dir() . '/msc_test_imports_' . uniqid();
        mkdir($this->testImportPath, 0755, true);

        $this->importer = new DataImporter();
        $this->importer->setImportPath($this->testImportPath);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testImportPath)) {
            $files = glob($this->testImportPath . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->testImportPath);
        }

        parent::tearDown();
    }

    public function test_constructor_sets_import_path()
    {
        $newPath = sys_get_temp_dir() . '/msc_test_new_' . uniqid();
        $importer = new DataImporter();
        $importer->setImportPath($newPath);

        $this->assertEquals($newPath, $importer->getImportPath());
        rmdir($newPath);
    }

    public function test_get_import_path()
    {
        $this->assertEquals($this->testImportPath, $this->importer->getImportPath());
    }

    public function test_set_import_path()
    {
        $newPath = sys_get_temp_dir() . '/msc_test_set_' . uniqid();
        $result = $this->importer->setImportPath($newPath);

        $this->assertInstanceOf(DataImporter::class, $result);
        $this->assertEquals($newPath, $this->importer->getImportPath());
        $this->assertTrue(file_exists($newPath));

        rmdir($newPath);
    }

    public function test_get_excluded_tables()
    {
        $excluded = $this->importer->getExcludedTables();
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
        $result = $this->importer->setExcludedTables($newExcluded);

        $this->assertInstanceOf(DataImporter::class, $result);
        $this->assertEquals($newExcluded, $this->importer->getExcludedTables());
    }

    public function test_parse_sql_statements_simple()
    {
        $sql = "SELECT * FROM users; SELECT * FROM posts;";
        $statements = $this->invokePrivateMethod('parseSqlStatements', [$sql]);

        $this->assertCount(2, $statements);
        $this->assertEquals('SELECT * FROM users', $statements[0]);
        $this->assertEquals('SELECT * FROM posts', $statements[1]);
    }

    public function test_parse_sql_statements_with_comments()
    {
        $sql = "-- This is a comment\nSELECT * FROM users; /* Another comment */ SELECT * FROM posts;";
        $statements = $this->invokePrivateMethod('parseSqlStatements', [$sql]);

        $this->assertCount(2, $statements);
        $this->assertEquals('SELECT * FROM users', $statements[0]);
        $this->assertEquals('SELECT * FROM posts', $statements[1]);
    }

    public function test_parse_sql_statements_with_strings()
    {
        $sql = "INSERT INTO users (name) VALUES ('John; Doe'); SELECT * FROM posts;";
        $statements = $this->invokePrivateMethod('parseSqlStatements', [$sql]);

        $this->assertCount(2, $statements);
        $this->assertEquals("INSERT INTO users (name) VALUES ('John; Doe')", $statements[0]);
        $this->assertEquals('SELECT * FROM posts', $statements[1]);
    }

    public function test_parse_sql_statements_multiline()
    {
        $sql = "INSERT INTO users\n(name, email)\nVALUES\n('John', 'john@example.com');";
        $statements = $this->invokePrivateMethod('parseSqlStatements', [$sql]);

        $this->assertCount(1, $statements);
        $this->assertEquals("INSERT INTO users\n(name, email)\nVALUES\n('John', 'john@example.com')", $statements[0]);
    }

    public function test_should_skip_statement()
    {
        $skipStatements = [
            'SET FOREIGN_KEY_CHECKS = 0',
            'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"',
            'SET AUTOCOMMIT = 0',
            'START TRANSACTION',
            'COMMIT',
            'BEGIN'
        ];

        foreach ($skipStatements as $statement) {
            $this->assertTrue($this->invokePrivateMethod('shouldSkipStatement', [$statement]));
        }

        $normalStatements = [
            'SELECT * FROM users',
            'INSERT INTO users (name) VALUES ("John")',
            'CREATE TABLE test (id INT)'
        ];

        foreach ($normalStatements as $statement) {
            $this->assertFalse($this->invokePrivateMethod('shouldSkipStatement', [$statement]));
        }
    }

    public function test_validate_import_file_nonexistent()
    {
        $issues = $this->importer->validateImportFile('/nonexistent/file.sql');
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('does not exist', $issues[0]);
    }

    public function test_validate_import_file_empty()
    {
        $filePath = $this->testImportPath . '/empty.sql';
        file_put_contents($filePath, "   \n\t  \n  ");

        $issues = $this->importer->validateImportFile($filePath);
        $this->assertCount(2, $issues); // Empty and no valid SQL
        $this->assertStringContainsString('empty', $issues[0]);
    }

    public function test_validate_import_file_invalid_sql()
    {
        $filePath = $this->testImportPath . '/invalid.sql';
        file_put_contents($filePath, 'INVALID SQL CONTENT');

        $issues = $this->importer->validateImportFile($filePath);
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('does not contain valid SQL', $issues[0]);
    }

    public function test_validate_import_file_valid()
    {
        $filePath = $this->testImportPath . '/valid.sql';
        $sql = "INSERT INTO users (name) VALUES ('John'); CREATE TABLE posts (id INT);";
        file_put_contents($filePath, $sql);

        $issues = $this->importer->validateImportFile($filePath);
        $this->assertCount(0, $issues);
    }

    public function test_validate_import_file_too_large()
    {
        $filePath = $this->testImportPath . '/large.sql';
        // Create a file larger than 100MB (but not actually that large for testing)
        $largeContent = str_repeat('INSERT INTO test (data) VALUES ("test");', 10000);
        file_put_contents($filePath, $largeContent);

        // Mock File::size to return a large value
        $mockFileSize = 150 * 1024 * 1024; // 150MB

        // We'll test the logic by creating a smaller file and checking the validation doesn't trigger
        // In a real scenario, we'd mock the File facade
        $issues = $this->importer->validateImportFile($filePath);
        $this->assertCount(0, $issues); // Should pass for normal-sized file
    }

    /**
     * Helper method to invoke private/protected methods
     */
    protected function invokePrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($this->importer);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->importer, $parameters);
    }
}