<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\File;

class SecurityChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Security Checker';
    }

    public function getDescription(): string
    {
        return 'Scan for XSS, CSRF, SQL injection, and path traversal vulnerabilities';
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
        $viewPath = resource_path('views');
        if (!File::exists($viewPath)) {
            return;
        }

        $bladeFiles = File::allFiles($viewPath);
        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'blade.php') {
                $content = file_get_contents($file->getPathname());

                // Check for forms without CSRF tokens
                if (preg_match_all('/<form[^>]*>/i', $content, $matches)) {
                    foreach ($matches[0] as $formTag) {
                        if (!preg_match('/@csrf|\{\{\s*csrf_token\s*\}\}/', $content)) {
                            $this->addIssue('csrf_missing', [
                                'file' => $file->getPathname(),
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
        $viewPath = resource_path('views');
        if (!File::exists($viewPath)) {
            return;
        }

        $bladeFiles = File::allFiles($viewPath);
        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'blade.php') {
                $content = file_get_contents($file->getPathname());

                // Check for unescaped output that could lead to XSS
                if (preg_match_all('/\{\{\{\s*\$[^}]+\s*\}\}\}/', $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $this->addIssue('xss_unescaped_output', [
                            'file' => $file->getPathname(),
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
        $controllerPath = app_path('Http/Controllers');
        if (File::exists($controllerPath)) {
            $controllerFiles = File::allFiles($controllerPath);
            foreach ($controllerFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

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

                                $this->addIssue('sql_injection_risk', [
                                    'file' => $file->getPathname(),
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
        $modelPath = app_path('Models');
        if (File::exists($modelPath)) {
            $modelFiles = File::allFiles($modelPath);
            foreach ($modelFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for raw queries in model methods
                    if (preg_match_all('/\bselect\b.*\bwhere\b.*[\'"]\s*\.\s*\$/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $offset = $match[1];
                            $lineNumber = $this->getLineNumberFromString($content, $offset);

                            $this->addIssue('sql_injection_string_concat', [
                                'file' => $file->getPathname(),
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
        $paths = [app_path('Http/Controllers'), app_path('Models')];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                $files = File::allFiles($path);
                foreach ($files as $file) {
                    if ($file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());

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

                                    $this->addIssue('path_traversal_risk', [
                                        'file' => $file->getPathname(),
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
        $controllerPath = app_path('Http/Controllers');
        if (File::exists($controllerPath)) {
            $controllerFiles = File::allFiles($controllerPath);
            foreach ($controllerFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Check for file upload handling
                    if (preg_match('/\$request->file\(|\$_FILES/', $content)) {
                        // Check if file validation is present
                        if (!preg_match('/validate\(|rules\(/', $content)) {
                            $this->addIssue('upload_validation_missing', [
                                'file' => $file->getPathname(),
                                'message' => "File upload detected without validation rules. Implement file type, size, and name validation to prevent security issues."
                            ]);
                        }

                        // Check for original filename usage (potential path traversal)
                        if (preg_match('/getClientOriginalName\(|originalName/', $content)) {
                            $this->addIssue('original_filename_usage', [
                                'file' => $file->getPathname(),
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