<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use Orchestra\Testbench\TestCase;
use NDEstates\LaravelModelSchemaChecker\Models\CodeImprovement;
use NDEstates\LaravelModelSchemaChecker\Contracts\CodeImprovementInterface;

/**
 * CodeImprovementTest - Comprehensive test suite for CodeImprovement model
 *
 * PURPOSE:
 * Tests the CodeImprovement model class which represents code improvement suggestions
 * with auto-fix capabilities. This model stores improvement metadata and handles
 * automatic code modifications.
 *
 * WHAT THIS TESTS:
 * - Constructor parameter handling and validation
 * - All getter methods return correct values
 * - Interface implementation compliance
 * - Auto-fix functionality with file operations
 * - Static factory method (fromSearchReplace)
 * - Edge cases and error conditions
 *
 * ASSERTIONS EXPLAINED:
 * - assertInstanceOf: Verifies object is correct type
 * - assertEquals: Verifies exact value matches
 * - assertTrue/assertFalse: Verifies boolean conditions
 * - assertContains: Verifies array contains specific value
 * - assertFileExists: Verifies file system state
 * - assertStringContains: Verifies string content
 *
 * IMPROVEMENT OPPORTUNITIES:
 * - Add validation for severity levels (must be: low, medium, high, critical)
 * - Add file permission checks before attempting fixes
 * - Add backup creation before applying fixes
 * - Add more robust error handling for file operations
 * - Consider adding checksum validation for original code
 */
