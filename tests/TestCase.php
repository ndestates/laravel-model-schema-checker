<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use NDEstates\LaravelModelSchemaChecker\ModelSchemaCheckerServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ModelSchemaCheckerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup test environment
        $app['config']->set('app.key', str_repeat('a', 32));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}