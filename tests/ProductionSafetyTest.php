<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\ModelSchemaCheckerServiceProvider;
use NDEstates\LaravelModelSchemaChecker\Commands\ModelSchemaCheckCommand;
use NDEstates\LaravelModelSchemaChecker\Commands\PublishAssetsCommand;

class ProductionSafetyTest extends TestCase
{
    public function test_commands_have_production_environment_checks()
    {
        // Test that the main command has the isProductionEnvironment method
        $this->assertTrue(method_exists(ModelSchemaCheckCommand::class, 'isProductionEnvironment'));
        $this->assertTrue(method_exists(ModelSchemaCheckCommand::class, 'handle'));
    }

    public function test_publish_assets_command_has_production_environment_checks()
    {
        $this->assertTrue(method_exists(PublishAssetsCommand::class, 'isProductionEnvironment'));
        $this->assertTrue(method_exists(PublishAssetsCommand::class, 'handle'));
    }

    public function test_controller_has_production_environment_checks()
    {
        // Test that the controller class exists and has the method
        $this->assertTrue(class_exists(\NDEstates\LaravelModelSchemaChecker\Http\Controllers\ModelSchemaCheckerController::class));
        $this->assertTrue(method_exists(\NDEstates\LaravelModelSchemaChecker\Http\Controllers\ModelSchemaCheckerController::class, 'isProductionEnvironment'));
    }

    public function test_production_environment_detection_method_exists()
    {
        // Test that all classes have the production detection method
        $classes = [
            ModelSchemaCheckCommand::class,
            PublishAssetsCommand::class,
            \NDEstates\LaravelModelSchemaChecker\Http\Controllers\ModelSchemaCheckerController::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(method_exists($class, 'isProductionEnvironment'), "Class {$class} should have isProductionEnvironment method");
        }
    }

    public function test_service_provider_has_production_check_in_boot()
    {
        // Test that the service provider's boot method contains production check
        $provider = new ModelSchemaCheckerServiceProvider(app());
        $reflection = new \ReflectionMethod($provider, 'boot');
        $methodBody = file_get_contents($reflection->getFileName());

        $this->assertStringContainsString('environment(\'production\')', $methodBody);
        $this->assertStringContainsString('if ($this->app->environment', $methodBody);
    }

    public function test_commands_contain_production_error_messages()
    {
        // Test that commands contain production safety error messages
        $commandClasses = [ModelSchemaCheckCommand::class, PublishAssetsCommand::class];

        foreach ($commandClasses as $class) {
            $reflection = new \ReflectionClass($class);
            $handleMethod = $reflection->getMethod('handle');
            $methodBody = file_get_contents($handleMethod->getFileName());

            $this->assertStringContainsString('SECURITY ERROR', $methodBody);
            $this->assertStringContainsString('disabled in production', $methodBody);
        }
    }

    public function test_controller_constructor_has_production_check()
    {
        // Test that the controller constructor contains production check
        $controllerClass = \NDEstates\LaravelModelSchemaChecker\Http\Controllers\ModelSchemaCheckerController::class;
        $reflection = new \ReflectionClass($controllerClass);
        $constructor = $reflection->getMethod('__construct');
        $methodBody = file_get_contents($constructor->getFileName());

        $this->assertStringContainsString('isProductionEnvironment', $methodBody);
        $this->assertStringContainsString('abort(403', $methodBody);
    }
}