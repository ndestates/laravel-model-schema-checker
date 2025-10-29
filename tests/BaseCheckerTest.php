<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use Orchestra\Testbench\TestCase;
use NDEstates\LaravelModelSchemaChecker\Checkers\BaseChecker;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;
use Illuminate\Console\Command;

class BaseCheckerTest extends TestCase
{
    protected TestChecker $checker;
    protected array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'strict_mode' => false,
            'rules' => [
                'enabled' => [
                    'test_rule' => true,
                    'disabled_rule' => false,
                ]
            ]
        ];

        $this->checker = new TestChecker($this->config);
    }

    public function test_constructor_sets_config()
    {
        $checker = new TestChecker(['test' => 'value']);
        $this->assertEquals(['test' => 'value'], $this->getPrivateProperty($checker, 'config'));
    }

    public function test_set_command()
    {
        $command = $this->createMock(Command::class);
        $result = $this->checker->setCommand($command);

        $this->assertInstanceOf(TestChecker::class, $result);
        $this->assertEquals($command, $this->getPrivateProperty($this->checker, 'command'));
    }

    public function test_is_enabled_default()
    {
        $this->assertTrue($this->checker->isEnabled());
    }

    public function test_is_enabled_disabled_by_config()
    {
        $config = [
            'rules' => [
                'enabled' => [
                    'test_rule' => false,
                ]
            ]
        ];
        $checker = new TestCheckerWithRule($config);

        $this->assertFalse($checker->isEnabled());
    }

    public function test_is_enabled_enabled_by_config()
    {
        $config = [
            'rules' => [
                'enabled' => [
                    'test_rule' => true,
                ]
            ]
        ];
        $checker = new TestCheckerWithRule($config);

        $this->assertTrue($checker->isEnabled());
    }

    public function test_enable_disable()
    {
        $this->checker->disable();
        $this->assertFalse($this->checker->isEnabled());

        $this->checker->enable();
        $this->assertTrue($this->checker->isEnabled());
    }

    public function test_get_issues_initially_empty()
    {
        $this->assertEquals([], $this->checker->getIssues());
    }

    public function test_add_issue()
    {
        // Mock the IssueManager to avoid database dependencies
        $issueManager = $this->createMock(IssueManager::class);
        $this->setPrivateProperty($this->checker, 'issueManager', $issueManager);

        $this->checker->addTestIssue('test_category', 'test_type', ['field' => 'value']);

        $issues = $this->checker->getIssues();
        $this->assertCount(1, $issues);

        $issue = $issues[0];
        $this->assertEquals('test_category', $issue['category']);
        $this->assertEquals('test_type', $issue['type']);
        $this->assertEquals('TestChecker', $issue['checker']);
        $this->assertEquals('value', $issue['field']);
    }

    public function test_is_strict_mode()
    {
        $this->assertFalse($this->invokePrivateMethod($this->checker, 'isStrictMode', []));

        $config = ['strict_mode' => true];
        $checker = new TestChecker($config);
        $this->assertTrue($this->invokePrivateMethod($checker, 'isStrictMode', []));
    }

    public function test_get_name()
    {
        $this->assertEquals('TestChecker', $this->checker->getName());
    }

    public function test_check_method_exists()
    {
        $this->assertTrue(method_exists($this->checker, 'check'));
    }

    /**
     * Helper method to get private/protected properties
     */
    protected function getPrivateProperty($object, string $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Helper method to set private/protected properties
     */
    protected function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        $property->setValue($object, $value);
    }

    /**
     * Helper method to invoke private/protected methods
     */
    protected function invokePrivateMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

/**
 * Concrete implementation of BaseChecker for testing
 */
class TestChecker extends BaseChecker
{
    public function getName(): string
    {
        return 'TestChecker';
    }

    public function getDescription(): string
    {
        return 'Test checker for unit testing';
    }

    public function check(): array
    {
        return [];
    }

    public function addTestIssue(string $category, string $type, array $data): void
    {
        $this->addIssue($category, $type, $data);
    }
}

/**
 * Test checker with rule name for testing config-based enabling/disabling
 */
class TestCheckerWithRule extends BaseChecker
{
    public function getName(): string
    {
        return 'TestCheckerWithRule';
    }

    public function getDescription(): string
    {
        return 'Test checker with rule for unit testing';
    }

    public function check(): array
    {
        return [];
    }

    protected function getRuleName(): ?string
    {
        return 'test_rule';
    }
}
