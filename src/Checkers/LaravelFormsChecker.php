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