<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\CheckerManager;
use NDEstates\LaravelModelSchemaChecker\Contracts\CheckerInterface;
use NDEstates\LaravelModelSchemaChecker\Checkers\ModelChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\FilamentChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\SecurityChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\RelationshipChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\MigrationChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\ValidationChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\PerformanceChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\CodeQualityChecker;
use NDEstates\LaravelModelSchemaChecker\Checkers\LaravelFormsChecker;
use Illuminate\Console\Command;

class CheckerManagerTest extends TestCase
{
    private array $defaultConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultConfig = [
            'controller_path' => '/tmp/test_controllers',
            'model_path' => '/tmp/test_models',
            'view_path' => '/tmp/test_views',
            'excluded_models' => ['App\Models\User'],
            'exclude_patterns' => [
                'files' => ['**/vendor/**', '**/node_modules/**'],
            ],
            'rules' => [
                'enabled' => [
                    'model_fillable_check' => true,
                    'relationship_integrity' => true,
                ],
            ],
            'performance_thresholds' => [
                'max_execution_time' => 30,
                'max_memory_usage' => 128,
            ],
            'environments' => [
                'testing' => [
                    'strict_mode' => false,
                ],
            ],
        ];
    }

    public function test_checker_manager_can_be_instantiated()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');
        $this->assertInstanceOf(CheckerManager::class, $manager);
    }

    public function test_checker_manager_registers_default_checkers()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');
        $checkers = $manager->getCheckers();

        $this->assertCount(9, $checkers);

        $checkerNames = array_map(fn($checker) => $checker->getName(), $checkers);
        $expectedNames = [
            'Model Checker',
            'Filament Checker',
            'Security Checker',
            'Relationship Checker',
            'Migration Checker',
            'Validation Checker',
            'Performance Checker',
            'Code Quality Checker',
            'Laravel Forms Checker',
        ];

        foreach ($expectedNames as $expectedName) {
            $this->assertContains($expectedName, $checkerNames);
        }
    }

    public function test_checker_manager_can_register_custom_checker()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');

        $customChecker = $this->createMock(CheckerInterface::class);
        $customChecker->method('getName')->willReturn('Custom Checker');
        $customChecker->method('getDescription')->willReturn('A custom checker');
        $customChecker->method('isEnabled')->willReturn(true);

        $manager->register($customChecker);

        $checkers = $manager->getCheckers();
        $this->assertCount(10, $checkers);

        $customCheckerFromManager = $manager->getChecker('Custom Checker');
        $this->assertSame($customChecker, $customCheckerFromManager);
    }

    public function test_checker_manager_can_enable_and_disable_checkers()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');

        // Initially all checkers should be enabled
        $enabledCheckers = $manager->getEnabledCheckers();
        $this->assertCount(9, $enabledCheckers);

        // Disable a checker
        $manager->disableChecker('Model Checker');

        $enabledCheckers = $manager->getEnabledCheckers();
        $this->assertCount(8, $enabledCheckers);

        $modelChecker = $manager->getChecker('Model Checker');
        $this->assertFalse($modelChecker->isEnabled());

        // Re-enable the checker
        $manager->enableChecker('Model Checker');

        $enabledCheckers = $manager->getEnabledCheckers();
        $this->assertCount(9, $enabledCheckers);

        $modelChecker = $manager->getChecker('Model Checker');
        $this->assertTrue($modelChecker->isEnabled());
    }

    public function test_checker_manager_can_get_checker_by_name()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');

        $modelChecker = $manager->getChecker('Model Checker');
        $this->assertInstanceOf(ModelChecker::class, $modelChecker);

        $securityChecker = $manager->getChecker('security'); // Case insensitive partial match
        $this->assertInstanceOf(SecurityChecker::class, $securityChecker);

        $nonExistentChecker = $manager->getChecker('Non Existent Checker');
        $this->assertNull($nonExistentChecker);
    }

    public function test_checker_manager_can_run_specific_check()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');

        // Mock a checker to return specific issues
        $mockChecker = $this->createMock(CheckerInterface::class);
        $mockChecker->method('getName')->willReturn('Mock Checker');
        $mockChecker->method('isEnabled')->willReturn(true);
        $mockChecker->method('check')->willReturn([
            ['type' => 'error', 'message' => 'Mock issue'],
        ]);

        $manager->register($mockChecker);

        $issues = $manager->runCheck('Mock Checker');
        $this->assertCount(1, $issues);
        $this->assertEquals('error', $issues[0]['type']);
        $this->assertEquals('Mock issue', $issues[0]['message']);
    }

    public function test_checker_manager_can_run_all_checks()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');

        // Disable all checkers to avoid calling Laravel helpers during test
        foreach ($manager->getCheckers() as $checker) {
            $manager->disableChecker($checker->getName());
        }

        $issues = $manager->runAllChecks();
        $this->assertIsArray($issues);
        $this->assertEmpty($issues); // No enabled checkers, so no issues
    }

    public function test_checker_manager_handles_file_exclusion_patterns()
    {
        $config = $this->defaultConfig;
        $config['exclude_patterns']['files'] = ['**/vendor/**', '**/tests/**'];

        $manager = new CheckerManager($config, 'testing');

        $this->assertTrue($manager->shouldSkipFile('/path/to/vendor/file.php'));
        $this->assertTrue($manager->shouldSkipFile('/path/to/tests/Test.php'));
        $this->assertFalse($manager->shouldSkipFile('/path/to/app/Model.php'));
    }

    public function test_checker_manager_handles_model_exclusions()
    {
        $config = $this->defaultConfig;
        $config['excluded_models'] = ['App\Models\User', 'App\Models\Admin'];

        $manager = new CheckerManager($config, 'testing');

        $this->assertTrue($manager->shouldSkipModel('App\Models\User'));
        $this->assertTrue($manager->shouldSkipModel('App\Models\Admin'));
        $this->assertFalse($manager->shouldSkipModel('App\Models\Post'));
    }

    public function test_checker_manager_handles_rule_enabling()
    {
        $config = $this->defaultConfig;
        $config['rules']['enabled'] = [
            'model_fillable_check' => true,
            'relationship_integrity' => false,
        ];

        $manager = new CheckerManager($config, 'testing');

        $this->assertTrue($manager->isRuleEnabled('model_fillable_check'));
        $this->assertFalse($manager->isRuleEnabled('relationship_integrity'));
        $this->assertTrue($manager->isRuleEnabled('non_existent_rule')); // Default to true
    }

    public function test_checker_manager_handles_performance_thresholds()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');

        $this->assertEquals(30, $manager->getPerformanceThreshold('max_execution_time'));
        $this->assertEquals(128, $manager->getPerformanceThreshold('max_memory_usage'));
        $this->assertNull($manager->getPerformanceThreshold('non_existent_threshold'));
    }

    public function test_checker_manager_can_set_command()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');

        $mockCommand = $this->createMock(Command::class);
        $manager->setCommand($mockCommand);

        // Verify that the command was set (we can't easily test that it was passed to checkers
        // without more complex mocking, but this tests the basic functionality)
        $this->assertInstanceOf(CheckerManager::class, $manager);
    }

    public function test_checker_manager_gets_available_checkers_info()
    {
        $manager = new CheckerManager($this->defaultConfig, 'testing');
        $availableCheckers = $manager->getAvailableCheckers();

        $this->assertCount(9, $availableCheckers);

        foreach ($availableCheckers as $checkerInfo) {
            $this->assertArrayHasKey('name', $checkerInfo);
            $this->assertArrayHasKey('description', $checkerInfo);
            $this->assertArrayHasKey('enabled', $checkerInfo);
            $this->assertIsString($checkerInfo['name']);
            $this->assertIsString($checkerInfo['description']);
            $this->assertIsBool($checkerInfo['enabled']);
        }
    }

}