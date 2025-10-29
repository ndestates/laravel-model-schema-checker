<?php

namespace NDEstates\LaravelModelSchemaChecker\Checkers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
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

        // Check form amendments and suggestions
        $this->checkFormAmendments();

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

    protected function checkBladeFile(\SplFileInfo $file): void
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
            $this->addIssue('forms', 'missing_csrf_protection', [
                'file' => $filePath,
                'filename' => $fileName,
                'message' => "Form in {$fileName} is missing CSRF protection (@csrf directive or csrf_token())"
            ]);
        }

        // Check for forms with method POST/PUT/PATCH/DELETE without CSRF
        if (preg_match('/<form[^>]*method\s*=\s*["\'](?:post|put|patch|delete)["\'][^>]*>/i', $content)) {
            if (!preg_match('/@csrf|\{\{\s*csrf_token\(\)\s*\}\}|\{\!\s*csrf_token\(\)\s*\!\}/', $content)) {
                $this->addIssue('forms', 'missing_csrf_for_state_changing_methods', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'message' => "POST/PUT/PATCH/DELETE form in {$fileName} is missing CSRF protection"
                ]);
            }
        }
    }

    protected function checkFormValidation(string $content, string $fileName, string $filePath): void
    {
        // Check for forms with validation errors display
        if (preg_match('/<form[^>]*>/i', $content)) {
            if (!preg_match('/@error|\$errors->|\{\{\s*\$errors->/', $content)) {
                $this->addIssue('forms', 'missing_validation_errors', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'message' => "Form in {$fileName} does not display validation errors"
                ]);
            }
        }

        // Check for old input preservation
        if (preg_match('/<input[^>]*type\s*=\s*["\'](?:text|email|password)["\'][^>]*>/i', $content)) {
            if (!preg_match('/old\(|@old|\{\{\s*old\(/', $content)) {
                $this->addIssue('forms', 'missing_old_input_preservation', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'message' => "Form inputs in {$fileName} do not preserve old input on validation failure"
                ]);
            }
        }
    }

    protected function checkXssPrevention(string $content, string $fileName, string $filePath): void
    {
        // Check for unescaped output that could lead to XSS
        if (preg_match('/\{\!\s*[^\}]+\!\}/', $content)) {
            $this->addIssue('forms', 'unescaped_output', [
                'file' => $filePath,
                'filename' => $fileName,
                'message' => "{$fileName} contains unescaped output {! !} which could lead to XSS vulnerabilities"
            ]);
        }

        // Check for proper escaping of user input
        if (preg_match('/\{\{\s*\$_(?:GET|POST|REQUEST|SERVER|COOKIE|SESSION)\[/', $content)) {
            $this->addIssue('forms', 'unsanitized_superglobal_output', [
                'file' => $filePath,
                'filename' => $fileName,
                'message' => "{$fileName} outputs superglobal data without proper sanitization"
            ]);
        }
    }

    protected function checkMassAssignment(string $content, string $fileName, string $filePath): void
    {
        // Check for mass assignment in forms (request()->all(), etc.)
        if (preg_match('/request\(\)\s*->\s*all\(\)|\$_POST|\$_REQUEST/i', $content)) {
            $this->addIssue('forms', 'potential_mass_assignment', [
                'file' => $filePath,
                'filename' => $fileName,
                'message' => "{$fileName} may be vulnerable to mass assignment - ensure proper validation and fillable/guarded properties"
            ]);
        }
    }

    protected function checkRouteModelBinding(string $content, string $fileName, string $filePath): void
    {
        // Check for route model binding usage
        if (preg_match('/route\([^)]*\$[^\)]+\)/', $content)) {
            // This is generally good, but check if it's being used safely
            if (!preg_match('/@can\(|@cannot\(|Gate::|\$user->can\(/', $content)) {
                $this->addIssue('forms', 'missing_authorization_route_binding', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'message' => "Route model binding in {$fileName} should include authorization checks"
                ]);
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
                $this->addIssue('forms', 'file_operation_variable_parameter', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'operation' => $operation,
                    'message' => "File operation '{$operation}' in {$fileName} uses variable parameter - potential path traversal vulnerability. Validate and sanitize file paths."
                ]);
            }

            // Check for concatenation in file paths
            if (preg_match("/{$operation}\s*\(\s*[^)]*\$\w+[^)]*\)/", $content)) {
                $this->addIssue('forms', 'file_path_concatenation', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'operation' => $operation,
                    'message' => "File operation '{$operation}' in {$fileName} constructs path from variables - potential path traversal. Use basename() and validate paths."
                ]);
            }
        }
    }

    protected function checkFileUploadTraversal(string $content, string $fileName, string $filePath): void
    {
        // Check for file upload handling that might allow path traversal
        if (preg_match('/\$request->file\(|request\(\)->file\(/', $content)) {
            // Check if original filename is used directly
            if (preg_match('/->getClientOriginalName\(\)|\.originalName/', $content)) {
                $this->addIssue('forms', 'original_filename_usage', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'message' => "File upload in {$fileName} uses original filename - potential path traversal. Generate safe filenames instead."
                ]);
            }

            // Check for file storage without path validation
            if (preg_match('/->store\(|->storeAs\(/', $content)) {
                if (!preg_match('/basename\(|pathinfo\(|realpath\(/', $content)) {
                    $this->addIssue('forms', 'unvalidated_file_storage', [
                        'file' => $filePath,
                        'filename' => $fileName,
                        'message' => "File storage in {$fileName} may not validate paths - potential path traversal. Validate file paths before storage."
                    ]);
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
                $this->addIssue('forms', 'route_parameter_file_operations', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'message' => "Route parameters in {$fileName} used for file operations - potential path traversal. Validate and sanitize all file paths."
                ]);
            }

            // Check for directory traversal patterns
            if (preg_match('/\.\.[\/\\\\]|\.\.[\/\\\\]/', $content)) {
                $this->addIssue('forms', 'directory_traversal_pattern', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'message' => "Directory traversal patterns ('../') detected in {$fileName} - critical path traversal vulnerability."
                ]);
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
                $this->addIssue('forms', 'direct_path_construction', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'pattern' => $pattern,
                    'message' => "Direct path construction from user input detected in {$fileName} - potential path traversal vulnerability. Use secure path handling."
                ]);
                break;
            }
        }

        // Check for missing path validation functions
        if (preg_match('/\$\w+\s*\.\s*[\'"]\//', $content)) {
            if (!preg_match('/basename\(|realpath\(|pathinfo\(/', $content)) {
                $this->addIssue('forms', 'missing_path_validation', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'message' => "Path construction in {$fileName} lacks validation functions - potential path traversal. Use basename() or realpath() for validation."
                ]);
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

    protected function checkLivewireComponent(\Symfony\Component\Finder\SplFileInfo $file): void
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
        if (
            !preg_match('/protected\s+\$rules\s*=/', $content) &&
            !preg_match('/public\s+function\s+rules\(\)/', $content)
        ) {
            $this->addIssue('forms', 'missing_livewire_validation_rules', [
                'file' => $filePath,
                'component' => $className,
                'message' => "Livewire component {$className} should define validation rules"
            ]);
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
                    $this->addIssue('forms', 'validation_after_business_logic', [
                        'file' => $filePath,
                        'component' => $className,
                        'method' => $methodName,
                        'message' => "Livewire component {$className} method {$methodName} should validate before executing business logic"
                    ]);
                }
            }
        }
    }

    protected function checkLivewireAuthorization(string $content, string $className, string $filePath): void
    {
        // Check for mount method with parameters (potential authorization issue)
        if (preg_match('/public\s+function\s+mount\s*\([^)]+\$[a-zA-Z_]/', $content)) {
            if (!preg_match('/\$this->authorize\(|Gate::|policy\(|@can\(|@cannot\(/', $content)) {
                $this->addIssue('forms', 'missing_mount_authorization', [
                    'file' => $filePath,
                    'component' => $className,
                    'message' => "Livewire component {$className} mount method should include authorization checks for bound models"
                ]);
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
                $this->addIssue('forms', 'missing_action_authorization', [
                    'file' => $filePath,
                    'component' => $className,
                    'method' => $method,
                    'message' => "Livewire component {$className} action method {$method} may need authorization checks"
                ]);
            }
        }
    }

    protected function checkLivewireFileUploads(string $content, string $className, string $filePath): void
    {
        // Check for file upload properties
        if (preg_match('/use\s+WithFileUploads;/', $content)) {
            // Check for file validation
            if (!preg_match('/[\'"]file[\'"]\s*=>\s*[\'"]|[\'"]mimes:[\'"]|[\'"]max:[\'"]/', $content)) {
                $this->addIssue('forms', 'missing_file_validation', [
                    'message' => "Livewire component {$className} uses file uploads but lacks proper validation rules",
                    'severity' => 'high',
                    'file' => $filePath
                ]);
            }

            // Check for secure temporary URL usage
            if (preg_match('/temporaryUrl\(/', $content)) {
                $this->addIssue('forms', 'insecure_temporary_url', [
                    'message' => "Livewire component {$className} uses temporaryUrl() - ensure proper security measures",
                    'severity' => 'medium',
                    'file' => $filePath
                ]);
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
                    $this->addIssue('forms', 'sensitive_public_property', [
                        'file' => $filePath,
                        'component' => $className,
                        'property' => $property,
                        'message' => "Livewire component {$className} has public property \${$property} that may contain sensitive data"
                    ]);
                    break;
                }
            }
        }

        // Check for proper property initialization
        if (preg_match('/public\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*;/', $content, $matches)) {
            $property = $matches[1];
            if (
                !preg_match("/\\\$this->{$property}\s*=\s*[^;]+;/", $content) &&
                !preg_match("/public\s+function\s+mount\s*\([^)]*\)\s*\{[\s\S]*?\\\$this->{$property}/", $content)
            ) {
                $this->addIssue('forms', 'uninitialized_property', [
                    'file' => $filePath,
                    'component' => $className,
                    'property' => $property,
                    'message' => "Livewire component {$className} property \${$property} is not initialized"
                ]);
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
                $this->addIssue('forms', 'file_operation_with_property', [
                    'file' => $filePath,
                    'component' => $className,
                    'operation' => $operation,
                    'message' => "Livewire component {$className} uses '{$operation}' with component property - potential path traversal. Validate file paths."
                ]);
            }

            // Check for concatenation with properties
            if (preg_match("/{$operation}\s*\([^)]*\$this->\w+[^)]*\)/", $content)) {
                $this->addIssue('forms', 'property_path_construction', [
                    'file' => $filePath,
                    'component' => $className,
                    'operation' => $operation,
                    'message' => "Livewire component {$className} constructs file paths using properties in '{$operation}' - validate for path traversal."
                ]);
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
                    $this->addIssue('forms', 'unvalidated_temporary_url', [
                        'file' => $filePath,
                        'component' => $className,
                        'message' => "Livewire component {$className} uses temporaryUrl() without validation - potential path traversal via file uploads."
                    ]);
                }
            }

            // Check for direct file access using uploaded file properties
            if (preg_match('/\$this->\w+->getRealPath\(\)|\$this->\w+->path/', $content)) {
                $this->addIssue('forms', 'direct_file_path_access', [
                    'file' => $filePath,
                    'component' => $className,
                    'message' => "Livewire component {$className} accesses uploaded file paths directly - potential path traversal. Use validated paths only."
                ]);
            }

            // Check for storing files with user-controlled names
            if (preg_match('/->storeAs\([^,]+,\s*\$this->/', $content)) {
                $this->addIssue('forms', 'user_controlled_filename', [
                    'file' => $filePath,
                    'component' => $className,
                    'message' => "Livewire component {$className} stores files with user-controlled names - potential path traversal. Generate safe filenames."
                ]);
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
                            $this->addIssue('forms', 'path_property_file_operations', [
                                'file' => $filePath,
                                'component' => $className,
                                'property' => $property,
                                'message' => "Livewire component {$className} property \${$property} used in file operations - validate for path traversal."
                            ]);
                        }
                    }
                    break;
                }
            }
        }

        // Check for dynamic property access that could be dangerous
        if (preg_match('/\$this->\{\$[^}]+\}/', $content)) {
            $this->addIssue('forms', 'dynamic_property_access', [
                'file' => $filePath,
                'component' => $className,
                'message' => "Livewire component {$className} uses dynamic property access - potential path traversal if used for file operations."
            ]);
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

    protected function checkFormAmendments(): void
    {
        $this->info('');
        $this->info('Checking Form Amendments');
        $this->info('=======================');

        // Analyze models to understand expected fields
        $modelFields = $this->analyzeModelsForFields();

        // Check Blade templates for form amendments
        $this->checkBladeFormAmendments($modelFields);

        // Check Livewire components for form amendments
        $this->checkLivewireFormAmendments($modelFields);

        // Offer automatic fixes for detected issues
        $this->offerAutomaticFixes();
    }

    protected function analyzeModelsForFields(): array
    {
        $modelsPath = app_path('Models');

        if (!File::exists($modelsPath)) {
            return [];
        }

        $modelFields = [];

        $modelFiles = File::allFiles($modelsPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() === 'php') {
                $modelData = $this->analyzeModelFields($file);
                if ($modelData) {
                    $modelFields[$modelData['model_name']] = $modelData;
                }
            }
        }

        return $modelFields;
    }

    protected function analyzeModelFields(\Symfony\Component\Finder\SplFileInfo $file): ?array
    {
        $namespace = $this->getNamespaceFromFile($file->getPathname());
        $className = $namespace . '\\' . $file->getFilenameWithoutExtension();

        if (!class_exists($className)) {
            return null;
        }

        try {
            // Check if the class is abstract before trying to instantiate it
            $reflection = new \ReflectionClass($className);
            if ($reflection->isAbstract()) {
                // Skip abstract classes as they cannot be instantiated
                return null;
            }

            $model = new $className();

            if (!$model instanceof \Illuminate\Database\Eloquent\Model) {
                return null;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                return null;
            }

            // Get fillable/guarded properties
            $fillable = $model->getFillable();
            $guarded = $model->getGuarded();

            // Get table name
            $tableName = $model->getTable();

            // Get database columns
            $columns = $this->getTableColumns($tableName);

            // Parse validation rules
            $validationRules = $this->parseValidationRules($content);

            // Determine required fields from validation rules
            $requiredFields = $this->getRequiredFieldsFromRules($validationRules);

            // Determine field types from database
            $fieldTypes = [];
            foreach ($columns as $column => $info) {
                $fieldTypes[$column] = $info['type'];
            }

            return [
                'model_name' => class_basename($className),
                'class_name' => $className,
                'table_name' => $tableName,
                'fillable' => $fillable,
                'guarded' => $guarded,
                'columns' => array_keys($columns),
                'field_types' => $fieldTypes,
                'validation_rules' => $validationRules,
                'required_fields' => $requiredFields,
                'file_path' => $file->getPathname()
            ];
        } catch (\Exception $e) {
            // Skip models that can't be analyzed
            return null;
        }
    }

    protected function parseValidationRules(string $content): array
    {
        $rules = [];

        // Check for static $rules property
        if (preg_match('/public static \$rules\s*=\s*\[([^\]]+)\]/s', $content, $matches)) {
            $rulesContent = $matches[1];
            $rules = $this->parseRulesArray($rulesContent);
        }

        return $rules;
    }

    protected function parseRulesArray(string $rulesContent): array
    {
        $rules = [];

        // Simple regex to parse 'field' => 'rules' patterns
        if (preg_match_all("/'([^']+)'\s*=>\s*['\"]([^'\"]*)['\"]/", $rulesContent, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $field = $matches[1][$i];
                $ruleString = $matches[2][$i];
                $rules[$field] = explode('|', $ruleString);
            }
        }

        return $rules;
    }

    protected function getRequiredFieldsFromRules(array $validationRules): array
    {
        $required = [];

        foreach ($validationRules as $field => $rules) {
            if (in_array('required', $rules)) {
                $required[] = $field;
            }
        }

        return $required;
    }

    protected function getTableColumns(string $tableName): array
    {
        try {
            $columns = [];
            $tableColumns = \Illuminate\Support\Facades\DB::select("DESCRIBE `{$tableName}`");

            foreach ($tableColumns as $column) {
                $columns[$column->Field] = [
                    'type' => $column->Type,
                    'null' => $column->Null === 'YES',
                    'default' => $column->Default,
                    'auto_increment' => strpos($column->Extra, 'auto_increment') !== false
                ];
            }

            return $columns;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function checkBladeFormAmendments(array $modelFields): void
    {
        $viewPath = resource_path('views');

        if (!File::exists($viewPath)) {
            return;
        }

        $bladeFiles = File::allFiles($viewPath);

        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'blade.php') {
                $this->checkBladeFormForAmendments($file, $modelFields);
            }
        }
    }

    protected function checkBladeFormForAmendments(\SplFileInfo $file, array $modelFields): void
    {
        $content = File::get($file->getPathname());
        $fileName = $file->getFilename();
        $filePath = $file->getPathname();

        // Only check files that contain forms
        if (!preg_match('/<form[^>]*>/i', $content)) {
            return;
        }

        // Try to identify the model this form is for
        $associatedModel = $this->identifyFormModel($content, $fileName, $modelFields);

        if (!$associatedModel) {
            return; // Can't determine model, skip amendment checks
        }

        $modelData = $modelFields[$associatedModel];

        // Check for missing required fields
        $this->checkMissingRequiredFields($content, $fileName, $filePath, $modelData);

        // Check for incorrect field requirements
        $this->checkIncorrectFieldRequirements($content, $fileName, $filePath, $modelData);

        // Check for missing fields that should be fillable
        $this->checkMissingFillableFields($content, $fileName, $filePath, $modelData);

        // Suggest field improvements based on database types
        $this->suggestFieldTypeImprovements($content, $fileName, $filePath, $modelData);
    }

    protected function identifyFormModel(string $content, string $fileName, array $modelFields): ?string
    {
        // Look for model references in the form
        $modelNames = array_keys($modelFields);

        // Check for model variable names (e.g., $user, $post, $product)
        foreach ($modelNames as $modelName) {
            $variableName = '$' . Str::camel($modelName);

            if (preg_match("/\\{$variableName}[\[\.]/", $content)) {
                return $modelName;
            }

            // Check for model binding in routes or actions
            if (preg_match("/route\([^)]*{$variableName}[^)]*\)/", $content)) {
                return $modelName;
            }
        }

        // Check for form action URLs that might indicate the model
        foreach ($modelNames as $modelName) {
            $modelRoute = Str::plural(Str::snake($modelName));
            if (preg_match("/action\s*=\s*['\"][^'\"]*{$modelRoute}[^'\"]*['\"]/i", $content)) {
                return $modelName;
            }
        }

        // Check filename for model hints
        foreach ($modelNames as $modelName) {
            if (stripos($fileName, Str::snake($modelName)) !== false) {
                return $modelName;
            }
        }

        return null;
    }

    protected function checkMissingRequiredFields(string $content, string $fileName, string $filePath, array $modelData): void
    {
        $requiredFields = $modelData['required_fields'];
        $formFields = $this->extractFormFields($content);

        $missingRequired = array_diff($requiredFields, $formFields);

        foreach ($missingRequired as $field) {
            $this->addIssue('forms', 'missing_required_field', [
                'file' => $filePath,
                'filename' => $fileName,
                'model' => $modelData['model_name'],
                'field' => $field,
                'message' => "Required field '{$field}' is missing from the form. Add an input field for this required field.",
                'suggestion' => "Add: <input type=\"text\" name=\"{$field}\" value=\"{{ old('{$field}') }}\" required>"
            ]);
        }
    }

    protected function checkIncorrectFieldRequirements(string $content, string $fileName, string $filePath, array $modelData): void
    {
        $requiredFields = $modelData['required_fields'];
        $formFields = $this->extractFormFields($content);
        $requiredFormFields = $this->extractRequiredFormFields($content);

        // Fields marked as required in form but not required by validation
        $incorrectlyRequired = array_diff($requiredFormFields, $requiredFields);

        foreach ($incorrectlyRequired as $field) {
            $this->addIssue('forms', 'incorrectly_required_field', [
                'file' => $filePath,
                'filename' => $fileName,
                'model' => $modelData['model_name'],
                'field' => $field,
                'message' => "Field '{$field}' is marked as required in the form but validation rules don't require it.",
                'suggestion' => "Remove the 'required' attribute from the {$field} input field."
            ]);
        }

        // Required fields not marked as required in form
        $missingRequiredAttr = array_diff($requiredFields, $requiredFormFields);

        foreach ($missingRequiredAttr as $field) {
            if (in_array($field, $formFields)) {
                $this->addIssue('forms', 'missing_required_attribute', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'model' => $modelData['model_name'],
                    'field' => $field,
                    'message' => "Required field '{$field}' exists in form but is not marked as required.",
                    'suggestion' => "Add 'required' attribute to the {$field} input field."
                ]);
            }
        }
    }

    protected function checkMissingFillableFields(string $content, string $fileName, string $filePath, array $modelData): void
    {
        $fillable = $modelData['fillable'];
        $columns = $modelData['columns'];
        $formFields = $this->extractFormFields($content);

        // If model has fillable properties, check for missing ones
        if (!empty($fillable)) {
            $missingFillable = array_diff($fillable, $formFields);

            // Exclude common non-form fields
            $excludeFields = ['id', 'created_at', 'updated_at', 'deleted_at'];
            $missingFillable = array_diff($missingFillable, $excludeFields);

            // Exclude foreign key fields ending with '_id' unless explicitly included in config
            $includedFields = $this->config['included_fields'] ?? [];
            $missingFillable = array_filter($missingFillable, function ($field) use ($includedFields) {
                if (substr($field, -3) === '_id') {
                    return in_array($field, $includedFields);
                }
                return true;
            });

            foreach ($missingFillable as $field) {
                $this->addIssue('forms', 'missing_fillable_field', [
                    'file' => $filePath,
                    'filename' => $fileName,
                    'model' => $modelData['model_name'],
                    'field' => $field,
                    'message' => "Fillable field '{$field}' is not present in the form.",
                    'suggestion' => "Consider adding an input field for fillable field '{$field}' if it should be editable."
                ]);
            }
        }
    }

    protected function suggestFieldTypeImprovements(string $content, string $fileName, string $filePath, array $modelData): void
    {
        $fieldTypes = $modelData['field_types'];
        $formFields = $this->extractFormFieldsWithTypes($content);

        foreach ($formFields as $fieldName => $fieldInfo) {
            if (isset($fieldTypes[$fieldName])) {
                $dbType = $fieldTypes[$fieldName];
                $formType = $fieldInfo['type'];

                // Suggest improvements based on database type
                $suggestion = $this->getFieldTypeSuggestion($fieldName, $dbType, $formType);

                if ($suggestion) {
                    $this->addIssue('forms', 'field_type_suggestion', [
                        'file' => $filePath,
                        'filename' => $fileName,
                        'model' => $modelData['model_name'],
                        'field' => $fieldName,
                        'current_type' => $formType,
                        'suggested_type' => $suggestion['type'],
                        'message' => $suggestion['message']
                    ]);
                }
            }
        }
    }

    protected function getFieldTypeSuggestion(string $fieldName, string $dbType, string $formType): ?array
    {
        // Email fields
        if (stripos($fieldName, 'email') !== false && $formType !== 'email') {
            return [
                'type' => 'email',
                'message' => "Field '{$fieldName}' appears to be an email field. Consider using input type='email' for better validation and UX."
            ];
        }

        // Password fields
        if (stripos($fieldName, 'password') !== false && $formType !== 'password') {
            return [
                'type' => 'password',
                'message' => "Field '{$fieldName}' appears to be a password field. Use input type='password' for security."
            ];
        }

        // Numeric fields
        if ((stripos($dbType, 'int') !== false || stripos($dbType, 'decimal') !== false) && $formType !== 'number') {
            return [
                'type' => 'number',
                'message' => "Field '{$fieldName}' is numeric in database. Consider using input type='number' for better UX."
            ];
        }

        // Date/time fields
        if (stripos($dbType, 'date') !== false && $formType !== 'date' && $formType !== 'datetime-local') {
            return [
                'type' => 'date',
                'message' => "Field '{$fieldName}' is a date field. Consider using input type='date' for better UX."
            ];
        }

        // Text areas for long text
        if (
            (stripos($dbType, 'text') !== false || stripos($dbType, 'varchar') !== false) &&
            strlen($dbType) > 10 && $formType === 'text'
        ) { // Rough check for longer text fields
            return [
                'type' => 'textarea',
                'message' => "Field '{$fieldName}' appears to store longer text. Consider using a textarea instead of input."
            ];
        }

        return null;
    }

    protected function extractFormFields(string $content): array
    {
        $fields = [];

        // Extract input names
        if (preg_match_all('/<input[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $fields = array_merge($fields, $matches[1]);
        }

        // Extract textarea names
        if (preg_match_all('/<textarea[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $fields = array_merge($fields, $matches[1]);
        }

        // Extract select names
        if (preg_match_all('/<select[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $fields = array_merge($fields, $matches[1]);
        }

        return array_unique($fields);
    }

    protected function extractRequiredFormFields(string $content): array
    {
        $requiredFields = [];

        // Extract required input fields
        if (preg_match_all('/<input[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*(?:required|aria-required\s*=\s*["\']true["\'])[^>]*>/i', $content, $matches)) {
            $requiredFields = array_merge($requiredFields, $matches[1]);
        }

        // Extract required textarea fields
        if (preg_match_all('/<textarea[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*(?:required|aria-required\s*=\s*["\']true["\'])[^>]*>/i', $content, $matches)) {
            $requiredFields = array_merge($requiredFields, $matches[1]);
        }

        // Extract required select fields
        if (preg_match_all('/<select[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*(?:required|aria-required\s*=\s*["\']true["\'])[^>]*>/i', $content, $matches)) {
            $requiredFields = array_merge($requiredFields, $matches[1]);
        }

        return array_unique($requiredFields);
    }

    protected function extractFormFieldsWithTypes(string $content): array
    {
        $fields = [];

        // Extract input fields with types
        if (preg_match_all('/<input[^>]*type\s*=\s*["\']([^"\']+)["\'][^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $type = $matches[1][$i];
                $name = $matches[2][$i];
                $fields[$name] = ['type' => $type, 'element' => 'input'];
            }
        }

        // Extract textarea fields
        if (preg_match_all('/<textarea[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $name) {
                $fields[$name] = ['type' => 'textarea', 'element' => 'textarea'];
            }
        }

        // Extract select fields
        if (preg_match_all('/<select[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $name) {
                $fields[$name] = ['type' => 'select', 'element' => 'select'];
            }
        }

        return $fields;
    }

    protected function checkLivewireFormAmendments(array $modelFields): void
    {
        $livewirePath = app_path('Livewire');

        if (!File::exists($livewirePath)) {
            return;
        }

        $livewireFiles = File::allFiles($livewirePath);

        foreach ($livewireFiles as $file) {
            if ($file->getExtension() === 'php') {
                $this->checkLivewireFormForAmendments($file, $modelFields);
            }
        }
    }

    protected function checkLivewireFormForAmendments(\Symfony\Component\Finder\SplFileInfo $file, array $modelFields): void
    {
        $content = File::get($file->getPathname());
        $className = $file->getFilenameWithoutExtension();
        $filePath = $file->getPathname();

        // Try to identify the model this component is for
        $associatedModel = $this->identifyLivewireModel($content, $className, $modelFields);

        if (!$associatedModel) {
            return;
        }

        $modelData = $modelFields[$associatedModel];

        // Check Livewire properties against model fields
        $this->checkLivewirePropertyFields($content, $className, $filePath, $modelData);

        // Check for missing validation rules in Livewire
        $this->checkLivewireValidationRules($content, $className, $filePath, $modelData);
    }

    protected function identifyLivewireModel(string $content, string $className, array $modelFields): ?string
    {
        $modelNames = array_keys($modelFields);

        // Check for model properties
        foreach ($modelNames as $modelName) {
            $propertyName = '$' . Str::camel($modelName);
            if (preg_match("/public\s+{$propertyName}\s*;/", $content)) {
                return $modelName;
            }
        }

        // Check class name for model hints
        foreach ($modelNames as $modelName) {
            if (stripos($className, $modelName) !== false) {
                return $modelName;
            }
        }

        return null;
    }

    protected function checkLivewirePropertyFields(string $content, string $className, string $filePath, array $modelData): void
    {
        // Extract public properties
        $properties = [];
        if (preg_match_all('/public\s+\$([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches)) {
            $properties = $matches[1];
        }

        $fillable = $modelData['fillable'];
        $requiredFields = $modelData['required_fields'];

        // Check for missing fillable properties
        if (!empty($fillable)) {
            $missingProperties = array_diff($fillable, $properties);

            // Exclude common non-property fields
            $excludeFields = ['id', 'created_at', 'updated_at', 'deleted_at'];
            $missingProperties = array_diff($missingProperties, $excludeFields);

            // Exclude foreign key fields ending with '_id' unless explicitly included in config
            $includedFields = $this->config['included_fields'] ?? [];
            $missingProperties = array_filter($missingProperties, function ($property) use ($includedFields) {
                if (substr($property, -3) === '_id') {
                    return in_array($property, $includedFields);
                }
                return true;
            });

            foreach ($missingProperties as $property) {
                $this->addIssue('forms', 'missing_livewire_property', [
                    'file' => $filePath,
                    'component' => $className,
                    'model' => $modelData['model_name'],
                    'property' => $property,
                    'message' => "Fillable property '{$property}' is missing from Livewire component.",
                    'suggestion' => "Add: public \${$property}; to the component properties."
                ]);
            }
        }

        // Check for required fields without validation
        $rules = $this->parseLivewireRules($content);
        foreach ($requiredFields as $field) {
            if (!isset($rules[$field])) {
                $this->addIssue('forms', 'missing_livewire_validation', [
                    'file' => $filePath,
                    'component' => $className,
                    'model' => $modelData['model_name'],
                    'field' => $field,
                    'message' => "Required field '{$field}' is missing validation rules in Livewire component.",
                    'suggestion' => "Add '{$field}' => 'required' to the validation rules."
                ]);
            }
        }
    }

    protected function checkLivewireValidationRules(string $content, string $className, string $filePath, array $modelData): void
    {
        $rules = $this->parseLivewireRules($content);
        $requiredFields = $modelData['required_fields'];

        // Check for validation rules that don't match model requirements
        foreach ($rules as $field => $ruleString) {
            // Handle both string and array rule formats
            $fieldRules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);

            if (in_array('required', $fieldRules) && !in_array($field, $requiredFields)) {
                $this->addIssue('forms', 'unnecessary_livewire_validation', [
                    'file' => $filePath,
                    'component' => $className,
                    'model' => $modelData['model_name'],
                    'field' => $field,
                    'message' => "Field '{$field}' has 'required' validation but model doesn't require it.",
                    'suggestion' => "Remove 'required' from the validation rules for '{$field}'."
                ]);
            }

            if (!in_array('required', $fieldRules) && in_array($field, $requiredFields)) {
                $this->addIssue('forms', 'missing_required_livewire_validation', [
                    'file' => $filePath,
                    'component' => $className,
                    'model' => $modelData['model_name'],
                    'field' => $field,
                    'message' => "Required field '{$field}' is missing 'required' validation rule.",
                    'suggestion' => "Add 'required' to the validation rules for '{$field}'."
                ]);
            }
        }
    }

    protected function parseLivewireRules(string $content): array
    {
        $rules = [];

        // Check for rules property
        if (preg_match('/protected\s+\$rules\s*=\s*\[([^\]]+)\]/s', $content, $matches)) {
            $rulesContent = $matches[1];
            $rules = $this->parseRulesArray($rulesContent);
        }

        // Check for rules method
        if (preg_match('/public\s+function\s+rules\(\)\s*\{([\s\S]*?)\}/', $content, $matches)) {
            $rulesMethodContent = $matches[1];
            // Simple parsing - could be improved
            if (preg_match_all("/'([^']+)'\s*=>\s*['\"]([^'\"]*)['\"]/", $rulesMethodContent, $matches)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $field = $matches[1][$i];
                    $ruleString = $matches[2][$i];
                    $rules[$field] = $ruleString;
                }
            }
        }

        return $rules;
    }

    protected function getNamespaceFromFile(string $filePath): string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return '';
        }

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function offerAutomaticFixes(): void
    {
        if (empty($this->issues)) {
            return;
        }

        $fixableIssues = $this->getFixableIssues();

        if (empty($fixableIssues)) {
            return;
        }

        $this->info('');
        $this->info('🔧 Automatic Form Fixes Available');
        $this->info('================================');

        $this->info("Found " . count($fixableIssues) . " issues that can be automatically fixed:");

        foreach ($fixableIssues as $index => $issue) {
            $this->info("  " . ($index + 1) . ". " . $issue['message']);
        }

        $this->info('');
        $this->info('Would you like me to automatically fix these issues? (y/N): ');

        // In a real implementation, this would read user input
        // For now, we'll show what would be fixed
        $this->showFixPreview($fixableIssues);
    }

    protected function getFixableIssues(): array
    {
        $fixableCategories = [
            'forms.missing_required_field',
            'forms.incorrectly_required_field',
            'forms.missing_required_attribute',
            'forms.missing_fillable_field',
            'forms.field_type_suggestion',
            'forms.missing_livewire_property',
            'forms.missing_livewire_validation',
            'forms.missing_required_livewire_validation',
            'forms.unnecessary_livewire_validation'
        ];

        return array_filter($this->issues, function ($issue) use ($fixableCategories) {
            return in_array($issue['category'] . '.' . $issue['type'], $fixableCategories);
        });
    }

    protected function showFixPreview(array $fixableIssues): void
    {
        $this->info('');
        $this->info('📋 Fix Preview:');
        $this->info('==============');

        $filesToModify = [];

        foreach ($fixableIssues as $issue) {
            $file = $issue['details']['file'] ?? '';
            if (!isset($filesToModify[$file])) {
                $filesToModify[$file] = [];
            }
            $filesToModify[$file][] = $issue;
        }

        foreach ($filesToModify as $file => $issues) {
            $this->info("📄 File: " . basename($file));
            foreach ($issues as $issue) {
                $this->showIssueFix($issue);
            }
            $this->info('');
        }

        $this->info('💡 To apply these fixes automatically, run with --fix-forms option');
        $this->info('   Example: php artisan model:schema-check --fix-forms');
        $this->info('');
        $this->info('⚠️  Note: Automatic fixes require file write permissions and must be enabled in config.');
        $this->info('   Set \'output.allow_file_writes\' => true in config/model-schema-checker.php');
    }

    protected function showIssueFix(array $issue): void
    {
        $type = $issue['type'];
        $details = $issue['details'] ?? [];

        if (empty($details)) {
            $this->info("  ⚠️  Cannot show fix details for issue type: {$type}");
            return;
        }

        switch ($type) {
            case 'missing_required_field':
                $this->info("  ✅ Add required field: {$details['field']}");
                $this->info("     Suggestion: {$details['suggestion']}");
                break;

            case 'incorrectly_required_field':
                $this->info("  ✅ Remove 'required' from field: {$details['field']}");
                break;

            case 'missing_required_attribute':
                $this->info("  ✅ Add 'required' attribute to field: {$details['field']}");
                break;

            case 'missing_fillable_field':
                $this->info("  ✅ Add fillable field: {$details['field']}");
                $this->info("     Suggestion: Consider adding input for fillable field");
                break;

            case 'field_type_suggestion':
                $this->info("  ✅ Change input type for {$details['field']} from '{$details['current_type']}' to '{$details['suggested_type']}'");
                break;

            case 'missing_livewire_property':
                $this->info("  ✅ Add public property: \${$details['property']}");
                break;

            case 'missing_livewire_validation':
            case 'missing_required_livewire_validation':
                $this->info("  ✅ Add validation rule: '{$details['field']}' => 'required'");
                break;

            case 'unnecessary_livewire_validation':
                $this->info("  ✅ Remove 'required' from validation rule: {$details['field']}");
                break;
        }
    }

    public function applyAutomaticFixes(): array
    {
        $results = [
            'fixed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => []
        ];

        $fixableIssues = $this->getFixableIssues();

        if (empty($fixableIssues)) {
            return $results;
        }

        $this->info('🔧 Applying Automatic Form Fixes...');

        // Group issues by file
        $filesToModify = [];
        foreach ($fixableIssues as $issue) {
            $file = $issue['details']['file'] ?? '';
            if (!isset($filesToModify[$file])) {
                $filesToModify[$file] = [];
            }
            $filesToModify[$file][] = $issue;
        }

        foreach ($filesToModify as $file => $issues) {
            $this->info("📄 Processing: " . basename($file));

            // Check if file writes are allowed in config
            if (!($this->config['output']['allow_file_writes'] ?? false)) {
                $results['errors']++;
                $results['details'][] = basename($file) . ": File writes are disabled in configuration. Set 'output.allow_file_writes' to true to enable automatic fixes.";
                continue;
            }

            // Check if file is writable
            if (!is_writable($file)) {
                $results['errors']++;
                $results['details'][] = basename($file) . ": File is not writable";
                continue;
            }

            try {
                $content = File::get($file);
                $originalContent = $content;
                $modified = false;

                foreach ($issues as $issue) {
                    $fixResult = $this->applyIssueFix($content, $issue);
                    if ($fixResult['applied']) {
                        $content = $fixResult['content'];
                        $modified = true;
                        $results['fixed']++;
                        $results['details'][] = basename($file) . ": " . $fixResult['action'];
                    } else {
                        $results['skipped']++;
                    }
                }

                if ($modified && $content !== $originalContent) {
                    // Create backup
                    $backupFile = $file . '.backup.' . date('Y-m-d-H-i-s');
                    File::put($backupFile, $originalContent);

                    // Apply changes
                    File::put($file, $content);
                    $this->info("  ✅ Fixed " . count($issues) . " issues (backup created: " . basename($backupFile) . ")");
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $this->error("  ❌ Error processing {$file}: " . $e->getMessage());
            }
        }

        return $results;
    }

    protected function applyIssueFix(string $content, array $issue): array
    {
        $type = $issue['type'];
        $details = $issue['details'] ?? [];

        if (empty($details)) {
            return [
                'applied' => false,
                'content' => $content,
                'action' => "Skipped - missing details for issue type: {$type}"
            ];
        }

        switch ($type) {
            case 'missing_required_field':
                return $this->fixMissingRequiredField($content, $details);

            case 'incorrectly_required_field':
                return $this->fixIncorrectlyRequiredField($content, $details);

            case 'missing_required_attribute':
                return $this->fixMissingRequiredAttribute($content, $details);

            case 'field_type_suggestion':
                return $this->fixFieldTypeSuggestion($content, $details);

            case 'missing_livewire_property':
                return $this->fixMissingLivewireProperty($content, $details);

            case 'missing_livewire_validation':
            case 'missing_required_livewire_validation':
                return $this->fixMissingLivewireValidation($content, $details);

            case 'unnecessary_livewire_validation':
                return $this->fixUnnecessaryLivewireValidation($content, $details);

            default:
                return ['applied' => false, 'content' => $content, 'action' => 'not supported'];
        }
    }

    protected function fixMissingRequiredField(string $content, array $details): array
    {
        $field = $details['field'];

        // Find the form and add the field before the closing </form> tag
        $pattern = '/<\/form>/i';
        $replacement = "    <div class=\"form-group\">\n";
        $replacement .= "        <label for=\"{$field}\">{$field}</label>\n";
        $replacement .= "        <input type=\"text\" name=\"{$field}\" id=\"{$field}\" value=\"{{ old('{$field}') }}\" required>\n";
        $replacement .= "        @error('{$field}') <div class=\"text-danger\">{{ \$message }}</div> @enderror\n";
        $replacement .= "    </div>\n";
        $replacement .= "</form>";

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, $replacement, $content, 1);
            return [
                'applied' => true,
                'content' => $newContent,
                'action' => "Added required field '{$field}' to form"
            ];
        }

        return ['applied' => false, 'content' => $content, 'action' => 'could not find form closing tag'];
    }

    protected function fixIncorrectlyRequiredField(string $content, array $details): array
    {
        $field = $details['field'];

        // Remove required attribute from input/textarea/select
        $patterns = [
            '/(<input[^>]*name\s*=\s*["\']' . preg_quote($field, '/') . '["\'][^>]*)\s+required([^>]*>)/i',
            '/(<textarea[^>]*name\s*=\s*["\']' . preg_quote($field, '/') . '["\'][^>]*)\s+required([^>]*>)/i',
            '/(<select[^>]*name\s*=\s*["\']' . preg_quote($field, '/') . '["\'][^>]*)\s+required([^>]*>)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $newContent = preg_replace($pattern, '$1$2', $content);
                return [
                    'applied' => true,
                    'content' => $newContent,
                    'action' => "Removed 'required' attribute from field '{$field}'"
                ];
            }
        }

        return ['applied' => false, 'content' => $content, 'action' => 'required attribute not found'];
    }

    protected function fixMissingRequiredAttribute(string $content, array $details): array
    {
        $field = $details['field'];

        // Add required attribute to input/textarea/select
        $patterns = [
            '/(<input[^>]*name\s*=\s*["\']' . preg_quote($field, '/') . '["\'][^>]*)(>)/i',
            '/(<textarea[^>]*name\s*=\s*["\']' . preg_quote($field, '/') . '["\'][^>]*)(>)/i',
            '/(<select[^>]*name\s*=\s*["\']' . preg_quote($field, '/') . '["\'][^>]*)(>)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $newContent = preg_replace($pattern, '$1 required$2', $content);
                return [
                    'applied' => true,
                    'content' => $newContent,
                    'action' => "Added 'required' attribute to field '{$field}'"
                ];
            }
        }

        return ['applied' => false, 'content' => $content, 'action' => 'field not found'];
    }

    protected function fixFieldTypeSuggestion(string $content, array $details): array
    {
        $field = $details['field'];
        $newType = $details['suggested_type'];

        // Change input type
        $pattern = '/(<input[^>]*name\s*=\s*["\']' . preg_quote($field, '/') . '["\'][^>]*type\s*=\s*["\'])[^"\']+(["\'][^>]*>)/i';

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, '$1' . $newType . '$2', $content);
            return [
                'applied' => true,
                'content' => $newContent,
                'action' => "Changed input type for '{$field}' to '{$newType}'"
            ];
        }

        return ['applied' => false, 'content' => $content, 'action' => 'input field not found'];
    }

    protected function fixMissingLivewireProperty(string $content, array $details): array
    {
        $property = $details['property'];

        // Find the class declaration and opening brace (handles multiline)
        $pattern = '/(class\s+\w+\s+extends\s+[\w\\\\]+[\s\S]*?\{)/';

        if (preg_match($pattern, $content, $matches)) {
            $classDeclaration = $matches[1];
            $replacement = $classDeclaration . "\n    public \$" . $property . ";\n";
            $newContent = preg_replace($pattern, $replacement, $content, 1);

            return [
                'applied' => true,
                'content' => $newContent,
                'action' => "Added public property '\${$property}' to Livewire component"
            ];
        }

        return ['applied' => false, 'content' => $content, 'action' => 'class declaration not found'];
    }

    protected function fixMissingLivewireValidation(string $content, array $details): array
    {
        $field = $details['field'];

        // Find rules property and add the field
        $pattern = '/(protected\s+\$rules\s*=\s*\[)/';

        if (preg_match($pattern, $content)) {
            $replacement = '$1' . "\n            '{$field}' => 'required',";
            $newContent = preg_replace($pattern, $replacement, $content, 1);

            return [
                'applied' => true,
                'content' => $newContent,
                'action' => "Added validation rule for '{$field}' to Livewire component"
            ];
        }

        return ['applied' => false, 'content' => $content, 'action' => 'rules property not found'];
    }

    protected function fixUnnecessaryLivewireValidation(string $content, array $details): array
    {
        $field = $details['field'];

        // Remove 'required' from rules for this field
        $pattern = '/(\'' . preg_quote($field, '/') . '\'\s*=>\s*[\'"])([^\'"]*?)required([^\'"]*?)([\'"])/';

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, '$1$2$3$4', $content);
            if ($newContent === null) {
                return ['applied' => false, 'content' => $content, 'action' => 'Failed to remove required rule'];
            }
            // Clean up any double pipes or leading/trailing pipes
            $newContent = preg_replace('/(\'' . preg_quote($field, '/') . '\'\s*=>\s*[\'"])\|+(\w)/', '$1$2', $newContent);
            if ($newContent === null) {
                return ['applied' => false, 'content' => $content, 'action' => 'Failed to clean up pipes'];
            }
            $newContent = preg_replace('/(\w)\|+(\w)/', '$1|$2', $newContent);

            return [
                'applied' => true,
                'content' => $newContent,
                'action' => "Removed 'required' from validation rule for '{$field}'"
            ];
        }

        return ['applied' => false, 'content' => $content, 'action' => 'required rule not found'];
    }
}