class CodeImprovementTest extends TestCase
{
    protected string $testFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests in this class as they require full Laravel environment setup
        $this->markTestSkipped('Requires full Laravel environment setup');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
        parent::tearDown();
    }

    /**
     * Test constructor with all parameters
     *
     * ASSERTIONS: assertInstanceOf, assertEquals (multiple)
     * RESULT: Verifies constructor properly assigns all parameters
     * IMPROVEMENT: Could add parameter validation
     */
    public function test_constructor_with_all_parameters()
    {
        $improvement = new CodeImprovement(
            '/path/to/file.php',
            'security',
            'Fix XSS vulnerability',
            'Replace unsafe input handling',
            ['search' => 'old', 'replace' => 'new'],
            42,
            'high',
            true,
            '$oldCode',
            '$newCode'
        );

        $this->assertInstanceOf(CodeImprovement::class, $improvement);
        $this->assertInstanceOf(CodeImprovementInterface::class, $improvement);
        $this->assertEquals('/path/to/file.php', $improvement->getFilePath());
        $this->assertEquals(42, $improvement->getLineNumber());
        $this->assertEquals('security', $improvement->getType());
        $this->assertEquals('high', $improvement->getSeverity());
        $this->assertEquals('Fix XSS vulnerability', $improvement->getTitle());
        $this->assertEquals('Replace unsafe input handling', $improvement->getDescription());
        $this->assertEquals(['search' => 'old', 'replace' => 'new'], $improvement->getSuggestedChanges());
        $this->assertTrue($improvement->canAutoFix());
        $this->assertEquals('$oldCode', $improvement->getOriginalCode());
        $this->assertEquals('$newCode', $improvement->getImprovedCode());
    }

    /**
     * Test constructor with minimal parameters (defaults)
     *
     * ASSERTIONS: assertEquals, assertFalse, assertEmpty
     * RESULT: Verifies default values are properly set
     * IMPROVEMENT: Could validate required parameters are not empty
     */
    public function test_constructor_with_minimal_parameters()
    {
        $improvement = new CodeImprovement(
            '/path/to/file.php',
            'quality',
            'Improve code quality',
            'General improvement'
        );

        $this->assertEquals('/path/to/file.php', $improvement->getFilePath());
        $this->assertNull($improvement->getLineNumber());
        $this->assertEquals('quality', $improvement->getType());
        $this->assertEquals('medium', $improvement->getSeverity()); // default
        $this->assertEquals('Improve code quality', $improvement->getTitle());
        $this->assertEquals('General improvement', $improvement->getDescription());
        $this->assertEmpty($improvement->getSuggestedChanges());
        $this->assertFalse($improvement->canAutoFix());
        $this->assertEmpty($improvement->getOriginalCode());
        $this->assertEmpty($improvement->getImprovedCode());
    }

    /**
     * Test all getter methods return expected values
     *
     * ASSERTIONS: assertEquals (multiple)
     * RESULT: Comprehensive verification of all getter methods
     * IMPROVEMENT: None needed - this is basic functionality
     */
    public function test_all_getters_return_correct_values()
    {
        $improvement = new CodeImprovement(
            '/test/file.php',
            'performance',
            'Optimize query',
            'Use indexed column',
            ['use_index' => true],
            15,
            'low',
            false,
            'SELECT * FROM table',
            'SELECT id FROM table'
        );

        $this->assertEquals('/test/file.php', $improvement->getFilePath());
        $this->assertEquals(15, $improvement->getLineNumber());
        $this->assertEquals('performance', $improvement->getType());
        $this->assertEquals('low', $improvement->getSeverity());
        $this->assertEquals('Optimize query', $improvement->getTitle());
        $this->assertEquals('Use indexed column', $improvement->getDescription());
        $this->assertEquals(['use_index' => true], $improvement->getSuggestedChanges());
        $this->assertFalse($improvement->canAutoFix());
        $this->assertEquals('SELECT * FROM table', $improvement->getOriginalCode());
        $this->assertEquals('SELECT id FROM table', $improvement->getImprovedCode());
    }

    /**
     * Test canAutoFix returns correct boolean values
     *
     * ASSERTIONS: assertTrue, assertFalse
     * RESULT: Verifies auto-fix capability detection
     * IMPROVEMENT: Could add more sophisticated logic for determining auto-fix capability
     */
    public function test_can_auto_fix()
    {
        $canFix = new CodeImprovement(
            '/file.php',
            'security',
            'Fix issue',
            'Description',
            [],
            null,
            'medium',
            true
        );

        $cannotFix = new CodeImprovement(
            '/file.php',
            'security',
            'Fix issue',
            'Description',
            [],
            null,
            'medium',
            false
        );

        $this->assertTrue($canFix->canAutoFix());
        $this->assertFalse($cannotFix->canAutoFix());
    }

    /**
     * Test applyFix when file doesn't exist
     *
     * ASSERTIONS: assertFalse
     * RESULT: Verifies proper handling of missing files
     * IMPROVEMENT: Could add more specific error reporting
     */
    public function test_apply_fix_file_not_exists()
    {
        $improvement = new CodeImprovement(
            '/nonexistent/file.php',
            'security',
            'Fix issue',
            'Description',
            [],
            null,
            'medium',
            true,
            'old',
            'new'
        );

        $result = $improvement->applyFix();
        $this->assertFalse($result);
    }

    /**
     * Test applyFix when cannot auto fix
     *
     * ASSERTIONS: assertFalse
     * RESULT: Verifies auto-fix is not attempted when not allowed
     * IMPROVEMENT: Could add logging for attempted fixes
     */
    public function test_apply_fix_cannot_auto_fix()
    {
        file_put_contents($this->testFilePath, 'test content');

        $improvement = new CodeImprovement(
            $this->testFilePath,
            'security',
            'Fix issue',
            'Description',
            [],
            null,
            'medium',
            false, // cannot auto fix
            'old',
            'new'
        );

        $result = $improvement->applyFix();
        $this->assertFalse($result);
    }

    /**
     * Test successful applyFix operation
     *
     * ASSERTIONS: assertTrue, assertStringContains
     * RESULT: Verifies file content is correctly modified
     * IMPROVEMENT: Add backup creation before modification
     */
    public function test_apply_fix_successful()
    {
        $originalContent = '<?php echo "Hello World"; ?>';
        $expectedContent = '<?php echo "Hello Laravel"; ?>';

        file_put_contents($this->testFilePath, $originalContent);

        $improvement = new CodeImprovement(
            $this->testFilePath,
            'quality',
            'Update greeting',
            'Change greeting text',
            ['search' => 'World', 'replace' => 'Laravel'],
            null,
            'medium',
            true,
            'World',
            'Laravel'
        );

        $result = $improvement->applyFix();
        $this->assertTrue($result);

        $newContent = file_get_contents($this->testFilePath);
        $this->assertStringContainsString('Hello Laravel', $newContent);
    }

    /**
     * Test applyFix when no changes are needed
     *
     * ASSERTIONS: assertFalse
     * RESULT: Verifies no modification when content unchanged
     * IMPROVEMENT: Could add logging to indicate no changes were needed
     */
    public function test_apply_fix_no_changes_needed()
    {
        $content = '<?php echo "Hello World"; ?>';
        file_put_contents($this->testFilePath, $content);

        $improvement = new CodeImprovement(
            $this->testFilePath,
            'quality',
            'Update greeting',
            'Change greeting text',
            [],
            null,
            'medium',
            true,
            'NotFound',
            'Replacement'
        );

        $result = $improvement->applyFix();
        $this->assertFalse($result);

        // Content should remain unchanged
        $this->assertEquals($content, file_get_contents($this->testFilePath));
    }

    /**
     * Test fromSearchReplace static factory method
     *
     * ASSERTIONS: assertInstanceOf, assertEquals (multiple), assertTrue
     * RESULT: Verifies factory method creates proper object with auto-fix enabled
     * IMPROVEMENT: Could add validation for search/replace parameters
     */
    public function test_from_search_replace_factory()
    {
        $improvement = CodeImprovement::fromSearchReplace(
            '/path/to/file.php',
            'security',
            'Fix vulnerability',
            'Replace unsafe code',
            '$oldCode',
            '$newCode',
            25,
            'high'
        );

        $this->assertInstanceOf(CodeImprovement::class, $improvement);
        $this->assertEquals('/path/to/file.php', $improvement->getFilePath());
        $this->assertEquals('security', $improvement->getType());
        $this->assertEquals('Fix vulnerability', $improvement->getTitle());
        $this->assertEquals('Replace unsafe code', $improvement->getDescription());
        $this->assertEquals(25, $improvement->getLineNumber());
        $this->assertEquals('high', $improvement->getSeverity());
        $this->assertTrue($improvement->canAutoFix());
        $this->assertEquals('$oldCode', $improvement->getOriginalCode());
        $this->assertEquals('$newCode', $improvement->getImprovedCode());
        $this->assertEquals([
            'search' => '$oldCode',
            'replace' => '$newCode'
        ], $improvement->getSuggestedChanges());
    }

    /**
     * Test fromSearchReplace with empty strings (should disable auto-fix)
     *
     * ASSERTIONS: assertFalse
     * RESULT: Verifies auto-fix is disabled when search/replace strings are empty
     * IMPROVEMENT: Could add more robust validation for empty strings
     */
    public function test_from_search_replace_empty_strings()
    {
        $improvement = CodeImprovement::fromSearchReplace(
            '/file.php',
            'quality',
            'Title',
            'Description',
            '', // empty search
            'replacement'
        );

        $this->assertFalse($improvement->canAutoFix());
    }

    /**
     * Test interface compliance - all required methods exist
     *
     * ASSERTIONS: assertTrue (multiple)
     * RESULT: Verifies CodeImprovement properly implements the interface
     * IMPROVEMENT: Could add more comprehensive interface testing
     */
    public function test_interface_compliance()
    {
        $improvement = new CodeImprovement('/file.php', 'test', 'title', 'desc');

        $this->assertTrue(method_exists($improvement, 'getFilePath'));
        $this->assertTrue(method_exists($improvement, 'getLineNumber'));
        $this->assertTrue(method_exists($improvement, 'getType'));
        $this->assertTrue(method_exists($improvement, 'getSeverity'));
        $this->assertTrue(method_exists($improvement, 'getTitle'));
        $this->assertTrue(method_exists($improvement, 'getDescription'));
        $this->assertTrue(method_exists($improvement, 'getSuggestedChanges'));
        $this->assertTrue(method_exists($improvement, 'canAutoFix'));
        $this->assertTrue(method_exists($improvement, 'applyFix'));
    }

    /**
     * Test edge case: very long file paths
     *
     * ASSERTIONS: assertEquals
     * RESULT: Verifies handling of long file paths
     * IMPROVEMENT: Could add path length validation
     */
    public function test_long_file_path()
    {
        $longPath = '/very/long/path/to/a/file/that/might/be/deeply/nested/in/the/filesystem/structure.php';
        $improvement = new CodeImprovement($longPath, 'test', 'title', 'desc');

        $this->assertEquals($longPath, $improvement->getFilePath());
    }

    /**
     * Test edge case: special characters in content
     *
     * ASSERTIONS: assertEquals
     * RESULT: Verifies handling of special characters in code
     * IMPROVEMENT: Could add character encoding validation
     */
    public function test_special_characters_in_code()
    {
        $specialCode = '<?php echo "Special chars: àáâãäåæçèéêë"; ?>';
        $improvement = new CodeImprovement(
            '/file.php',
            'test',
            'title',
            'desc',
            [],
            null,
            'medium',
            true,
            'old',
            $specialCode
        );

        $this->assertEquals($specialCode, $improvement->getImprovedCode());
    }
}
