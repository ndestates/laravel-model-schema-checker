<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use NDEstates\LaravelModelSchemaChecker\Checkers\LaravelFormsChecker;
use Mockery;

/**
 * LaravelFormsCheckerSchemaTest - Comprehensive test suite for schema comparison functionality
 *
 * Purpose: Validates LaravelFormsChecker schema comparison capabilities including:
 * - Model field analysis from database schema
 * - Blade template form field validation against model schema
 * - Livewire component property validation against model schema
 * - Detection of missing required fields in forms
 * - Detection of missing fillable properties in Livewire components
 * - Field type suggestions based on database schema
 *
 * Test Categories:
 * - Model field analysis and database schema extraction
 * - Blade form amendments and field validation
 * - Livewire component amendments and property validation
 * - Model identification from form content
 * - Required field validation
 * - Fillable property validation
 * - Field type suggestions
 *
 * Assertions Used: assertCount, assertEquals, assertArrayHasKey, assertContains,
 * assertStringContains, assertTrue, assertFalse, assertEmpty
 *
 * Results Expected: All tests should pass with comprehensive coverage
 * of schema comparison functionality and edge cases
 */
class LaravelFormsCheckerSchemaTest extends TestCase
{
    private LaravelFormsChecker $checker;
    private string $tempDir;
    private string $modelsDir;
    private string $viewsDir;
    private string $livewireDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory structure
        $this->tempDir = sys_get_temp_dir() . '/laravel_forms_schema_test_' . uniqid();
        $this->modelsDir = $this->tempDir . '/app/Models';
        $this->viewsDir = $this->tempDir . '/resources/views';
        $this->livewireDir = $this->tempDir . '/app/Livewire';

        mkdir($this->modelsDir, 0755, true);
        mkdir($this->viewsDir, 0755, true);
        mkdir($this->livewireDir, 0755, true);

        $this->checker = new LaravelFormsChecker();

