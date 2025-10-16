<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;

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

    public function test_command_exists()
    {
        $this->assertFileExists(__DIR__ . '/../src/Commands/ModelSchemaCheckCommand.php');
    }

    public function test_commands_directory_exists()
    {
        $this->assertDirectoryExists(__DIR__ . '/../src/Commands');
    }
}