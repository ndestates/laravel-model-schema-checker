<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Checkers\LaravelFormsChecker;

class LaravelFormsCheckerAutomaticFixTest extends TestCase
{
    private LaravelFormsChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new LaravelFormsChecker();
    }

    public function test_fix_missing_required_field_adds_field_to_form()
    {
        $formContent = '<form method="POST" action="/users">
    @csrf
    <div class="form-group">
        <label for="existing">Existing Field</label>
        <input type="text" name="existing" value="{{ old(\'existing\') }}">
    </div>
</form>';

        $issue = [
            'type' => 'missing_required_field',
            'details' => [
                'field' => 'name'
            ]
        ];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('applyIssueFix');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, $formContent, $issue);

        // Verify the fix was applied
        $this->assertTrue($result['applied'], 'Fix should be applied');
        $this->assertStringContainsString('Added required field \'name\' to form', $result['action']);

        // Verify the field was added to the content
        $this->assertStringContainsString('name="name"', $result['content'], 'Name field should be added');
        $this->assertStringContainsString('required', $result['content'], 'Field should have required attribute');
        $this->assertStringContainsString('@error(\'name\')', $result['content'], 'Error handling should be added');
        $this->assertStringContainsString('{{ old(\'name\') }}', $result['content'], 'Old input should be preserved');
    }

    public function test_fix_missing_livewire_property_adds_property_to_component()
    {
        $componentContent = '<?php

namespace App\Livewire;

use Livewire\Component;

class TestForm extends Component
{
    public $existingProperty;

    public function save()
    {
        // Save logic
    }

    public function render()
    {
        return view(\'test-form\');
    }
}
';

        $issue = [
            'type' => 'missing_livewire_property',
            'details' => [
                'property' => 'name'
            ]
        ];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('applyIssueFix');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, $componentContent, $issue);

        // Verify the fix was applied
        $this->assertTrue($result['applied'], 'Fix should be applied');
        $this->assertStringContainsString('Added public property \'$name\' to Livewire component', $result['action']);

        // Verify the property was added
        $this->assertStringContainsString('public $name;', $result['content'], 'Name property should be added');
        $this->assertStringContainsString('public $existingProperty;', $result['content'], 'Existing property should remain');
    }

    public function test_fix_incorrectly_required_field_removes_required_attribute()
    {
        $formContent = '<form method="POST">
    @csrf
    <input type="text" name="name" value="{{ old(\'name\') }}" required>
    <input type="email" name="email" value="{{ old(\'email\') }}">
</form>';

        $issue = [
            'type' => 'incorrectly_required_field',
            'details' => [
                'field' => 'name'
            ]
        ];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('applyIssueFix');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, $formContent, $issue);

        // Verify the fix was applied
        $this->assertTrue($result['applied'], 'Fix should be applied');
        $this->assertStringContainsString('Removed \'required\' attribute from field \'name\'', $result['action']);

        // Verify the required attribute was removed
        $this->assertStringNotContainsString('name="name" value="{{ old(\'name\') }}" required', $result['content'], 'Required attribute should be removed');
        $this->assertStringContainsString('name="name" value="{{ old(\'name\') }}"', $result['content'], 'Field should remain without required');
    }

    public function test_fix_missing_required_attribute_adds_required_to_existing_field()
    {
        $formContent = '<form method="POST">
    @csrf
    <input type="text" name="name" value="{{ old(\'name\') }}">
    <input type="email" name="email" value="{{ old(\'email\') }}" required>
</form>';

        $issue = [
            'type' => 'missing_required_attribute',
            'details' => [
                'field' => 'name'
            ]
        ];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('applyIssueFix');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, $formContent, $issue);

        // Verify the fix was applied
        $this->assertTrue($result['applied'], 'Fix should be applied');
        $this->assertStringContainsString('Added \'required\' attribute to field \'name\'', $result['action']);

        // Verify the required attribute was added
        $this->assertStringContainsString('name="name" value="{{ old(\'name\') }}" required', $result['content'], 'Required attribute should be added');
        $this->assertStringContainsString('name="email" value="{{ old(\'email\') }}" required', $result['content'], 'Existing required field should remain');
    }

    public function test_fix_field_type_suggestion_updates_input_type()
    {
        $formContent = '<form method="POST">
    @csrf
    <input type="text" name="email" value="{{ old(\'email\') }}">
</form>';

        $issue = [
            'type' => 'field_type_suggestion',
            'details' => [
                'field' => 'email',
                'suggested_type' => 'email'
            ]
        ];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('applyIssueFix');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, $formContent, $issue);

        // Verify the fix was applied
        $this->assertTrue($result['applied'], 'Fix should be applied');
        $this->assertStringContainsString('Changed input type for \'email\' to \'email\'', $result['action']);

        // Verify the input type was changed
        $this->assertStringContainsString('type="email"', $result['content'], 'Input type should be changed to email');
        $this->assertStringNotContainsString('type="text"', $result['content'], 'Old text type should be removed');
    }

    public function test_fix_missing_livewire_validation_adds_validation_rule()
    {
        $componentContent = '<?php

namespace App\Livewire;

use Livewire\Component;

class TestForm extends Component
{
    public $name;

    protected $rules = [
        "name" => "required|string",
    ];

    public function save()
    {
        $this->validate();
    }
}
';

        $issue = [
            'type' => 'missing_livewire_validation',
            'details' => [
                'field' => 'email'
            ]
        ];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('applyIssueFix');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, $componentContent, $issue);

        // Verify the fix was applied
        $this->assertTrue($result['applied'], 'Fix should be applied');
        $this->assertStringContainsString('Added validation rule for \'email\' to Livewire component', $result['action']);

        // Verify the validation rule was added
        $this->assertStringContainsString("'email' => 'required'", $result['content'], 'Email validation rule should be added');
    }
}