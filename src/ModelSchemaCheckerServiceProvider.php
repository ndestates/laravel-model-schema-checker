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
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add console commands here if needed
            ]);
        }
    }
}