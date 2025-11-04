<?php

namespace NDEstates\LaravelModelSchemaChecker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PublishAssetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model-schema-checker:publish-assets
                            {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Laravel Model Schema Checker assets (CSS/JS) to the public directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // PRODUCTION SAFETY: This tool is designed for development only
        if ($this->isProductionEnvironment()) {
            $this->error('🚫 SECURITY ERROR: Laravel Model Schema Checker is disabled in production environments.');
            $this->error('');
            $this->error('This tool is designed exclusively for development and testing environments.');
            $this->error('Asset publishing should only be done during development setup.');
            $this->error('');
            $this->error('If you believe this is an error, please check your APP_ENV setting.');
            return 1;
        }

        $this->info('Publishing Laravel Model Schema Checker assets...');

        // Publish assets
        $exitCode = Artisan::call('vendor:publish', [
            '--tag' => 'model-schema-checker-assets',
            '--force' => $this->option('force'),
        ]);

        if ($exitCode === 0) {
            $this->info('✅ Assets published successfully!');
            $this->info('📁 Assets are now available at: public/vendor/model-schema-checker/');
            $this->info('🎨 The web interface should now display properly.');
        } else {
            $this->error('❌ Failed to publish assets.');
            return 1;
        }

        return 0;
    }

    /**
     * Check if we're running in a production environment
     * Multiple layers of protection to prevent reverse engineering
     */
    protected function isProductionEnvironment(): bool
    {
        $env = app()->environment();

        // Primary check: standard Laravel environments
        if (in_array(strtolower($env), ['production', 'prod', 'live'])) {
            return true;
        }

        // Secondary check: environment variables that might indicate production
        if (env('APP_ENV') === 'production' || env('APP_ENV') === 'prod' || env('APP_ENV') === 'live') {
            return true;
        }

        // Tertiary check: server environment variables
        if (isset($_SERVER['APP_ENV']) && in_array(strtolower($_SERVER['APP_ENV']), ['production', 'prod', 'live'])) {
            return true;
        }

        // Quaternary check: check for production-like hostnames
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = strtolower($_SERVER['HTTP_HOST']);
            // Common production patterns
            if (strpos($host, '.com') !== false ||
                strpos($host, '.org') !== false ||
                strpos($host, '.net') !== false ||
                !preg_match('/\b(localhost|127\.0\.0\.1|\.local|\.dev|\.test)\b/', $host)) {
                // Additional check: if not clearly development, be conservative
                if (!preg_match('/\b(dev|staging|test|demo)\b/', $host)) {
                    // This is a heuristic - in production deployments, be extra cautious
                    return true;
                }
            }
        }

        return false;
    }
}