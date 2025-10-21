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

class ModelSchemaCheckCommandTest extends TestCase
{
    private ModelSchemaCheckCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_command_can_be_instantiated()
    {
        $this->assertInstanceOf(ModelSchemaCheckCommand::class, $this->command);
    }

    public function test_command_has_correct_signature()
    {
        $signature = $this->command->getSynopsis();
        $this->assertStringContainsString('--fix-migrations', $signature);
        $this->assertStringContainsString('--rollback-migrations', $signature);
        $this->assertStringContainsString('--backup-db', $signature);
        $this->assertStringContainsString('--dry-run', $signature);
    }

    public function test_command_has_correct_name()
    {
        $this->assertEquals('model:schema-check', $this->command->getName());
    }

    public function test_command_has_correct_description()
    {
        $description = $this->command->getDescription();
        $this->assertStringContainsString('Comprehensive Laravel application validation', $description);
    }

    public function test_command_definition_includes_key_options()
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('backup-db'));
        $this->assertTrue($definition->hasOption('fix-migrations'));
        $this->assertTrue($definition->hasOption('rollback-migrations'));
    }

    public function test_command_can_be_configured_with_different_services()
    {
        $customCheckerManager = $this->createMock(CheckerManager::class);
        $customIssueManager = $this->createMock(IssueManager::class);
        $customMigrationGenerator = $this->createMock(MigrationGenerator::class);
        $customDataExporter = $this->createMock(DataExporter::class);
        $customDataImporter = $this->createMock(DataImporter::class);
        $customMigrationCleanup = $this->createMock(MigrationCleanup::class);

        $customCommand = new ModelSchemaCheckCommand(
            $customCheckerManager,
            $customIssueManager,
            $customMigrationGenerator,
            $customDataExporter,
            $customDataImporter,
            $customMigrationCleanup
        );

        $this->assertInstanceOf(ModelSchemaCheckCommand::class, $customCommand);
        $this->assertNotSame($this->command, $customCommand);
    }
}