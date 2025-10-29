<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Supplier Testing Examples - Demonstrates how to test external dependencies
 *
 * This test shows practical supplier testing patterns using PHPUnit mocks
 * to simulate various external service scenarios.
 */
class SupplierTestingExamplesTest extends TestCase
{
    public function test_database_supplier_mock_example()
    {
        // Create a mock database connection
        $dbMock = $this->createMock(\PDO::class);

        // Mock the prepare method to return a statement mock
        $stmtMock = $this->createMock(\PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['id' => 1, 'name' => 'User 1'],
                ['id' => 2, 'name' => 'User 2']
            ]);

        $dbMock->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users')
            ->willReturn($stmtMock);

        // Simulate using the database supplier
        $result = $this->executeQuery($dbMock, 'SELECT * FROM users');

        // Assert the supplier provided expected data
        $this->assertCount(2, $result);
        $this->assertEquals('User 1', $result[0]['name']);
    }

    public function test_file_system_supplier_mock_example()
    {
        // Create a mock file system using a custom class
        $fsMock = $this->createMock(FileSystemMock::class);

        // Mock file operations
        $fsMock->expects($this->once())
            ->method('file_exists')
            ->with('/app/Models/User.php')
            ->willReturn(true);

        $fsMock->expects($this->once())
            ->method('file_get_contents')
            ->with('/app/Models/User.php')
            ->willReturn('<?php class User { public $name; }');

        // Simulate using the file system supplier
        $exists = $fsMock->file_exists('/app/Models/User.php');
        $content = $fsMock->file_get_contents('/app/Models/User.php');

        // Assert the supplier worked correctly
        $this->assertTrue($exists);
        $this->assertStringContainsString('class User', $content);
    }

    public function test_api_supplier_mock_example()
    {
        // Create a mock HTTP client using a custom class
        $httpMock = $this->createMock(HttpClientMock::class);

        // Mock API responses
        $httpMock->expects($this->once())
            ->method('get')
            ->with('https://api.example.com/users/1')
            ->willReturn([
                'status' => 200,
                'body' => json_encode(['id' => 1, 'name' => 'John'])
            ]);

        // Simulate API call
        $response = $httpMock->get('https://api.example.com/users/1');
        $data = json_decode($response['body'], true);

        // Assert the API supplier provided expected data
        $this->assertEquals(200, $response['status']);
        $this->assertEquals('John', $data['name']);
    }

    public function test_supplier_failure_handling()
    {
        // Test how your code handles supplier failures
        $dbMock = $this->createMock(\PDO::class);

        // Mock database connection failure
        $dbMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Connection lost'));

        // Assert that your code handles the failure gracefully
        $this->expectException(\PDOException::class);
        $this->executeQuery($dbMock, 'SELECT * FROM users');
    }

    public function test_supplier_contract_verification()
    {
        // Verify that PHPUnit's mock system works as expected
        $mock = $this->createMock(FileSystemMock::class);

        // Test that we can create mocks and configure expectations
        $this->assertInstanceOf(\PHPUnit\Framework\MockObject\MockObject::class, $mock);

        // Test that mock objects can be configured with expectations
        $configuredMock = $this->createMock(HttpClientMock::class);
        $this->assertInstanceOf(HttpClientMock::class, $configuredMock);
    }

    /**
     * Helper method to simulate database query execution
     */
    private function executeQuery($db, string $sql): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

// Mock classes to replace deprecated addMethods() approach
class FileSystemMock
{
    public function file_exists(string $path): bool
    {
    }
    public function file_get_contents(string $path): string
    {
    }
}

class HttpClientMock
{
    public function get(string $url): array
    {
    }
}
