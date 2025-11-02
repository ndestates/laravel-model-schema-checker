<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Commands\PublishAssetsCommand;

class PublishAssetsCommandTest extends TestCase
{
    public function test_command_can_be_instantiated()
    {
        $command = new PublishAssetsCommand();
        $this->assertInstanceOf(PublishAssetsCommand::class, $command);
    }

    public function test_command_has_correct_signature()
    {
        $command = new PublishAssetsCommand();
        $signature = $command->getSynopsis();
        $this->assertStringContainsString('model-schema-checker:publish-assets', $signature);
        $this->assertStringContainsString('--force', $signature);
    }

    public function test_command_has_correct_description()
    {
        $command = new PublishAssetsCommand();
        $description = $command->getDescription();
        $this->assertStringContainsString('Publish Laravel Model Schema Checker assets', $description);
    }
}