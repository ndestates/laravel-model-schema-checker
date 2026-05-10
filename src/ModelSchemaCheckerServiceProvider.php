<?php

namespace NDEstates\LaravelModelSchemaChecker;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        if ($this->isProductionEnvironment()) {
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

        // Auto-publish configuration file if it doesn't exist
        $configPath = config_path('model-schema-checker.php');
        if (!file_exists($configPath)) {
            $this->publishes([
                __DIR__ . '/../config/model-schema-checker.php' => $configPath,
            ], 'config');
            // Force publish the config immediately
            $this->publishConfig();
        } else {
            // Still allow manual re-publishing
            $this->publishes([
                __DIR__ . '/../config/model-schema-checker.php' => $configPath,
            ], 'config');
        }

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/model-schema-checker'),
        ], 'views');

        // Publish built assets
        $this->publishes([
            __DIR__ . '/../dist' => public_path('vendor/model-schema-checker'),
        ], 'model-schema-checker-assets');

        // Auto-publish assets if they don't exist
        $assetsPath = public_path('vendor/model-schema-checker/css/app.css');
        if (!file_exists($assetsPath)) {
            $this->publishAssets();
        }

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

    /**
     * Force publish the configuration file
     */
    protected function publishConfig(): void
    {
        $from = __DIR__ . '/../config/model-schema-checker.php';
        $to = config_path('model-schema-checker.php');

        if (!$this->isSafePackagePath($from) || !$this->isPathWithinRoots($to, [config_path()])) {
            return;
        }

        if (!$this->ensureDirectoryExists(dirname($to), [config_path()])) {
            return;
        }

        copy($from, $to);
    }

    /**
     * Force publish the assets
     */
    protected function publishAssets(): void
    {
        $from = __DIR__ . '/../dist';
        $to = public_path('vendor/model-schema-checker');

        if (!$this->isSafePackagePath($from) || !$this->ensureDirectoryExists($to, [public_path()])) {
            return;
        }

        $this->copyDirectory($from, $to);
    }

    /**
     * Copy directory recursively
     */
    protected function copyDirectory(string $from, string $to): void
    {
        if (!$this->isSafePackagePath($from) || !$this->ensureDirectoryExists($to, [public_path(), resource_path(), config_path()])) {
            throw new \RuntimeException('Refusing to copy files outside allowed package paths.');
        }

        $files = scandir($from);

        if ($files === false) {
            throw new \RuntimeException('Unable to read source directory for publishing.');
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $source = $from . '/' . $file;
            $destination = $to . '/' . $file;

            if (is_link($source)) {
                continue;
            }

            if (is_dir($source)) {
                if (!$this->ensureDirectoryExists($destination, [public_path(), resource_path(), config_path()])) {
                    throw new \RuntimeException('Refusing to create directory outside allowed publish roots.');
                }
                $this->copyDirectory($source, $destination);
            } else {
                if (!$this->isPathWithinRoots($destination, [public_path(), resource_path(), config_path()])) {
                    throw new \RuntimeException('Refusing to copy file outside allowed publish roots.');
                }
                copy($source, $destination);
            }
        }
    }

    protected function isProductionEnvironment(): bool
    {
        if (isset($_SERVER['DDEV_PROJECT']) || isset($_SERVER['DDEV_HOSTNAME']) || getenv('DDEV_PROJECT') || getenv('IS_DDEV_PROJECT')) {
            return false;
        }

        $env = strtolower((string) $this->app->environment());
        if (in_array($env, ['production', 'prod', 'live'], true)) {
            return true;
        }

        $appEnv = strtolower((string) ($_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?? ''));
        if (in_array($appEnv, ['production', 'prod', 'live'], true)) {
            return true;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }

        $looksLocal = Str::contains($host, ['localhost', '127.0.0.1', '.local', '.dev', '.test', '.ddev.site', 'ddev']);
        $looksProduction = Str::contains($host, ['.com', '.org', '.net']) && !Str::contains($host, ['dev', 'staging', 'test', 'demo']);

        return !$looksLocal && $looksProduction;
    }

    protected function isSafePackagePath(string $path): bool
    {
        return $this->isPathWithinRoots($path, [$this->getPackageRoot()]);
    }

    protected function isPathWithinRoots(string $path, array $roots): bool
    {
        $normalizedPath = $this->normalizePath($path);
        if ($normalizedPath === null) {
            return false;
        }

        foreach ($roots as $root) {
            $normalizedRoot = $this->normalizePath($root);
            if ($normalizedRoot === null) {
                continue;
            }

            if ($normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    protected function ensureDirectoryExists(string $directory, array $allowedRoots): bool
    {
        if (!$this->isPathWithinRoots($directory, $allowedRoots)) {
            return false;
        }

        if (is_dir($directory)) {
            return true;
        }

        return mkdir($directory, 0755, true) || is_dir($directory);
    }

    protected function normalizePath(string $path): ?string
    {
        $resolved = realpath($path);
        if ($resolved !== false) {
            return rtrim($resolved, DIRECTORY_SEPARATOR);
        }

        $parent = dirname($path);
        $resolvedParent = realpath($parent);
        if ($resolvedParent === false) {
            return null;
        }

        return rtrim($resolvedParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
    }

    protected function getPackageRoot(): string
    {
        return realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    }
}
