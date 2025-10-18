<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LaravelFormsChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'Laravel Forms Checker';
    }

    public function getDescription(): string
    {
        return 'Check Blade templates and Livewire forms';
    }

    public function check(): array
    {
        $this->info('');
        $this->info('Checking Laravel Forms');
        $this->info('=====================');

        // Check Blade templates
        $this->checkBladeTemplates();

        // Check Livewire components
        $this->checkLivewireComponents();

        return $this->issues;
    }

    protected function checkBladeTemplates(): void
    {
        $viewPath = resource_path('views');

        if (!File::exists($viewPath)) {
            $this->warn("Views directory not found: {$viewPath}");
            return;
        }

        $bladeFiles = File::allFiles($viewPath);

        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'blade.php') {
                $this->checkBladeFile($file);
            }
        }
    }

    protected function checkBladeFile($file): void
    {
        $content = File::get($file->getPathname());
        $fileName = $file->getFilename();

        // Check for CSRF protection in forms
        $this->checkCsrfProtection($content, $fileName, $file->getPathname());

        // Check for proper form validation
        $this->checkFormValidation($content, $fileName, $file->getPathname());

        // Check for XSS prevention
        $this->checkXssPrevention($content, $fileName, $file->getPathname());

        // Check for mass assignment protection
        $this->checkMassAssignment($content, $fileName, $file->getPathname());

        // Check for proper route model binding
        $this->checkRouteModelBinding($content, $fileName, $file->getPathname());

        // Check for path traversal vulnerabilities
        $this->checkPathTraversal($content, $fileName, $file->getPathname());
    }

    protected function checkCsrfProtection(string $content, string $fileName, string $filePath): void
    {
        // Check if file contains forms
        if (!preg_match('/<form[^>]*>/i', $content)) {
            return; // No forms in this file
        }

        // Check for CSRF token in forms
        if (!preg_match('/@csrf|\{\{\s*csrf_token\(\)\s*\}\}|\{\!\s*csrf_token\(\)\s*\!\}/', $content)) {
            $this->issue(
                "Form in {$fileName} is missing CSRF protection (@csrf directive or csrf_token())",
                'high',
                $filePath
            );
        }

        // Check for forms with method POST/PUT/PATCH/DELETE without CSRF
        if (preg_match('/<form[^>]*method\s*=\s*["\'](?:post|put|patch|delete)["\'][^>]*>/i', $content)) {
            if (!preg_match('/@csrf|\{\{\s*csrf_token\(\)\s*\}\}|\{\!\s*csrf_token\(\)\s*\!\}/', $content)) {
                $this->issue(
                    "POST/PUT/PATCH/DELETE form in {$fileName} is missing CSRF protection",
                    'critical',
                    $filePath
                );
            }
        }
    }

    protected function checkFormValidation(string $content, string $fileName, string $filePath): void
    {
        // Check for forms with validation errors display
        if (preg_match('/<form[^>]*>/i', $content)) {
            if (!preg_match('/@error|\$errors->|\{\{\s*\$errors->/', $content)) {
                $this->issue(
                    "Form in {$fileName} does not display validation errors",
                    'medium',
                    $filePath
                );
            }
        }

        // Check for old input preservation
        if (preg_match('/<input[^>]*type\s*=\s*["\'](?:text|email|password)["\'][^>]*>/i', $content)) {
            if (!preg_match('/old\(|@old|\{\{\s*old\(/', $content)) {
                $this->issue(
                    "Form inputs in {$fileName} do not preserve old input on validation failure",
                    'low',
                    $filePath
                );
            }
        }
    }

    protected function checkXssPrevention(string $content, string $fileName, string $filePath): void
    {
        // Check for unescaped output that could lead to XSS
        if (preg_match('/\{\!\s*[^\}]+\!\}/', $content)) {
            $this->issue(
                "{$fileName} contains unescaped output {! !} which could lead to XSS vulnerabilities",
                'high',
                $filePath
            );
        }

        // Check for proper escaping of user input
        if (preg_match('/\{\{\s*\$_(?:GET|POST|REQUEST|SERVER|COOKIE|SESSION)\[/', $content)) {
            $this->issue(
                "{$fileName} outputs superglobal data without proper sanitization",
                'high',
                $filePath
            );
        }
    }

    protected function checkMassAssignment(string $content, string $fileName, string $filePath): void
    {
        // Check for mass assignment in forms (request()->all(), etc.)
        if (preg_match('/request\(\)\s*->\s*all\(\)|\$_POST|\$_REQUEST/i', $content)) {
            $this->issue(
                "{$fileName} may be vulnerable to mass assignment - ensure proper validation and fillable/guarded properties",
                'high',
                $filePath
            );
        }
    }

    protected function checkRouteModelBinding(string $content, string $fileName, string $filePath): void
    {
        // Check for route model binding usage
        if (preg_match('/route\([^)]*\$[^\)]+\)/', $content)) {
            // This is generally good, but check if it's being used safely
            if (!preg_match('/@can\(|@cannot\(|Gate::|\$user->can\(/', $content)) {
                $this->issue(
                    "Route model binding in {$fileName} should include authorization checks",
                    'medium',
                    $filePath
                );
            }
        }
    }

    protected function checkPathTraversal(string $content, string $fileName, string $filePath): void
    {
        // Check for file operations using user input (potential path traversal)
        $this->checkFileOperationsForTraversal($content, $fileName, $filePath);

        // Check for file upload handling that might allow path traversal
        $this->checkFileUploadTraversal($content, $fileName, $filePath);

        // Check for route parameters used in file paths
        $this->checkRouteParameterTraversal($content, $fileName, $filePath);

        // Check for direct file path construction from user input
        $this->checkDirectPathConstruction($content, $fileName, $filePath);
    }

    protected function checkFileOperationsForTraversal(string $content, string $fileName, string $filePath): void
    {
        // Check for file operations using variables that could contain user input
        $fileOperations = [
            'file_get_contents',
            'file_put_contents',
            'fopen',
            'include',
            'require',
            'include_once',
            'require_once',
            'Storage::get',
            'Storage::put',
            'Storage::disk',
            'File::get',
            'File::put'
        ];

        foreach ($fileOperations as $operation) {
            // Look for file operations with variable parameters
            if (preg_match("/{$operation}\s*\(\s*\$[a-zA-Z_][a-zA-Z0-9_]*(?:\s*\.\s*[^)]*)?\s*\)/", $content)) {
                $this->issue(
                    "File operation '{$operation}' in {$fileName} uses variable parameter - potential path traversal vulnerability. Validate and sanitize file paths.",
                    'critical',
                    $filePath
                );
            }

            // Check for concatenation in file paths
            if (preg_match("/{$operation}\s*\(\s*[^)]*\$\w+[^)]*\)/", $content)) {
                $this->issue(
                    "File operation '{$operation}' in {$fileName} constructs path from variables - potential path traversal. Use basename() and validate paths.",
                    'high',
                    $filePath
                );
            }
        }
    }

    protected function checkFileUploadTraversal(string $content, string $fileName, string $filePath): void
    {
        // Check for file upload handling that might allow path traversal
        if (preg_match('/\$request->file\(|request\(\)->file\(/', $content)) {
            // Check if original filename is used directly
            if (preg_match('/->getClientOriginalName\(\)|\.originalName/', $content)) {
                $this->issue(
                    "File upload in {$fileName} uses original filename - potential path traversal. Generate safe filenames instead.",
                    'critical',
                    $filePath
                );
            }

            // Check for file storage without path validation
            if (preg_match('/->store\(|->storeAs\(/', $content)) {
                if (!preg_match('/basename\(|pathinfo\(|realpath\(/', $content)) {
                    $this->issue(
                        "File storage in {$fileName} may not validate paths - potential path traversal. Validate file paths before storage.",
                        'high',
                        $filePath
                    );
                }
            }
        }
    }

    protected function checkRouteParameterTraversal(string $content, string $fileName, string $filePath): void
    {
        // Check for route parameters used in file operations
        if (preg_match('/\$request->\w+|\$request\[|\$_GET\[|\$_POST\[|\$_REQUEST\[/', $content)) {
            // Look for file operations using request parameters
            if (preg_match('/file_get_contents\(|fopen\(|Storage::|File::/', $content)) {
                $this->issue(
                    "Route parameters in {$fileName} used for file operations - potential path traversal. Validate and sanitize all file paths.",
                    'critical',
                    $filePath
                );
            }

            // Check for directory traversal patterns
            if (preg_match('/\.\.[\/\\\\]|\.\.[\/\\\\]/', $content)) {
                $this->issue(
                    "Directory traversal patterns ('../') detected in {$fileName} - critical path traversal vulnerability.",
                    'critical',
                    $filePath
                );
            }
        }
    }

    protected function checkDirectPathConstruction(string $content, string $fileName, string $filePath): void
    {
        // Check for direct path construction from user input
        $dangerousPatterns = [
            '/\$\w+\s*\.\s*[\'"]\/[\'"]/',  // Variable . '/path'
            '/[\'"]\/[\'"]\s*\.\s*\$\w+/',  // '/path' . variable
            '/\$_\w+\[.*\]\s*\.\s*[\'"]/', // $_GET['param'] . '/'
            '/[\'"]\/\.\.\//',              // /../ patterns
            '/\\\\\.\./',                   // \.. patterns (Windows)
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->issue(
                    "Direct path construction from user input detected in {$fileName} - potential path traversal vulnerability. Use secure path handling.",
                    'critical',
                    $filePath
                );
                break;
            }
        }

        // Check for missing path validation functions
        if (preg_match('/\$\w+\s*\.\s*[\'"]\//', $content)) {
            if (!preg_match('/basename\(|realpath\(|pathinfo\(/', $content)) {
                $this->issue(
                    "Path construction in {$fileName} lacks validation functions - potential path traversal. Use basename() or realpath() for validation.",
                    'high',
                    $filePath
                );
            }
        }
    }

    protected function checkLivewireComponents(): void
    {
        $livewirePath = app_path('Livewire');

        if (!File::exists($livewirePath)) {
            $this->info('Livewire directory not found - skipping Livewire checks');
            return;
        }

        $livewireFiles = File::allFiles($livewirePath);

        foreach ($livewireFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkLivewireComponent($file);
            }
        }
    }

    protected function checkLivewireComponent($file): void
    {
        $content = File::get($file->getPathname());
        $className = $file->getFilenameWithoutExtension();

        // Check for proper validation rules
        $this->checkLivewireValidation($content, $className, $file->getPathname());

        // Check for proper authorization
        $this->checkLivewireAuthorization($content, $className, $file->getPathname());

        // Check for secure file uploads
        $this->checkLivewireFileUploads($content, $className, $file->getPathname());

        // Check for proper property declarations
        $this->checkLivewireProperties($content, $className, $file->getPathname());

        // Check for path traversal vulnerabilities in Livewire
        $this->checkLivewirePathTraversal($content, $className, $file->getPathname());
    }

    protected function checkLivewireValidation(string $content, string $className, string $filePath): void
    {
        // Check for validation rules
        if (!preg_match('/protected\s+\$rules\s*=/', $content) &&
            !preg_match('/public\s+function\s+rules\(\)/', $content)) {
            $this->issue(
                "Livewire component {$className} should define validation rules",
                'medium',
                $filePath
            );
        }

        // Check for validation method
        if (preg_match('/public\s+function\s+(\w+)\s*\([^)]*\)\s*\{[\s\S]*?\$this->validate\(/', $content, $matches)) {
            $methodName = $matches[1];
            // Check if validation is called before business logic
            $methodContent = $this->getMethodContent($content, $methodName);
            if ($methodContent) {
                $validatePos = strpos($methodContent, '$this->validate(');
                $businessLogicPos = strpos($methodContent, '$this->');

                if ($validatePos !== false && $businessLogicPos !== false && $businessLogicPos < $validatePos) {
                    $this->issue(
                        "Livewire component {$className} method {$methodName} should validate before executing business logic",
                        'medium',
                        $filePath
                    );
                }
            }
        }
    }

    protected function checkLivewireAuthorization(string $content, string $className, string $filePath): void
    {
        // Check for mount method with parameters (potential authorization issue)
        if (preg_match('/public\s+function\s+mount\s*\([^)]+\$[a-zA-Z_]/', $content)) {
            if (!preg_match('/\$this->authorize\(|Gate::|policy\(|@can\(|@cannot\(/', $content)) {
                $this->issue(
                    "Livewire component {$className} mount method should include authorization checks for bound models",
                    'high',
                    $filePath
                );
            }
        }

        // Check for action methods that might need authorization
        preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $matches);
        foreach ($matches[1] as $method) {
            if (in_array($method, ['mount', 'render', 'rules', 'updated', 'updating'])) {
                continue; // Skip lifecycle methods
            }

            $methodContent = $this->getMethodContent($content, $method);
            if ($methodContent && !preg_match('/authorize\(|Gate::|policy\(|@can\(|@cannot\(/', $methodContent)) {
                $this->issue(
                    "Livewire component {$className} action method {$method} may need authorization checks",
                    'medium',
                    $filePath
                );
            }
        }
    }

    protected function checkLivewireFileUploads(string $content, string $className, string $filePath): void
    {
        // Check for file upload properties
        if (preg_match('/use\s+WithFileUploads;/', $content)) {
            // Check for file validation
            if (!preg_match('/[\'"]file[\'"]\s*=>\s*[\'"]|[\'"]mimes:[\'"]|[\'"]max:[\'"]/', $content)) {
                $this->issue(
                    "Livewire component {$className} uses file uploads but lacks proper validation rules",
                    'high',
                    $filePath
                );
            }

            // Check for secure temporary URL usage
            if (preg_match('/temporaryUrl\(/', $content)) {
                $this->issue(
                    "Livewire component {$className} uses temporaryUrl() - ensure proper security measures",
                    'medium',
                    $filePath
                );
            }
        }
    }

    protected function checkLivewireProperties(string $content, string $className, string $filePath): void
    {
        // Check for public properties that might be sensitive
        preg_match_all('/public\s+\$([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches);

        $sensitivePatterns = ['password', 'token', 'key', 'secret', 'api_key'];
        foreach ($matches[1] as $property) {
            foreach ($sensitivePatterns as $pattern) {
                if (stripos($property, $pattern) !== false) {
                    $this->issue(
                        "Livewire component {$className} has public property \${$property} that may contain sensitive data",
                        'high',
                        $filePath
                    );
                    break;
                }
            }
        }

        // Check for proper property initialization
        if (preg_match('/public\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*;/', $content, $matches)) {
            $property = $matches[1];
            if (!preg_match("/\\\$this->{$property}\s*=\s*[^;]+;/", $content) &&
                !preg_match("/public\s+function\s+mount\s*\([^)]*\)\s*\{[\s\S]*?\\\$this->{$property}/", $content)) {
                $this->issue(
                    "Livewire component {$className} property \${$property} is not initialized",
                    'low',
                    $filePath
                );
            }
        }
    }

    protected function checkLivewirePathTraversal(string $content, string $className, string $filePath): void
    {
        // Check for file operations in Livewire components that could allow path traversal
        $this->checkLivewireFileOperations($content, $className, $filePath);

        // Check for unsafe file upload handling in Livewire
        $this->checkLivewireUploadTraversal($content, $className, $filePath);

        // Check for property-based path construction
        $this->checkLivewirePropertyTraversal($content, $className, $filePath);
    }

    protected function checkLivewireFileOperations(string $content, string $className, string $filePath): void
    {
        // Check for file operations using component properties
        $fileOperations = [
            'Storage::get', 'Storage::put', 'Storage::disk', 'Storage::delete',
            'File::get', 'File::put', 'File::delete', 'File::exists',
            'file_get_contents', 'file_put_contents', 'fopen', 'unlink'
        ];

        foreach ($fileOperations as $operation) {
            // Check if file operations use $this-> properties
            if (preg_match("/{$operation}\s*\(\s*\$this->\w+/", $content)) {
                $this->issue(
                    "Livewire component {$className} uses '{$operation}' with component property - potential path traversal. Validate file paths.",
                    'critical',
                    $filePath
                );
            }

            // Check for concatenation with properties
            if (preg_match("/{$operation}\s*\([^)]*\$this->\w+[^)]*\)/", $content)) {
                $this->issue(
                    "Livewire component {$className} constructs file paths using properties in '{$operation}' - validate for path traversal.",
                    'high',
                    $filePath
                );
            }
        }
    }

    protected function checkLivewireUploadTraversal(string $content, string $className, string $filePath): void
    {
        // Check for WithFileUploads trait usage
        if (preg_match('/use\s+WithFileUploads;/', $content)) {
            // Check if temporary URLs are used without validation
            if (preg_match('/temporaryUrl\(/', $content)) {
                if (!preg_match('/validate\(|rules\(/', $content)) {
                    $this->issue(
                        "Livewire component {$className} uses temporaryUrl() without validation - potential path traversal via file uploads.",
                        'high',
                        $filePath
                    );
                }
            }

            // Check for direct file access using uploaded file properties
            if (preg_match('/\$this->\w+->getRealPath\(\)|\$this->\w+->path/', $content)) {
                $this->issue(
                    "Livewire component {$className} accesses uploaded file paths directly - potential path traversal. Use validated paths only.",
                    'critical',
                    $filePath
                );
            }

            // Check for storing files with user-controlled names
            if (preg_match('/->storeAs\([^,]+,\s*\$this->/', $content)) {
                $this->issue(
                    "Livewire component {$className} stores files with user-controlled names - potential path traversal. Generate safe filenames.",
                    'critical',
                    $filePath
                );
            }
        }
    }

    protected function checkLivewirePropertyTraversal(string $content, string $className, string $filePath): void
    {
        // Check for properties that might contain file paths
        preg_match_all('/public\s+\$([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches);

        $pathRelatedNames = ['file', 'path', 'directory', 'dir', 'folder', 'upload', 'image', 'document', 'media'];
        foreach ($matches[1] as $property) {
            foreach ($pathRelatedNames as $pathName) {
                if (stripos($property, $pathName) !== false) {
                    // Check if this property is used in file operations
                    if (preg_match("/\$this->{$property}/", $content)) {
                        if (preg_match('/Storage::|File::|file_|fopen|unlink/', $content)) {
                            $this->issue(
                                "Livewire component {$className} property \${$property} used in file operations - validate for path traversal.",
                                'high',
                                $filePath
                            );
                        }
                    }
                    break;
                }
            }
        }

        // Check for dynamic property access that could be dangerous
        if (preg_match('/\$this->\{\$[^}]+\}/', $content)) {
            $this->issue(
                "Livewire component {$className} uses dynamic property access - potential path traversal if used for file operations.",
                'medium',
                $filePath
            );
        }
    }

    protected function getMethodContent(string $content, string $methodName): ?string
    {
        // Extract method content using regex
        $pattern = "/public\s+function\s+{$methodName}\s*\([^)]*\)\s*\{([\s\S]*?)\}/";
        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        }
        return null;
    }
}