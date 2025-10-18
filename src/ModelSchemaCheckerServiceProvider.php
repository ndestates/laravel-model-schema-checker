<?php

namespace NDEstates\LaravelModelSchemaChecker;

use Illuminate\Support\ServiceProvider;
use NDEstates\LaravelModelSchemaChecker\Commands\ModelSchemaCheckCommand;

class ModelSchemaCheckerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/model-schema-checker.php',
            'model-schema-checker'
        );

        // Register services - IssueManager can be registered here as it's simple
        $this->app->singleton(
            \NDEstates\LaravelModelSchemaChecker\Services\IssueManager::class
        );

        // Register MigrationGenerator service
        $this->app->singleton(
            \NDEstates\LaravelModelSchemaChecker\Services\MigrationGenerator::class
        );

        // Register DataExporter service
        $this->app->singleton(
            \NDEstates\LaravelModelSchemaChecker\Services\DataExporter::class
        );

        // Register DataImporter service
        $this->app->singleton(
            \NDEstates\LaravelModelSchemaChecker\Services\DataImporter::class
        );

        // Register MigrationCleanup service
        $this->app->singleton(
            \NDEstates\LaravelModelSchemaChecker\Services\MigrationCleanup::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register CheckerManager in boot() when all services are available
        $this->app->singleton(
            \NDEstates\LaravelModelSchemaChecker\Services\CheckerManager::class,
            function ($app) {
                return new \NDEstates\LaravelModelSchemaChecker\Services\CheckerManager(
                    config('model-schema-checker', [])
                );
            }
        );

        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../config/model-schema-checker.php' => config_path('model-schema-checker.php'),
        ], 'config');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModelSchemaCheckCommand::class,
            ]);
        }
    }
}