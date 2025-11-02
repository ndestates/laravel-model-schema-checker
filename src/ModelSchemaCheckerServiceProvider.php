<?php

namespace NDEstates\LaravelModelSchemaChecker;

use Illuminate\Support\ServiceProvider;
use NDEstates\LaravelModelSchemaChecker\Commands\ModelSchemaCheckCommand;
use NDEstates\LaravelModelSchemaChecker\Commands\PublishAssetsCommand;
use NDEstates\LaravelModelSchemaChecker\Commands\MigrateForgivingCommand;

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
        // Only load in non-production environments
        if ($this->app->environment('production')) {
            return;
        }

        // Register CheckerManager in boot() when all services are available
        $this->app->singleton(
            \NDEstates\LaravelModelSchemaChecker\Services\CheckerManager::class,
            function ($app) {
                return new \NDEstates\LaravelModelSchemaChecker\Services\CheckerManager(
                    config('model-schema-checker', [])
                );
            }
        );

        // Load web routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../config/model-schema-checker.php' => config_path('model-schema-checker.php'),
        ], 'config');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/model-schema-checker'),
        ], 'views');

        // Publish built assets
        $this->publishes([
            __DIR__ . '/../dist' => public_path('vendor/model-schema-checker'),
        ], 'model-schema-checker-assets');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'model-schema-checker');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModelSchemaCheckCommand::class,
                PublishAssetsCommand::class,
                MigrateForgivingCommand::class,
            ]);
        }
    }
}
