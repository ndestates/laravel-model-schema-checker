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
        $this->info('Checking Security Vulnerabilities');
        $this->info('=================================');

        // Check for CSRF protection in forms
        $this->checkCSRFProtection();

        // Check for XSS vulnerabilities
        $this->checkXSSVulnerabilities();

        // Check for SQL injection vulnerabilities
        $this->checkSQLInjectionVulnerabilities();

        // Check for path traversal vulnerabilities
        $this->checkPathTraversalVulnerabilities();

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
}
