<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\File;

class SecurityChecker extends BaseChecker
{
    protected string $viewPath;
    protected string $controllerPath;
    protected string $modelPath;

    public function __construct(array $config = [], ?string $viewPath = null, ?string $controllerPath = null, ?string $modelPath = null)
    {
        parent::__construct($config);

        $this->viewPath = $viewPath ?? $this->getDefaultViewPath();
        $this->controllerPath = $controllerPath ?? $this->getDefaultControllerPath();
        $this->modelPath = $modelPath ?? $this->getDefaultModelPath();
    }

    protected function getDefaultViewPath(): string
    {
        // Use config value if available, otherwise try Laravel helper
        if (isset($this->config['view_path'])) {
            return $this->config['view_path'];
        }

        try {
            return resource_path('views');
        } catch (\Exception $e) {
            // Laravel environment not fully available
            return '';
        }
    }

    protected function getDefaultControllerPath(): string
    {
        // Use config value if available, otherwise try Laravel helper
        if (isset($this->config['controller_path'])) {
            return $this->config['controller_path'];
        }

        try {
            return app_path('Http/Controllers');
        } catch (\Exception $e) {
            // Laravel environment not fully available
            return '';
        }
    }

    protected function getDefaultModelPath(): string
    {
        // Use config value if available, otherwise try Laravel helper
        if (isset($this->config['model_path'])) {
            return $this->config['model_path'];
        }

        try {
            return app_path('Models');
        } catch (\Exception $e) {
            // Laravel environment not fully available
            return '';
        }
    }

    protected function isLaravelEnvironment(): bool
    {
        return function_exists('resource_path') && function_exists('app_path');
    }

