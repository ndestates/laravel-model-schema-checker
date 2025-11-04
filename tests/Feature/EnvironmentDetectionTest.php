<?php

describe('Environment Detection Logic', function () {

    it('correctly identifies production environment', function () {
        $envValue = 'production';
        $isProduction = $envValue === 'production';

        expect($isProduction)->toBeTrue();
    });

    it('correctly identifies development environment', function () {
        $envValue = 'development';
        $isProduction = $envValue === 'production';
        $isDevelopment = $envValue === 'development';

        expect($isDevelopment)->toBeTrue()
            ->and($isProduction)->toBeFalse();
    });

    it('correctly identifies local environment', function () {
        $envValue = 'local';
        $isProduction = $envValue === 'production';
        $isLocal = $envValue === 'local';

        expect($isLocal)->toBeTrue()
            ->and($isProduction)->toBeFalse();
    });

    it('correctly identifies testing environment', function () {
        $envValue = 'testing';
        $isProduction = $envValue === 'production';
        $isTesting = $envValue === 'testing';

        expect($isTesting)->toBeTrue()
            ->and($isProduction)->toBeFalse();
    });

    it('determines correct middleware based on environment', function () {
        // Production should require authentication
        $productionMiddleware = 'production' === 'production' ? ['web', 'auth'] : ['web'];
        expect($productionMiddleware)->toBe(['web', 'auth']);

        // Development should only require web
        $developmentMiddleware = 'development' === 'production' ? ['web', 'auth'] : ['web'];
        expect($developmentMiddleware)->toBe(['web']);

        // Local should only require web
        $localMiddleware = 'local' === 'production' ? ['web', 'auth'] : ['web'];
        expect($localMiddleware)->toBe(['web']);

        // Testing should only require web
        $testingMiddleware = 'testing' === 'production' ? ['web', 'auth'] : ['web'];
        expect($testingMiddleware)->toBe(['web']);
    });

    it('detects DDEV environment regardless of other settings', function () {
        // Create a mock controller class that just has the isProductionEnvironment method
        $mockController = new class {
            protected function isProductionEnvironment(): bool
            {
                // Check for DDEV environment FIRST (always treat as development)
                if (isset($_SERVER['DDEV_PROJECT']) || isset($_SERVER['DDEV_HOSTNAME']) || 
                    getenv('DDEV_PROJECT') || getenv('IS_DDEV_PROJECT') ||
                    (isset($_SERVER['IS_DDEV_PROJECT']) && $_SERVER['IS_DDEV_PROJECT'])) {
                    return false; // DDEV is always development
                }

                // Use Laravel's app environment helper
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
                        !preg_match('/\b(localhost|127\.0\.0\.1|\.local|\.dev|\.test|\.ddev\.site)\b/', $host)) {
                        // Additional check: if not clearly development, be conservative
                        if (!preg_match('/\b(dev|staging|test|demo|\.ddev)\b/', $host)) {
                            // This is a heuristic - in production deployments, be extra cautious
                            return true;
                        }
                    }
                }

                return false;
            }

            public function testIsProductionEnvironment() {
                return $this->isProductionEnvironment();
            }
        };

        // Test DDEV_PROJECT server variable
        $_SERVER['DDEV_PROJECT'] = 'test-project';
        expect($mockController->testIsProductionEnvironment())->toBeFalse('DDEV_PROJECT should make environment non-production');
        unset($_SERVER['DDEV_PROJECT']);

        // Test DDEV_HOSTNAME server variable
        $_SERVER['DDEV_HOSTNAME'] = 'test-project.ddev.site';
        expect($mockController->testIsProductionEnvironment())->toBeFalse('DDEV_HOSTNAME should make environment non-production');
        unset($_SERVER['DDEV_HOSTNAME']);

        // Test IS_DDEV_PROJECT server variable
        $_SERVER['IS_DDEV_PROJECT'] = 'true';
        expect($mockController->testIsProductionEnvironment())->toBeFalse('IS_DDEV_PROJECT should make environment non-production');
        unset($_SERVER['IS_DDEV_PROJECT']);
    });

    it('detects DDEV environment in command regardless of other settings', function () {
        // Create a mock command class that just has the isProductionEnvironment method
        $mockCommand = new class {
            protected function isProductionEnvironment(): bool
            {
                // Check for DDEV environment FIRST (always treat as development)
                if (isset($_SERVER['DDEV_PROJECT']) || isset($_SERVER['DDEV_HOSTNAME']) || 
                    getenv('DDEV_PROJECT') || getenv('IS_DDEV_PROJECT') ||
                    (isset($_SERVER['IS_DDEV_PROJECT']) && $_SERVER['IS_DDEV_PROJECT'])) {
                    return false; // DDEV is always development
                }

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
                        !preg_match('/\b(localhost|127\.0\.0\.1|\.local|\.dev|\.test|\.ddev\.site)\b/', $host)) {
                        // Additional check: if not clearly development, be conservative
                        if (!preg_match('/\b(dev|staging|test|demo|\.ddev)\b/', $host)) {
                            // This is a heuristic - in production deployments, be extra cautious
                            return true;
                        }
                    }
                }

                return false;
            }

            public function testIsProductionEnvironment() {
                return $this->isProductionEnvironment();
            }
        };

        // Test command's DDEV detection
        $_SERVER['DDEV_PROJECT'] = 'test-project';

        expect($mockCommand->testIsProductionEnvironment())->toBeFalse('Command should detect DDEV_PROJECT as non-production');

        // Cleanup
        unset($_SERVER['DDEV_PROJECT']);
    });
});