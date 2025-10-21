<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use Orchestra\Testbench\TestCase;
use NDEstates\LaravelModelSchemaChecker\Exceptions\CheckerException;

/**
 * CheckerExceptionTest - Comprehensive test suite for custom CheckerException
 *
 * PURPOSE:
 * Tests the custom CheckerException class which extends PHP's base Exception
 * to include additional context information for better error reporting and debugging.
 *
 * WHAT THIS TESTS:
 * - Constructor parameter handling and inheritance
 * - Context data storage and retrieval
 * - Exception message and code handling
 * - Fluent interface for context setting
 * - Exception chaining with previous exceptions
 *
 * ASSERTIONS EXPLAINED:
 * - assertInstanceOf: Verifies object is correct exception type
 * - assertEquals: Verifies exact value matches for messages, codes, context
 * - assertSame: Verifies object identity (for previous exception)
 * - assertEmpty: Verifies empty arrays or null values
 * - assertCount: Verifies array element count
 * - assertArrayHasKey: Verifies array contains specific keys
 *
 * IMPROVEMENT OPPORTUNITIES:
 * - Add context validation (ensure context is array)
 * - Add context serialization for logging
 * - Add factory methods for common exception types
 * - Add error code constants for different error types
 * - Add stack trace context capture
 * - Consider adding error severity levels
 */