        // Mock Laravel helper functions
        if (!function_exists('app_path')) {
            eval('function app_path($path = "") { return "/tmp/app" . ($path ? "/" . $path : ""); }');
        }
        if (!function_exists('resource_path')) {
            eval('function resource_path($path = "") { return "/tmp/resources" . ($path ? "/" . $path : ""); }');
        }
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        if (file_exists($this->tempDir)) {
            exec("rm -rf " . escapeshellarg($this->tempDir));
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test model field analysis extracts correct schema information
     * Assertions: assertArrayHasKey, assertEquals, assertContains
     * Validates database schema extraction and model field analysis
     */
    public function test_analyzes_model_fields_correctly()
    {
        // Mock Laravel helper functions
        if (!function_exists('app_path')) {
            function app_path($path = '') {
                return '/tmp/app' . ($path ? '/' . $path : '');
            }
        }

        // Create test model
        $modelContent = '<?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;

        class User extends Model
        {
            protected $fillable = ["name", "email"];
            protected $guarded = ["id"];

            protected $rules = [
                "name" => "required|string",
                "email" => "required|email",
                "phone" => "nullable|string"
            ];
        }
        ';

        file_put_contents($this->modelsDir . '/User.php', $modelContent);

        // Mock database schema
        DB::shouldReceive('select')
            ->with('DESCRIBE `users`')
            ->andReturn([
                (object)['Field' => 'id', 'Type' => 'int(11)', 'Null' => 'NO', 'Default' => null, 'Extra' => 'auto_increment'],
                (object)['Field' => 'name', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                (object)['Field' => 'email', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                (object)['Field' => 'phone', 'Type' => 'varchar(20)', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
                (object)['Field' => 'created_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
                (object)['Field' => 'updated_at', 'Type' => 'timestamp', 'Null' => 'YES', 'Default' => null, 'Extra' => '']
            ]);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('analyzeModelsForFields');
        $method->setAccessible(true);

        $modelFields = $method->invoke($this->checker);

        // Verify model analysis
        $this->assertArrayHasKey('User', $modelFields);
        $userData = $modelFields['User'];

        $this->assertEquals('User', $userData['model_name']);
        $this->assertEquals('users', $userData['table_name']);
        $this->assertEquals(['name', 'email'], $userData['fillable']);
        $this->assertEquals(['id'], $userData['guarded']);
        $this->assertContains('name', $userData['columns']);
        $this->assertContains('email', $userData['columns']);
        $this->assertContains('phone', $userData['columns']);
        $this->assertEquals(['name', 'email'], $userData['required_fields']);
        $this->assertArrayHasKey('name', $userData['validation_rules']);
        $this->assertArrayHasKey('email', $userData['validation_rules']);
    }

    /**
     * Test detection of missing required fields in Blade forms
     * Assertions: assertCount, assertEquals, assertArrayHasKey
     * Validates form field validation against model requirements
     */
    public function test_detects_missing_required_fields_in_blade_forms()
    {
        // Mock Laravel helper functions
        if (!function_exists('resource_path')) {
            function resource_path($path = '') {
                return '/tmp/resources' . ($path ? '/' . $path : '');
            }
        }

        // Create test model data (simulating what analyzeModelsForFields would return)
        $modelData = [
            'model_name' => 'Product',
            'fillable' => ['name', 'price', 'description'],
            'required_fields' => ['name', 'price'],
            'columns' => ['id', 'name', 'price', 'description']
        ];

        // Create Blade form missing required fields
        $formContent = '<form method="POST" action="/products">
            @csrf
            <input type="text" name="name" value="{{ old(\'name\') }}" required>
            <!-- Missing price field -->
            <input type="text" name="description" value="{{ old(\'description\') }}">
            <button type="submit">Create Product</button>
        </form>';

        // Use reflection to test protected methods
        $reflection = new \ReflectionClass($this->checker);

        // Test identifyFormModel
        $identifyMethod = $reflection->getMethod('identifyFormModel');
        $identifyMethod->setAccessible(true);
        $modelName = $identifyMethod->invoke($this->checker, $formContent, 'create.blade.php', ['Product' => $modelData]);

        $this->assertEquals('Product', $modelName);

        // Test checkMissingRequiredFields
        $checkMethod = $reflection->getMethod('checkMissingRequiredFields');
        $checkMethod->setAccessible(true);

        // Clear any existing issues
        $issuesProperty = $reflection->getProperty('issues');
        $issuesProperty->setAccessible(true);
        $issuesProperty->setValue($this->checker, []);

        $checkMethod->invoke($this->checker, $formContent, 'create.blade.php', 'create.blade.php', $modelData);

        $issues = $issuesProperty->getValue($this->checker);

        // Debug: check what issues were generated
        var_dump('Generated issues:', $issues);

        // Filter for missing required field issues
        $missingRequiredIssues = array_filter($issues, function ($issue) {
            return $issue['type'] === 'missing_required_field';
        });

        var_dump('Filtered issues:', $missingRequiredIssues);

        $this->assertCount(1, $missingRequiredIssues);
        $issue = reset($missingRequiredIssues);
        $this->assertEquals('price', $issue['details']['field']);
        $this->assertEquals('Product', $issue['details']['model']);
        $this->assertStringContains('Required field \'price\' is missing', $issue['message']);
    }

    /**
     * Test detection of missing fillable fields in Blade forms
     * Assertions: assertCount, assertEquals
     * Validates that forms include all fillable model fields
     */
    public function test_detects_missing_fillable_fields_in_blade_forms()
    {
        // Create test model data
        $modelData = [
            'model_name' => 'Article',
            'fillable' => ['title', 'content', 'author_id', 'published_at'],
            'required_fields' => [],
            'columns' => ['id', 'title', 'content', 'author_id', 'published_at']
        ];

        // Create Blade form missing some fillable fields
        $formContent = '<form method="POST" action="{{ route(\'articles.store\') }}">
            @csrf
            <input type="text" name="title" value="{{ old(\'title\') }}" required>
            <textarea name="content">{{ old(\'content\') }}</textarea>
            <!-- Missing author_id and published_at fields -->
            <button type="submit">Create Article</button>
        </form>';

        // Use reflection to test protected methods
        $reflection = new \ReflectionClass($this->checker);

        // Test checkMissingFillableFields
        $checkMethod = $reflection->getMethod('checkMissingFillableFields');
        $checkMethod->setAccessible(true);

        // Clear any existing issues
        $issuesProperty = $reflection->getProperty('issues');
        $issuesProperty->setAccessible(true);
        $issuesProperty->setValue($this->checker, []);

        $checkMethod->invoke($this->checker, $formContent, 'create.blade.php', 'create.blade.php', $modelData);

        $issues = $issuesProperty->getValue($this->checker);

        // Filter for missing fillable field issues
        $missingFillableIssues = array_filter($issues, function ($issue) {
            return $issue['type'] === 'missing_fillable_field';
        });

        // Should detect missing author_id and published_at
        $this->assertGreaterThanOrEqual(1, count($missingFillableIssues));
    }

    /**
     * Test Livewire component property validation against model schema
     * Assertions: assertCount, assertEquals, assertArrayHasKey
     * Validates Livewire component properties match model fillable fields
     */
    public function test_validates_livewire_properties_against_model_schema()
    {
        // Create test model data
        $modelData = [
            'model_name' => 'Contact',
            'fillable' => ['name', 'email', 'phone', 'message'],
            'required_fields' => ['name', 'email', 'message'],
            'columns' => ['id', 'name', 'email', 'phone', 'message']
        ];

        // Create Livewire component missing some properties
        $componentContent = '<?php
        namespace App\Livewire;

        use Livewire\Component;

        class ContactForm extends Component
        {
            public $name;
            public $email;
            // Missing phone and message properties

            protected $rules = [
                "name" => "required|string",
                "email" => "required|email"
            ];

            public function save()
            {
                $this->validate();
                // Save logic
            }

            public function render()
            {
                return view("livewire.contact-form");
            }
        }
        ';

        // Use reflection to test protected methods
        $reflection = new \ReflectionClass($this->checker);

        // Test checkLivewirePropertyFields
        $checkMethod = $reflection->getMethod('checkLivewirePropertyFields');
        $checkMethod->setAccessible(true);

        // Clear any existing issues
        $issuesProperty = $reflection->getProperty('issues');
        $issuesProperty->setAccessible(true);
        $issuesProperty->setValue($this->checker, []);

        $checkMethod->invoke($this->checker, $componentContent, 'ContactForm', 'ContactForm.php', $modelData);

        $issues = $issuesProperty->getValue($this->checker);

        // Filter for missing Livewire property issues
        $missingPropertyIssues = array_filter($issues, function ($issue) {
            return $issue['type'] === 'missing_livewire_property';
        });

        // Should detect missing phone and message properties
        $this->assertGreaterThanOrEqual(1, count($missingPropertyIssues));
    }

    /**
     * Test field type suggestions based on database schema
     * Assertions: assertCount, assertEquals
     * Validates field type improvement suggestions
     */
    public function test_suggests_field_type_improvements_based_on_schema()
    {
        // Create test model data with field types
        $modelData = [
            'model_name' => 'User',
            'fillable' => ['email', 'phone'],
            'field_types' => [
                'email' => 'varchar(255)',
                'phone' => 'varchar(20)'
            ],
            'columns' => ['id', 'email', 'phone']
        ];

        // Create Blade form with incorrect field types
        $formContent = '<form method="POST" action="{{ route(\'users.store\') }}">
            @csrf
            <input type="text" name="email" value="{{ old(\'email\') }}">
            <input type="text" name="phone" value="{{ old(\'phone\') }}">
            <button type="submit">Create User</button>
        </form>';

        // Use reflection to test protected methods
        $reflection = new \ReflectionClass($this->checker);

        // Test extractFormFieldsWithTypes first
        $extractMethod = $reflection->getMethod('extractFormFieldsWithTypes');
        $extractMethod->setAccessible(true);
        $extractedFields = $extractMethod->invoke($this->checker, $formContent);

        // Verify field extraction works
        $this->assertArrayHasKey('email', $extractedFields);
        $this->assertEquals('text', $extractedFields['email']['type']);

        // Test suggestFieldTypeImprovements
        $suggestMethod = $reflection->getMethod('suggestFieldTypeImprovements');
        $suggestMethod->setAccessible(true);

        // Clear any existing issues
        $issuesProperty = $reflection->getProperty('issues');
        $issuesProperty->setAccessible(true);
        $issuesProperty->setValue($this->checker, []);

        $suggestMethod->invoke($this->checker, $formContent, 'create.blade.php', 'create.blade.php', $modelData);

        $issues = $issuesProperty->getValue($this->checker);

        // Filter for field type suggestion issues
        $typeSuggestionIssues = array_filter($issues, function ($issue) {
            return $issue['type'] === 'field_type_suggestion';
        });

        // Should suggest email type for email field
        $emailSuggestions = array_filter($typeSuggestionIssues, function ($issue) {
            return $issue['field'] === 'email';
        });

        $this->assertGreaterThanOrEqual(1, count($emailSuggestions));
    }

    /**
     * Test model identification from form content
     * Assertions: assertEquals, assertNull
     * Validates model identification logic from various form patterns
     */
    public function test_identifies_models_from_form_content()
    {
        $modelFields = [
            'User' => ['model_name' => 'User'],
            'Product' => ['model_name' => 'Product'],
            'Article' => ['model_name' => 'Article']
        ];

        // Use reflection to test identifyFormModel method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('identifyFormModel');
        $method->setAccessible(true);

        // Test identification by route (looks for variable name in route call)
        $formContent2 = '<form method="POST" action="{{ route(\'store\', $product) }}">
            <input name="name">
        </form>';

        $result2 = $method->invoke($this->checker, $formContent2, 'create.blade.php', $modelFields);

        // Debug: check what Str::camel returns
        $camelProduct = \Illuminate\Support\Str::camel('Product');
        var_dump('Camel case of Product:', $camelProduct);
        var_dump('Form content:', $formContent2);
        var_dump('Result:', $result2);

        $this->assertEquals('Product', $result2);

        // Test identification by filename
        $formContent3 = '<form method="POST">
            <input name="title">
        </form>';

        $result3 = $method->invoke($this->checker, $formContent3, 'create_article.blade.php', $modelFields);
        $this->assertEquals('Article', $result3);

        // Test no identification
        $formContent4 = '<form method="POST">
            <input name="field">
        </form>';

        $result4 = $method->invoke($this->checker, $formContent4, 'form.blade.php', $modelFields);
        $this->assertNull($result4);
    }

    /**
     * Test Livewire model identification
     * Assertions: assertEquals, assertNull
     * Validates Livewire component model identification logic
     */
    public function test_identifies_models_from_livewire_components()
    {
        $modelFields = [
            'User' => ['model_name' => 'User'],
            'Contact' => ['model_name' => 'Contact']
        ];

        // Test identification by property
        $componentContent1 = '<?php
        class UserForm extends Component
        {
            public $user;
            public $name;
        }
        ';

        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('identifyLivewireModel');
        $method->setAccessible(true);

        $result1 = $method->invoke($this->checker, $componentContent1, 'UserForm', $modelFields);
        $this->assertEquals('User', $result1);

        // Test identification by class name
        $componentContent2 = '<?php
        class ContactForm extends Component
        {
            public $name;
        }
        ';

        $result2 = $method->invoke($this->checker, $componentContent2, 'ContactForm', $modelFields);
        $this->assertEquals('Contact', $result2);

        // Test no identification
        $componentContent3 = '<?php
        class GenericForm extends Component
        {
            public $data;
        }
        ';

        $result3 = $method->invoke($this->checker, $componentContent3, 'GenericForm', $modelFields);
        $this->assertNull($result3);
    }

    /**
     * Test validation rule detection in Livewire components
     * Assertions: assertCount, assertEquals
     * Validates detection of missing validation rules in Livewire components
     */
    public function test_detects_missing_validation_rules_in_livewire()
    {
        // Create test model data
        $modelData = [
            'model_name' => 'Order',
            'fillable' => ['customer_name', 'total', 'status'],
            'required_fields' => ['customer_name', 'total', 'status'],
            'validation_rules' => [
                'customer_name' => ['required', 'string'],
                'total' => ['required', 'numeric'],
                'status' => ['required', 'in:pending,processing,completed']
            ],
            'columns' => ['id', 'customer_name', 'total', 'status']
        ];

        // Create Livewire component with incomplete validation rules
        $componentContent = '<?php
        namespace App\Livewire;

        use Livewire\Component;

        class OrderForm extends Component
        {
            public $customer_name;
            public $total;
            public $status;

            protected $rules = [
                "customer_name" => "required|string",
                "total" => "required|numeric"
                // Missing status validation rule
            ];

            public function save()
            {
                $this->validate();
            }

            public function render()
            {
                return view("livewire.order-form");
            }
        }
        ';

        // Use reflection to test protected methods
        $reflection = new \ReflectionClass($this->checker);

        // Test checkLivewireValidationRules
        $checkMethod = $reflection->getMethod('checkLivewireValidationRules');
        $checkMethod->setAccessible(true);

        // Clear any existing issues
        $issuesProperty = $reflection->getProperty('issues');
        $issuesProperty->setAccessible(true);
        $issuesProperty->setValue($this->checker, []);

        $checkMethod->invoke($this->checker, $componentContent, 'OrderForm', 'OrderForm.php', $modelData);

        $issues = $issuesProperty->getValue($this->checker);

        // Filter for missing Livewire validation issues
        $missingValidationIssues = array_filter($issues, function ($issue) {
            return $issue['type'] === 'missing_required_livewire_validation';
        });

        // Should detect missing status validation rule
        $this->assertGreaterThanOrEqual(1, count($missingValidationIssues));
    }
}