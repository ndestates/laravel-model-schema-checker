<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Checkers\LaravelFormsChecker;

class LaravelFormsCheckerTest extends TestCase
{
    private LaravelFormsChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new LaravelFormsChecker();
    }

    public function test_is_form_component_detects_form_components()
    {
        // Test form component by name pattern
        $formContent = '<?php class IssueForm extends Component { }';
        $this->assertTrue($this->invokePrivateMethod('isFormComponent', [$formContent, 'IssueForm']));

        // Test form component by method
        $methodContent = '<?php class SomeComponent extends Component { public function save() { } }';
        $this->assertTrue($this->invokePrivateMethod('isFormComponent', [$methodContent, 'SomeComponent']));

        // Test form component by validation rules
        $rulesContent = '<?php class AnotherComponent extends Component { protected $rules = []; }';
        $this->assertTrue($this->invokePrivateMethod('isFormComponent', [$rulesContent, 'AnotherComponent']));
    }

    public function test_is_form_component_excludes_non_form_components()
    {
        // Test list component (should be excluded)
        $listContent = '<?php class UserIssuesList extends Component { public function render() { } }';
        $this->assertFalse($this->invokePrivateMethod('isFormComponent', [$listContent, 'UserIssuesList']));

        // Test widget component (should be excluded)
        $widgetContent = '<?php class SyncEmployeesWidget extends Component { public function mount() { } }';
        $this->assertFalse($this->invokePrivateMethod('isFormComponent', [$widgetContent, 'SyncEmployeesWidget']));

        // Test display component (should be excluded)
        $displayContent = '<?php class DashboardStats extends Component { }';
        $this->assertFalse($this->invokePrivateMethod('isFormComponent', [$displayContent, 'DashboardStats']));
    }

    private function invokePrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->checker, $parameters);
    }
}