class CheckerExceptionTest extends TestCase
{
    /**
     * Test constructor with message only
     *
     * ASSERTIONS: assertInstanceOf, assertEquals, assertEmpty
     * RESULT: Verifies basic exception creation with default values
     * IMPROVEMENT: Could add message validation
     */
    public function test_constructor_with_message_only()
    {
        $exception = new CheckerException('Test error message');

        $this->assertInstanceOf(CheckerException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals('Test error message', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertEmpty($exception->getContext());
        $this->assertNull($exception->getPrevious());
    }

    /**
     * Test constructor with message and context
     *
     * ASSERTIONS: assertEquals, assertCount, assertArrayHasKey
     * RESULT: Verifies context data is properly stored
     * IMPROVEMENT: Could validate context structure
     */
    public function test_constructor_with_message_and_context()
    {
        $context = [
            'file' => '/path/to/file.php',
            'line' => 42,
            'checker' => 'ModelChecker',
            'additional_info' => 'Extra details'
        ];

        $exception = new CheckerException('Validation failed', $context);

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertEquals($context, $exception->getContext());
        $this->assertCount(4, $exception->getContext());
        $this->assertArrayHasKey('file', $exception->getContext());
        $this->assertArrayHasKey('checker', $exception->getContext());
    }

    /**
     * Test constructor with all parameters including previous exception
     *
     * ASSERTIONS: assertEquals, assertSame
     * RESULT: Verifies exception chaining works correctly
     * IMPROVEMENT: Could add previous exception validation
     */
    public function test_constructor_with_all_parameters()
    {
        $previousException = new \RuntimeException('Original error');
        $context = ['operation' => 'file_read', 'path' => '/test/file.php'];

        $exception = new CheckerException(
            'File processing failed',
            $context,
            1001,
            $previousException
        );

        $this->assertEquals('File processing failed', $exception->getMessage());
        $this->assertEquals(1001, $exception->getCode());
        $this->assertEquals($context, $exception->getContext());
        $this->assertSame($previousException, $exception->getPrevious());
    }

    /**
     * Test getContext method returns correct data
     *
     * ASSERTIONS: assertEquals, assertEmpty
     * RESULT: Verifies context getter works correctly
     * IMPROVEMENT: None needed - basic getter functionality
     */
    public function test_get_context()
    {
        $context = ['key' => 'value', 'number' => 123];
        $exception = new CheckerException('Test', $context);

        $this->assertEquals($context, $exception->getContext());
        $this->assertEquals('value', $exception->getContext()['key']);
        $this->assertEquals(123, $exception->getContext()['number']);
    }

    /**
     * Test setContext method with fluent interface
     *
     * ASSERTIONS: assertEquals, assertInstanceOf, assertSame
     * RESULT: Verifies context can be updated and method chaining works
     * IMPROVEMENT: Could add context immutability option
     */
    public function test_set_context_fluent_interface()
    {
        $exception = new CheckerException('Test message');
        $this->assertEmpty($exception->getContext());

        $newContext = ['updated' => true, 'timestamp' => time()];
        $result = $exception->setContext($newContext);

        $this->assertInstanceOf(CheckerException::class, $result);
        $this->assertSame($exception, $result); // Fluent interface returns self
        $this->assertEquals($newContext, $exception->getContext());
    }

    /**
     * Test setContext with empty array
     *
     * ASSERTIONS: assertEmpty
     * RESULT: Verifies context can be cleared
     * IMPROVEMENT: Could add context clearing method
     */
    public function test_set_context_empty()
    {
        $exception = new CheckerException('Test', ['initial' => 'data']);
        $this->assertNotEmpty($exception->getContext());

        $exception->setContext([]);
        $this->assertEmpty($exception->getContext());
    }

    /**
     * Test context with complex data structures
     *
     * ASSERTIONS: assertEquals, assertIsArray, assertCount
     * RESULT: Verifies complex nested context data is handled correctly
     * IMPROVEMENT: Could add context serialization methods
     */
    public function test_context_with_complex_data()
    {
        $complexContext = [
            'files' => ['file1.php', 'file2.php', 'file3.php'],
            'metadata' => [
                'total_lines' => 150,
                'errors_found' => 3,
                'warnings' => 7
            ],
            'timestamps' => [
                'started' => 1638360000,
                'finished' => 1638360300
            ],
            'null_value' => null,
            'boolean_value' => true
        ];

        $exception = new CheckerException('Complex error', $complexContext);

        $this->assertEquals($complexContext, $exception->getContext());
        $this->assertIsArray($exception->getContext()['files']);
        $this->assertCount(3, $exception->getContext()['files']);
        $this->assertIsArray($exception->getContext()['metadata']);
        $this->assertEquals(150, $exception->getContext()['metadata']['total_lines']);
        $this->assertNull($exception->getContext()['null_value']);
        $this->assertTrue($exception->getContext()['boolean_value']);
    }

    /**
     * Test exception inheritance from base Exception
     *
     * ASSERTIONS: assertInstanceOf, assertTrue (multiple)
     * RESULT: Verifies all base Exception methods are available
     * IMPROVEMENT: Could add convenience methods for common exception operations
     */
    public function test_exception_inheritance()
    {
        $exception = new CheckerException('Test message', [], 42);

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertTrue(method_exists($exception, 'getMessage'));
        $this->assertTrue(method_exists($exception, 'getCode'));
        $this->assertTrue(method_exists($exception, 'getFile'));
        $this->assertTrue(method_exists($exception, 'getLine'));
        $this->assertTrue(method_exists($exception, 'getTrace'));
        $this->assertTrue(method_exists($exception, 'getTraceAsString'));
        $this->assertTrue(method_exists($exception, '__toString'));
    }

    /**
     * Test exception message with special characters
     *
     * ASSERTIONS: assertEquals
     * RESULT: Verifies special characters in messages are handled correctly
     * IMPROVEMENT: Could add message sanitization
     */
    public function test_exception_message_with_special_characters()
    {
        $specialMessage = 'Error in file: /path/to/file.php at line 42 - "unexpected token" found!';
        $exception = new CheckerException($specialMessage);

        $this->assertEquals($specialMessage, $exception->getMessage());
    }

    /**
     * Test context with large data sets
     *
     * ASSERTIONS: assertCount, assertEquals
     * RESULT: Verifies performance with large context data
     * IMPROVEMENT: Could add context size limits or compression
     */
    public function test_context_with_large_dataset()
    {
        $largeContext = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeContext["key_$i"] = "value_$i";
        }

        $exception = new CheckerException('Large context test', $largeContext);

        $this->assertCount(1000, $exception->getContext());
        $this->assertEquals('value_0', $exception->getContext()['key_0']);
        $this->assertEquals('value_999', $exception->getContext()['key_999']);
    }

    /**
     * Test exception chaining with multiple levels
     *
     * ASSERTIONS: assertInstanceOf, assertEquals, assertSame
     * RESULT: Verifies deep exception chaining works correctly
     * IMPROVEMENT: Could add methods to traverse exception chain
     */
    public function test_exception_chaining_multiple_levels()
    {
        $rootCause = new \InvalidArgumentException('Root cause');
        $middleException = new \RuntimeException('Middle level', 0, $rootCause);
        $topException = new CheckerException('Top level', ['level' => 'top'], 500, $middleException);

        $this->assertInstanceOf(CheckerException::class, $topException);
        $this->assertEquals('Top level', $topException->getMessage());
        $this->assertEquals(500, $topException->getCode());
        $this->assertEquals(['level' => 'top'], $topException->getContext());

        $this->assertSame($middleException, $topException->getPrevious());
        $this->assertEquals('Middle level', $topException->getPrevious()->getMessage());

        $this->assertSame($rootCause, $topException->getPrevious()->getPrevious());
        $this->assertEquals('Root cause', $topException->getPrevious()->getPrevious()->getMessage());
    }

    /**
     * Test context immutability (context should be copied, not referenced)
     *
     * ASSERTIONS: assertNotSame, assertEquals
     * RESULT: Verifies context is properly copied to prevent external modification
     * IMPROVEMENT: Could implement deep cloning for nested arrays
     */
    public function test_context_immutability()
    {
        $originalContext = ['mutable' => 'original'];
        $exception = new CheckerException('Test', $originalContext);

        // Modify original array
        $originalContext['mutable'] = 'modified';

        // Exception context should remain unchanged
        $this->assertEquals('original', $exception->getContext()['mutable']);
        $this->assertNotSame($originalContext, $exception->getContext());
    }

    /**
     * Test empty context handling
     *
     * ASSERTIONS: assertEmpty, assertIsArray
     * RESULT: Verifies empty context is handled properly
     * IMPROVEMENT: Could provide default context structure
     */
    public function test_empty_context_handling()
    {
        $exception = new CheckerException('No context');

        $this->assertEmpty($exception->getContext());
        $this->assertIsArray($exception->getContext());

        // Setting empty context should work
        $exception->setContext([]);
        $this->assertEmpty($exception->getContext());
    }
}