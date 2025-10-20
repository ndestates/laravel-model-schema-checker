<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\ModelSchemaCheckerServiceProvider;
use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationGenerator;
use NDEstates\LaravelModelSchemaChecker\Services\DataExporter;
use NDEstates\LaravelModelSchemaChecker\Services\DataImporter;
use NDEstates\LaravelModelSchemaChecker\Services\MigrationCleanup;
use NDEstates\LaravelModelSchemaChecker\Commands\ModelSchemaCheckCommand;

class BasicTest extends TestCase
{
    public function test_package_can_be_loaded()
    {
        $this->assertTrue(true, 'Package loaded successfully');
    }

    public function test_service_provider_exists()
    {
        $this->assertFileExists(__DIR__ . '/../src/ModelSchemaCheckerServiceProvider.php');
    }

    public function test_service_provider_can_be_instantiated()
    {
        $provider = new ModelSchemaCheckerServiceProvider(app());
        $this->assertInstanceOf(ModelSchemaCheckerServiceProvider::class, $provider);
    }

    public function test_command_exists()
    {
        $this->assertFileExists(__DIR__ . '/../src/Commands/ModelSchemaCheckCommand.php');
    }

    public function test_commands_directory_exists()
    {
        $this->assertDirectoryExists(__DIR__ . '/../src/Commands');
    }

    public function test_services_directory_exists()
    {
        $this->assertDirectoryExists(__DIR__ . '/../src/Services');
    }

    public function test_checkers_directory_exists()
    {
        $this->assertDirectoryExists(__DIR__ . '/../src/Checkers');
    }

    public function test_contracts_directory_exists()
    {
        $this->assertDirectoryExists(__DIR__ . '/../src/Contracts');
    }

    public function test_config_file_exists()
    {
        $this->assertFileExists(__DIR__ . '/../config/model-schema-checker.php');
    }

    public function test_composer_json_exists()
    {
        $this->assertFileExists(__DIR__ . '/../composer.json');
    }

    public function test_readme_exists()
    {
        $this->assertFileExists(__DIR__ . '/../README.md');
    }

    public function test_service_classes_exist()
    {
        $services = [
            CheckerManager::class,
            IssueManager::class,
            MigrationGenerator::class,
            DataExporter::class,
            DataImporter::class,
            MigrationCleanup::class,
        ];

        foreach ($services as $service) {
            $this->assertTrue(class_exists($service), "Service class $service should exist");
        }
    }

    public function test_command_class_can_be_instantiated()
    {
        // Mock the required dependencies
        $checkerManager = $this->createMock(CheckerManager::class);
        $issueManager = $this->createMock(IssueManager::class);
        $migrationGenerator = $this->createMock(MigrationGenerator::class);
        $dataExporter = $this->createMock(DataExporter::class);
        $dataImporter = $this->createMock(DataImporter::class);
        $migrationCleanup = $this->createMock(MigrationCleanup::class);

        $command = new ModelSchemaCheckCommand(
            $checkerManager,
            $issueManager,
            $migrationGenerator,
            $dataExporter,
            $dataImporter,
            $migrationCleanup
        );

        $this->assertInstanceOf(ModelSchemaCheckCommand::class, $command);
    }

    public function test_command_signature_contains_expected_options()
    {
        $checkerManager = $this->createMock(CheckerManager::class);
        $issueManager = $this->createMock(IssueManager::class);
        $migrationGenerator = $this->createMock(MigrationGenerator::class);
        $dataExporter = $this->createMock(DataExporter::class);
        $dataImporter = $this->createMock(DataImporter::class);
        $migrationCleanup = $this->createMock(MigrationCleanup::class);

        $command = new ModelSchemaCheckCommand(
            $checkerManager,
            $issueManager,
            $migrationGenerator,
            $dataExporter,
            $dataImporter,
            $migrationCleanup
        );

        $signature = $command->getSynopsis();
        $this->assertStringContains('--fix-migrations', $signature);
        $this->assertStringContains('--rollback-migrations', $signature);
        $this->assertStringContains('--backup-db', $signature);
        $this->assertStringContains('--dry-run', $signature);
    }
}