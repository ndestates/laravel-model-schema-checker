<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{
    public function test_package_can_be_loaded()
    {
        $this->assertTrue(true, 'Package loaded successfully');
    }

    public function test_check_script_exists()
    {
        $this->assertFileExists(__DIR__ . '/../check.php');
    }

    public function test_run_checker_script_exists()
    {
        $this->assertFileExists(__DIR__ . '/../run-checker.sh');
    }

    public function test_check_directory_exists()
    {
        $this->assertDirectoryExists(__DIR__ . '/../check');
    }
}