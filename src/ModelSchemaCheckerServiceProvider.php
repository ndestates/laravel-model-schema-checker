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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
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