<?php

namespace NDEstates\LaravelModelSchemaChecker;

use Illuminate\Support\ServiceProvider;

class ModelSchemaCheckerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register any package services here
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add console commands here if needed
            ]);
        }
    }
}