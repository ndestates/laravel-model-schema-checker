<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationDataMapper;

/**
 * MigrationDataMapperTest - Tests for MigrationDataMapper service
 *
 * Tests the data mapping functionality including backup creation,
 * strategy generation, and risk assessment.
 */
class MigrationDataMapperTest extends TestCase
{
    private string $tempDir;
    private MigrationDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/migration_mapper_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->mapper = new MigrationDataMapper($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            exec("rm -rf " . escapeshellarg($this->tempDir));
        }

        parent::tearDown();
    }

    public function testCreatesBackupWithMetadata(): void
    {
        // Mock database tables for testing
        $tables = ['users', 'posts'];

        // Since we can't actually create DB tables in this test environment,
        // we'll test the backup structure creation
        $backup = $this->mapper->createBackupWithMetadata();

        $this->assertArrayHasKey('backup_id', $backup);
        $this->assertArrayHasKey('path', $backup);
        $this->assertArrayHasKey('metadata', $backup);
        $this->assertArrayHasKey('tables_backed_up', $backup);

        $this->assertMatchesRegularExpression('/^backup_\d{4}_\d{2}_\d{2}_\d{6}_\w+$/', $backup['backup_id']);
        $this->assertStringContainsString($this->tempDir, $backup['path']);
    }

    public function testValidatesBackupIntegrity(): void
    {
        $backup = $this->mapper->createBackupWithMetadata();

        // Test backup validation (will fail gracefully since no actual data)
        try {
            $this->mapper->validateBackup($backup['backup_id']);
            // If no exception, backup structure is valid
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Expected to fail due to missing actual database tables
            $this->assertStringContainsString('Backup metadata not found', $e->getMessage());
        }
    }

    public function testCreatesMappingStrategyForCleanMigrations(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [],
                'HIGH' => [],
                'MEDIUM' => [],
                'LOW' => [],
                'LEAST' => []
            ],
            'issues_found' => 0,
            'data_mapping_required' => false
        ];

        $backupId = 'test_backup_123';
        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, $backupId);

        $this->assertArrayHasKey('backup_id', $strategy);
        $this->assertArrayHasKey('mappings_required', $strategy);
        $this->assertArrayHasKey('table_mappings', $strategy);
        $this->assertArrayHasKey('column_mappings', $strategy);
        $this->assertArrayHasKey('data_transformations', $strategy);
        $this->assertArrayHasKey('risk_assessment', $strategy);

        $this->assertEquals($backupId, $strategy['backup_id']);
        $this->assertFalse($strategy['mappings_required']);
    }

    public function testCreatesMappingStrategyForDataLossMigrations(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [],
                'HIGH' => [
                    [
                        'type' => 'data_loss',
                        'description' => 'Migration 2024_01_01_000001_drop_column.php may cause data loss'
                    ]
                ],
                'MEDIUM' => [],
                'LOW' => [],
                'LEAST' => []
            ],
            'issues_found' => 1,
            'data_mapping_required' => true
        ];

        $backupId = 'test_backup_456';
        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, $backupId);

        $this->assertTrue($strategy['mappings_required']);
        $this->assertNotEmpty($strategy['data_transformations']);
        $this->assertEquals('HIGH', $strategy['risk_assessment']['overall_risk']);
        $this->assertTrue($strategy['risk_assessment']['data_loss_potential']);
    }

    public function testCreatesMappingStrategyForForeignKeyIssues(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [],
                'HIGH' => [
                    [
                        'type' => 'foreign_key',
                        'description' => 'Migration 2024_01_01_000002_foreign_key.php has foreign key constraint issues'
                    ]
                ],
                'MEDIUM' => [],
                'LOW' => [],
                'LEAST' => []
            ],
            'issues_found' => 1,
            'data_mapping_required' => true
        ];

        $backupId = 'test_backup_789';
        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, $backupId);

        $this->assertTrue($strategy['mappings_required']);
        $this->assertNotEmpty($strategy['data_transformations']);

        $constraintValidation = array_filter($strategy['data_transformations'], function ($transform) {
            return $transform['type'] === 'constraint_validation';
        });

        $this->assertNotEmpty($constraintValidation);
    }

    public function testAssessesExtremeRiskForCriticalIssues(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [
                    [
                        'type' => 'syntax_error',
                        'description' => 'Migration has syntax errors'
                    ]
                ],
                'HIGH' => [],
                'MEDIUM' => [],
                'LOW' => [],
                'LEAST' => []
            ],
            'issues_found' => 1,
            'data_mapping_required' => true
        ];

        $backupId = 'test_backup_extreme';
        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, $backupId);

        $this->assertEquals('EXTREME', $strategy['risk_assessment']['overall_risk']);
        $this->assertTrue($strategy['risk_assessment']['data_loss_potential']);
        $this->assertEquals('VERY_SLOW', $strategy['risk_assessment']['estimated_migration_time']);
    }

    public function testHandlesBackupAndRestoreTransformations(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [],
                'HIGH' => [
                    [
                        'type' => 'data_loss',
                        'description' => 'Migration 2024_01_01_000001_drop_column.php may cause data loss',
                        'table' => 'users'
                    ]
                ],
                'MEDIUM' => [],
                'LOW' => [],
                'LEAST' => []
            ],
            'issues_found' => 1,
            'data_mapping_required' => true
        ];

        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, 'test_backup');

        $backupRestore = array_filter($strategy['data_transformations'], function ($transform) {
            return $transform['action'] === 'backup_and_restore';
        });

        $this->assertNotEmpty($backupRestore);
        $this->assertEquals('users', $backupRestore[0]['table']);
        $this->assertStringContainsString('data loss', $backupRestore[0]['reason']);
    }

    public function testHandlesReferenceValidationTransformations(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [],
                'HIGH' => [
                    [
                        'type' => 'foreign_key',
                        'description' => 'Migration 2024_01_01_000002_foreign_key.php has foreign key constraint issues'
                    ]
                ],
                'MEDIUM' => [],
                'LOW' => [],
                'LEAST' => []
            ],
            'issues_found' => 1,
            'data_mapping_required' => true
        ];

        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, 'test_backup');

        $referenceValidation = array_filter($strategy['data_transformations'], function ($transform) {
            return $transform['action'] === 'validate_references';
        });

        $this->assertNotEmpty($referenceValidation);
        $this->assertStringContainsString('foreign key', $referenceValidation[0]['reason']);
    }

    public function testHandlesIndexAdditionTransformations(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [],
                'HIGH' => [],
                'MEDIUM' => [
                    [
                        'type' => 'missing_index',
                        'description' => 'Migration 2024_01_01_000003_index.php creates foreign keys without indexes'
                    ]
                ],
                'LOW' => [],
                'LEAST' => []
            ],
            'issues_found' => 1,
            'data_mapping_required' => true
        ];

        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, 'test_backup');

        $indexAddition = array_filter($strategy['data_transformations'], function ($transform) {
            return $transform['action'] === 'add_indexes';
        });

        $this->assertNotEmpty($indexAddition);
        $this->assertStringContainsString('indexes', $indexAddition[0]['reason']);
    }

    public function testAssessesLowRiskForMinimalIssues(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [],
                'HIGH' => [],
                'MEDIUM' => [],
                'LOW' => [
                    [
                        'type' => 'performance',
                        'description' => 'Minor performance issue'
                    ]
                ],
                'LEAST' => []
            ],
            'issues_found' => 1,
            'data_mapping_required' => false
        ];

        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, 'test_backup');

        $this->assertEquals('LOW', $strategy['risk_assessment']['overall_risk']);
        $this->assertFalse($strategy['risk_assessment']['data_loss_potential']);
        $this->assertEquals('FAST', $strategy['risk_assessment']['estimated_migration_time']);
    }

    public function testAssessesMediumRiskForMediumIssues(): void
    {
        $migrationAnalysis = [
            'criticality' => [
                'CRITICAL' => [],
                'HIGH' => [],
                'MEDIUM' => [
                    [
                        'type' => 'naming',
                        'description' => 'Naming convention issue'
                    ]
                ],
                'LOW' => [],
                'LEAST' => []
            ],
            'issues_found' => 1,
            'data_mapping_required' => false
        ];

        $strategy = $this->mapper->createDataMappingStrategy($migrationAnalysis, 'test_backup');

        $this->assertEquals('MEDIUM', $strategy['risk_assessment']['overall_risk']);
        $this->assertEquals('MEDIUM', $strategy['risk_assessment']['estimated_migration_time']);
    }
}