    protected function getAllFiles(string $directory): array
    {
        if ($this->isLaravelEnvironment()) {
            try {
                return File::allFiles($directory);
            } catch (\Exception $e) {
                // Fallback to PHP functions if facade is not available
            }
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function getName(): string
    {
        return 'Security Checker';
    }

    public function getDescription(): string
    {
        return 'Scan for XSS, CSRF, SQL injection, and path traversal vulnerabilities';
    }

    protected function getRuleName(): ?string
    {
        return 'security_checks';
    }

    public function check(): array
    {
        $this->info('');
        $this->info('🔒 Modern Cybersecurity Essentials Check');
        $this->info('========================================');

        // Core Security Checks
        $this->checkCSRFProtection();
        $this->checkXSSVulnerabilities();
        $this->checkSQLInjectionVulnerabilities();
        $this->checkPathTraversalVulnerabilities();

        // Modern Cybersecurity Essentials
        $this->checkMassAssignmentProtection();
        $this->checkAuthenticationBypass();
        $this->checkDataExposure();
        $this->checkSecureHeaders();
        $this->checkSecureConfiguration();
        $this->checkDependencySecurity();
        $this->checkSecureCodingPractices();
        $this->checkInputValidation();
        $this->checkErrorHandlingSecurity();

        return $this->issues;
    }

    protected function checkCSRFProtection(): void
    {
        $this->info('Checking CSRF Protection...');

        // Check Filament forms (they handle CSRF automatically)
        if (class_exists(\Filament\FilamentServiceProvider::class)) {
            $this->info('✓ Filament forms include automatic CSRF protection');
        }

        // Check Laravel forms in blade templates
        $this->checkBladeCSRFProtection();
    }

    protected function checkXSSVulnerabilities(): void
    {
        $this->info('Checking XSS Vulnerabilities...');

        // Check Filament forms (they handle escaping automatically)
        if (class_exists(\Filament\FilamentServiceProvider::class)) {
            $this->info('✓ Filament forms include automatic XSS protection');
        }

        // Check Laravel blade templates for unescaped output
        $this->checkBladeXSSProtection();
    }

    protected function checkSQLInjectionVulnerabilities(): void
    {
        $this->info('Checking SQL Injection Vulnerabilities...');

        // Check for raw database queries in controllers and models
        $this->checkRawDatabaseQueries();

        // Check for proper use of Eloquent vs raw queries
        $this->checkEloquentUsage();
    }

    protected function checkPathTraversalVulnerabilities(): void
    {
        $this->info('Checking Path Traversal Vulnerabilities...');

        // Check file operations for path traversal issues
        $this->checkFileOperations();

        // Check for unsafe file upload handling
        $this->checkFileUploads();
    }

    protected function checkBladeCSRFProtection(): void
    {
        if (!file_exists($this->viewPath)) {
            return;
        }

        $bladeFiles = $this->getAllFiles($this->viewPath);
        foreach ($bladeFiles as $file) {
            if (str_ends_with($file, '.blade.php')) {
                $content = file_get_contents($file);

                if ($content === false) {
                    continue; // Skip files that cannot be read
                }

                // Check for forms without CSRF tokens
                if (preg_match_all('/<form[^>]*>/i', $content, $matches)) {
                    foreach ($matches[0] as $formTag) {
                        if (!preg_match('/@csrf|\{\{\s*csrf_token\s*\}\}/', $content)) {
                            $this->addIssue('security', 'csrf_missing', [
                                'file' => $file,
                                'form_tag' => $formTag,
                                'message' => 'Form found without CSRF token protection'
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function checkBladeXSSProtection(): void
    {
        if (!file_exists($this->viewPath)) {
            return;
        }

        $bladeFiles = $this->getAllFiles($this->viewPath);
        foreach ($bladeFiles as $file) {
            if (str_ends_with($file, '.blade.php')) {
                $content = file_get_contents($file);

                if ($content === false) {
                    continue; // Skip files that cannot be read
                }

                // Check for unescaped output that could lead to XSS
                if (preg_match_all('/\{\{\{\s*\$[^}]+\s*\}\}\}/', $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $this->addIssue('security', 'xss_unescaped_output', [
                            'file' => $file,
                            'unescaped_output' => $match,
                            'message' => 'Triple braces {{{ }}} allow unescaped HTML output - potential XSS vulnerability'
                        ]);
                    }
                }
            }
        }
    }

    protected function checkRawDatabaseQueries(): void
    {
        // Check controllers for raw database queries
        if (file_exists($this->controllerPath)) {
            $controllerFiles = $this->getAllFiles($this->controllerPath);
            foreach ($controllerFiles as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $content = file_get_contents($file);

                    if ($content === false) {
                        continue; // Skip files that cannot be read
                    }

                    // Check for raw DB::raw(), DB::select(), etc.
                    $rawQueryPatterns = [
                        '/DB::raw\(/',
                        '/DB::select\(/',
                        '/DB::insert\(/',
                        '/DB::update\(/',
                        '/DB::delete\(/',
                    ];

                    foreach ($rawQueryPatterns as $pattern) {
                        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                            foreach ($matches[0] as $match) {
                                $offset = $match[1];
                                $lineNumber = $this->getLineNumberFromString($content, $offset);

                                $this->addIssue('security', 'sql_injection_risk', [
                                    'file' => $file,
                                    'line' => $lineNumber,
                                    'query_type' => str_replace(['DB::', '('], '', $match[0]),
                                    'message' => "Raw database query found - potential SQL injection vulnerability. Use parameterized queries or Eloquent instead."
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function checkEloquentUsage(): void
    {
        // Check models for proper Eloquent usage vs raw queries
        if (file_exists($this->modelPath)) {
            $modelFiles = $this->getAllFiles($this->modelPath);
            foreach ($modelFiles as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $content = file_get_contents($file);

                    if ($content === false) {
                        continue; // Skip files that cannot be read
                    }

                    // Check for raw queries in model methods
                    if (preg_match_all('/\bselect\b.*\bwhere\b.*[\'"]\s*\.\s*\$/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $offset = $match[1];
                            $lineNumber = $this->getLineNumberFromString($content, $offset);

                            $this->addIssue('security', 'sql_injection_string_concat', [
                                'file' => $file,
                                'line' => $lineNumber,
                                'code' => trim($match[0]),
                                'message' => "String concatenation in SQL query - potential SQL injection. Use parameterized queries."
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function checkFileOperations(): void
    {
        // Check for unsafe file operations
        $paths = [$this->controllerPath, $this->modelPath];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $files = $this->getAllFiles($path);
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                        $content = file_get_contents($file);

                        if ($content === false) {
                            continue; // Skip files that cannot be read
                        }

                        // Check for direct file path usage without validation
                        $fileOpPatterns = [
                            '/\bfopen\b.*\$_\w+/',
                            '/\bfile_get_contents\b.*\$_\w+/',
                            '/\bfile_put_contents\b.*\$_\w+/',
                            '/\bunlink\b.*\$_\w+/',
                            '/\binclude\b.*\$_\w+/',
                            '/\brequire\b.*\$_\w+/',
                        ];

                        foreach ($fileOpPatterns as $pattern) {
                            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                                foreach ($matches[0] as $match) {
                                    $offset = $match[1];
                                    $lineNumber = $this->getLineNumberFromString($content, $offset);

                                    $this->addIssue('security', 'path_traversal_risk', [
                                        'file' => $file,
                                        'line' => $lineNumber,
                                        'operation' => trim($match[0]),
                                        'message' => "File operation using user input - potential path traversal vulnerability. Validate and sanitize file paths."
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function checkFileUploads(): void
    {
        // Check controllers for file upload handling
        if (file_exists($this->controllerPath)) {
            $controllerFiles = $this->getAllFiles($this->controllerPath);
            foreach ($controllerFiles as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $content = file_get_contents($file);

                    if ($content === false) {
                        continue; // Skip files that cannot be read
                    }

                    // Check for file upload handling
                    if (preg_match('/\$request->file\(|\$_FILES/', $content)) {
                        // Check if file validation is present
                        if (!preg_match('/validate\(|rules\(/', $content)) {
                            $this->addIssue('security', 'upload_validation_missing', [
                                'file' => $file,
                                'message' => "File upload detected without validation rules. Implement file type, size, and name validation to prevent security issues."
                            ]);
                        }

                        // Check for original filename usage (potential path traversal)
                        if (preg_match('/getClientOriginalName\(|originalName/', $content)) {
                            $this->addIssue('security', 'original_filename_usage', [
                                'file' => $file,
                                'message' => "Using original filename from upload - potential path traversal. Generate safe filenames instead."
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function getLineNumberFromString(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }

    // ===== MODERN CYBERSECURITY ESSENTIALS =====

    protected function checkMassAssignmentProtection(): void
    {
        $this->info('🔒 Checking Mass Assignment Protection...');

        if (!file_exists($this->modelPath)) {
            return;
        }

        $modelFiles = $this->getAllFiles($this->modelPath);
        foreach ($modelFiles as $file) {
            if (str_ends_with($file, '.php')) {
                $content = file_get_contents($file);

                if ($content === false) {
                    continue;
                }

                // Check for unguarded models (critical security risk)
                if (preg_match('/public\s+static\s+function\s+unguard\s*\(\s*\)/', $content)) {
                    $this->addIssue('security', 'mass_assignment_unguarded', [
                        'file' => $file,
                        'severity' => 'critical',
                        'message' => "Model uses unguarded() - allows mass assignment of ALL attributes. This is a critical security vulnerability."
                    ]);
                }

                // Check for missing fillable/guarded properties
                if (!preg_match('/protected\s+\$fillable\s*=|protected\s+\$guarded\s*=/', $content)) {
                    $this->addIssue('security', 'mass_assignment_unprotected', [
                        'file' => $file,
                        'severity' => 'high',
                        'message' => "Model has no fillable or guarded properties defined. This allows mass assignment vulnerabilities."
                    ]);
                }
            }
        }
    }

    protected function checkAuthenticationBypass(): void
    {
        $this->info('🔐 Checking Authentication Bypass Vulnerabilities...');

        if (!file_exists($this->controllerPath)) {
            return;
        }

        $controllerFiles = $this->getAllFiles($this->controllerPath);
        foreach ($controllerFiles as $file) {
            if (str_ends_with($file, '.php')) {
                $content = file_get_contents($file);

                if ($content === false) {
                    continue;
                }

                // Check for missing authentication middleware
                if (!preg_match('/middleware\s*\(\s*[\'"]auth[\'"]|auth\s*middleware/', $content)) {
                    // Look for sensitive operations without auth checks
                    if (preg_match('/public\s+function\s+(store|update|destroy|delete)/', $content)) {
                        $this->addIssue('security', 'missing_auth_middleware', [
                            'file' => $file,
                            'severity' => 'high',
                            'message' => "Controller method performs sensitive operations without authentication middleware."
                        ]);
                    }
                }

                // Check for authorization bypass (missing policy checks)
                if (preg_match('/public\s+function\s+(update|destroy|delete)/', $content)) {
                    if (!preg_match('/authorize\(|can\(|policy\(/', $content)) {
                        $this->addIssue('security', 'missing_authorization', [
                            'file' => $file,
                            'severity' => 'medium',
                            'message' => "Controller method modifies data without authorization checks."
                        ]);
                    }
                }
            }
        }
    }

    protected function checkDataExposure(): void
    {
        $this->info('🛡️  Checking Data Exposure Vulnerabilities...');

        if (!file_exists($this->controllerPath)) {
            return;
        }

        $controllerFiles = $this->getAllFiles($this->controllerPath);
        foreach ($controllerFiles as $file) {
            if (str_ends_with($file, '.php')) {
                $content = file_get_contents($file);

                if ($content === false) {
                    continue;
                }

                // Check for potential data leaks in error responses
                if (preg_match('/dd\(|dump\(|var_dump\(/', $content)) {
                    $this->addIssue('security', 'debug_data_exposure', [
                        'file' => $file,
                        'severity' => 'high',
                        'message' => "Debug functions (dd, dump, var_dump) found in production code - can expose sensitive data."
                    ]);
                }

                // Check for sensitive data in logs
                if (preg_match('/Log::|logger\(\)/', $content)) {
                    if (preg_match('/password|token|key|secret/i', $content)) {
                        $this->addIssue('security', 'sensitive_data_logging', [
                            'file' => $file,
                            'severity' => 'medium',
                            'message' => "Potential logging of sensitive data (passwords, tokens, keys)."
                        ]);
                    }
                }
            }
        }
    }

    protected function checkSecureHeaders(): void
    {
        $this->info('🛡️  Checking Secure Headers Configuration...');

        // Check for security middleware
        $middlewarePath = app_path('Http/Middleware');
        if (file_exists($middlewarePath)) {
            $middlewareFiles = $this->getAllFiles($middlewarePath);

            $hasSecurityHeaders = false;
            foreach ($middlewareFiles as $file) {
                if (str_ends_with($file, '.php')) {
                    $content = file_get_contents($file);

                    if ($content === false) {
                        continue;
                    }

                    if (preg_match('/Content-Security-Policy|X-Frame-Options|X-Content-Type-Options|HSTS/i', $content)) {
                        $hasSecurityHeaders = true;
                        break;
                    }
                }
            }

            if (!$hasSecurityHeaders) {
                $this->addIssue('security', 'missing_security_headers', [
                    'severity' => 'medium',
                    'message' => "No security headers middleware detected. Consider implementing CSP, X-Frame-Options, HSTS, and other security headers."
                ]);
            }
        }
    }

    protected function checkSecureConfiguration(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $this->info('⚙️  Checking Secure Configuration...');

        // Check for debug mode in production
        if (config('app.debug') === true && app()->environment('production')) {
            $this->addIssue('security', 'debug_enabled_production', [
                'severity' => 'critical',
                'message' => "Debug mode is enabled in production environment - exposes sensitive application information."
            ]);
        }

        // Check database credentials exposure
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if ($envContent !== false) {
                if (preg_match('/DB_PASSWORD\s*=\s*(.+)/', $envContent, $matches)) {
                    if (strlen($matches[1]) < 8) {
                        $this->addIssue('security', 'weak_db_password', [
                            'severity' => 'high',
                            'message' => "Database password is very short. Use strong, complex passwords."
                        ]);
                    }
                }
            }
        }
    }

    protected function checkDependencySecurity(): void
    {
        $this->info('📦 Checking Dependency Security...');

        $composerPath = base_path('composer.json');
        if (file_exists($composerPath)) {
            $composerContent = file_get_contents($composerPath);
            if ($composerContent !== false) {
                $composerData = json_decode($composerContent, true);

                if ($composerData) {
                    // Check for known vulnerable packages (basic check)
                    $vulnerablePackages = [
                        'laravel/framework' => '11.0', // Example minimum versions
                        'symfony/http-kernel' => '6.0',
                    ];

                    foreach ($vulnerablePackages as $package => $minVersion) {
                        if (isset($composerData['require'][$package])) {
                            $currentVersion = $composerData['require'][$package];
                            if (version_compare($currentVersion, $minVersion, '<')) {
                                $this->addIssue('security', 'outdated_dependency', [
                                    'severity' => 'medium',
                                    'package' => $package,
                                    'current' => $currentVersion,
                                    'recommended' => $minVersion,
                                    'message' => "Package {$package} version {$currentVersion} may have security vulnerabilities. Update to {$minVersion}+."
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function checkSecureCodingPractices(): void
    {
        $this->info('💻 Checking Secure Coding Practices...');

        $allFiles = array_merge(
            file_exists($this->controllerPath) ? $this->getAllFiles($this->controllerPath) : [],
            file_exists($this->modelPath) ? $this->getAllFiles($this->modelPath) : []
        );

        foreach ($allFiles as $file) {
            if (str_ends_with($file, '.php')) {
                $content = file_get_contents($file);

                if ($content === false) {
                    continue;
                }

                // Check for eval() usage (critical security risk)
                if (preg_match('/\beval\s*\(/', $content)) {
                    $this->addIssue('security', 'dangerous_eval_usage', [
                        'file' => $file,
                        'severity' => 'critical',
                        'message' => "Use of eval() function detected - allows code injection attacks."
                    ]);
                }

                // Check for shell execution without proper validation
                if (preg_match('/exec\(|shell_exec\(|system\(|passthru\(/', $content)) {
                    $this->addIssue('security', 'unsafe_shell_execution', [
                        'file' => $file,
                        'severity' => 'high',
                        'message' => "Shell execution functions used without proper input validation."
                    ]);
                }

                // Check for weak random number generation
                if (preg_match('/rand\(|mt_rand\(/', $content)) {
                    if (!preg_match('/openssl_random_pseudo_bytes|random_bytes/', $content)) {
                        $this->addIssue('security', 'weak_random_generation', [
                            'file' => $file,
                            'severity' => 'medium',
                            'message' => "Using weak random number generation. Consider using random_bytes() or openssl_random_pseudo_bytes()."
                        ]);
                    }
                }
            }
        }
    }

    protected function checkInputValidation(): void
    {
        $this->info('✅ Checking Input Validation...');

        if (!file_exists($this->controllerPath)) {
            return;
        }

        $controllerFiles = $this->getAllFiles($this->controllerPath);
        foreach ($controllerFiles as $file) {
            if (str_ends_with($file, '.php')) {
                $content = file_get_contents($file);

                if ($content === false) {
                    continue;
                }

                // Check for user input usage without validation
                if (preg_match('/\$request->input\(|\$request->get\(|\$_GET|\$_POST/', $content)) {
                    if (!preg_match('/validate\(|rules\(|bail\(/', $content)) {
                        $this->addIssue('security', 'unvalidated_input', [
                            'file' => $file,
                            'severity' => 'high',
                            'message' => "User input used without validation. Implement proper validation rules."
                        ]);
                    }
                }

                // Check for SQL-like user input (potential injection)
                if (preg_match('/\$request->.*\%|\$request->.*like/i', $content)) {
                    $this->addIssue('security', 'potential_sql_injection', [
                        'file' => $file,
                        'severity' => 'medium',
                        'message' => "User input used in SQL-like operations. Ensure proper escaping and validation."
                    ]);
                }
            }
        }
    }

    protected function checkErrorHandlingSecurity(): void
    {
        $this->info('🚨 Checking Error Handling Security...');

        $allFiles = array_merge(
            file_exists($this->controllerPath) ? $this->getAllFiles($this->controllerPath) : [],
            file_exists($this->modelPath) ? $this->getAllFiles($this->modelPath) : []
        );

        foreach ($allFiles as $file) {
            if (str_ends_with($file, '.php')) {
                $content = file_get_contents($file);

                if ($content === false) {
                    continue;
                }

                // Check for try-catch blocks that might leak sensitive information
                if (preg_match('/catch\s*\(\s*Exception\s+\$e\s*\)\s*\{/', $content)) {
                    if (preg_match('/echo\s+\$e|return\s+\$e|\$e->getMessage\(\)/', $content)) {
                        $this->addIssue('security', 'information_disclosure', [
                            'file' => $file,
                            'severity' => 'medium',
                            'message' => "Exception details exposed to user. Use generic error messages in production."
                        ]);
                    }
                }

                // Check for proper error logging
                if (preg_match('/catch\s*\(/', $content)) {
                    if (!preg_match('/Log::|logger\(\)/', $content)) {
                        $this->addIssue('security', 'missing_error_logging', [
                            'file' => $file,
                            'severity' => 'low',
                            'message' => "Exceptions caught but not logged. Implement proper error logging for debugging."
                        ]);
                    }
                }
            }
        }
    }
}
