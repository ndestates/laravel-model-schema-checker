<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use Orchestra\Testbench\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationCleanup;
use Carbon\Carbon;

class MigrationCleanupTest extends TestCase
{
    protected MigrationCleanup $cleanup;
    protected string $testMigrationsPath;
    protected string $testBackupPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests in this class as they require full Laravel environment setup
        $this->markTestSkipped('Requires full Laravel environment setup');
    }

    protected function tearDown(): void
    {
        // Clean up test files and directories
        $this->cleanupTestDirectory($this->testMigrationsPath);
        $this->cleanupTestDirectory($this->testBackupPath);

        parent::tearDown();
    }

    protected function cleanupTestDirectory(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $files = glob($path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->cleanupTestDirectory($file);
                rmdir($file);
            }
        }
        rmdir($path);
    }

    public function test_constructor_sets_paths()
    {
        $cleanup = new MigrationCleanup();
        $this->assertIsString($cleanup->getMigrationsPath());
        $this->assertIsString($cleanup->getBackupPath());
    }

    public function test_get_migrations_path()
    {
        $this->assertEquals($this->testMigrationsPath, $this->cleanup->getMigrationsPath());
    }

    public function test_set_migrations_path()
    {
        $newPath = sys_get_temp_dir() . '/msc_test_new_' . uniqid();
        $result = $this->cleanup->setMigrationsPath($newPath);

        $this->assertInstanceOf(MigrationCleanup::class, $result);
        $this->assertEquals($newPath, $this->cleanup->getMigrationsPath());
    }

    public function test_get_backup_path()
    {
        $this->assertEquals($this->testBackupPath, $this->cleanup->getBackupPath());
    }

    public function test_set_backup_path()
    {
        $newPath = sys_get_temp_dir() . '/msc_test_backup_' . uniqid();
        $result = $this->cleanup->setBackupPath($newPath);

        $this->assertInstanceOf(MigrationCleanup::class, $result);
        $this->assertEquals($newPath, $this->cleanup->getBackupPath());
        $this->assertTrue(file_exists($newPath));

        rmdir($newPath);
    }

    public function test_get_protected_migrations()
    {
        $protected = $this->cleanup->getProtectedMigrations();
        $this->assertIsArray($protected);
        $this->assertContains('2014_10_12_000000_create_users_table.php', $protected);
    }

    public function test_set_protected_migrations()
    {
        $newProtected = ['custom_migration.php'];
        $result = $this->cleanup->setProtectedMigrations($newProtected);

        $this->assertInstanceOf(MigrationCleanup::class, $result);
        $this->assertEquals($newProtected, $this->cleanup->getProtectedMigrations());
    }

    public function test_migration_has_been_run_invalid_filename()
    {
        $result = $this->invokePrivateMethod('migrationHasBeenRun', ['invalid_filename.php']);
        $this->assertFalse($result);
    }

    public function test_is_orphaned_migration()
    {
        // This method always returns false in the current implementation
        $result = $this->invokePrivateMethod('isOrphanedMigration', ['any_filename.php']);
        $this->assertFalse($result);
    }

    public function test_calculate_total_size()
    {
        // Create mock file objects
        $mockFile1 = $this->createMock(\SplFileInfo::class);
        $mockFile1->method('getSize')->willReturn(1000);

        $mockFile2 = $this->createMock(\SplFileInfo::class);
        $mockFile2->method('getSize')->willReturn(2000);

        $files = [$mockFile1, $mockFile2];
        $result = $this->invokePrivateMethod('calculateTotalSize', [$files]);

        $this->assertEquals(3000, $result);
    }

    public function test_get_cleanup_preview_no_files()
    {
        $preview = $this->cleanup->getCleanupPreview();
        $this->assertEquals(0, $preview['total_migration_files']);
        $this->assertEquals(0, $preview['files_to_delete']);
        $this->assertEquals(0, $preview['total_size_to_save']);
        $this->assertIsArray($preview['files']);
    }

    public function test_cleanup_old_backups_no_backups()
    {
        $deletedCount = $this->cleanup->cleanupOldBackups(30);
        $this->assertEquals(0, $deletedCount);
    }

    public function test_get_available_backups_no_backups()
    {
        $backups = $this->cleanup->getAvailableBackups();
        $this->assertIsArray($backups);
        $this->assertEmpty($backups);
    }

    public function test_restore_from_backup_nonexistent()
    {
        $result = $this->cleanup->restoreFromBackup('nonexistent_backup');
        $this->assertEquals(0, $result['files_restored']);
        $this->assertCount(1, $result['errors']);
    }

    /**
     * Helper method to invoke private/protected methods
     */
    protected function invokePrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($this->cleanup);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->cleanup, $parameters);
    }
}
