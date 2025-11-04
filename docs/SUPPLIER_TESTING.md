# Supplier Testing Strategy with PHPUnit

## Overview
Supplier tests verify that external dependencies (suppliers) provide the expected functionality and handle failures gracefully. These tests ensure your application works correctly with real-world external services.

## Types of Supplier Tests

### 1. **API Client Tests** (`ApiClientSupplierTest`)
Test external API dependencies:
- ✅ Mock HTTP responses for success scenarios
- ✅ Test timeout and network failure handling
- ✅ Verify rate limiting behavior
- ✅ Test authentication failures

### 2. **Database Supplier Tests** (`DatabaseSupplierTest`)
Test database connectivity and operations:
- ✅ Connection establishment and failure scenarios
- ✅ Table existence and schema validation
- ✅ Query execution and result handling
- ✅ Transaction management

### 3. **File System Supplier Tests** (`FileSystemSupplierTest`)
Test file system operations:
- ✅ File read/write permissions
- ✅ Directory creation and traversal
- ✅ File existence and content validation
- ✅ Permission denied scenarios

### 4. **Cache Supplier Tests**
Test caching backends (Redis, Memcached):
- ✅ Cache connection and availability
- ✅ Set/get operations
- ✅ Cache expiration
- ✅ Cache miss handling

### 5. **Queue Supplier Tests**
Test queue systems (Redis, SQS, Database):
- ✅ Queue connection and job dispatching
- ✅ Job processing and failure handling
- ✅ Queue monitoring and metrics

## Testing Patterns

### **Contract Testing**
```php
public function test_supplier_contract()
{
    // Verify supplier implements expected interface
    $this->assertTrue(method_exists($supplier, 'requiredMethod'));
    $this->assertTrue(method_exists($supplier, 'anotherRequiredMethod'));
}
```

### **Mock-Based Testing**
```php
public function test_supplier_with_mocks()
{
    // Arrange
    $mockSupplier = $this->createMock(SupplierInterface::class);
    $mockSupplier->expects($this->once())
        ->method('getData')
        ->willReturn(['expected' => 'data']);

    // Act
    $result = $this->service->processWithSupplier($mockSupplier);

    // Assert
    $this->assertEquals(['expected' => 'data'], $result);
}
```

### **Integration Testing**
```php
public function test_real_supplier_integration()
{
    // Use real supplier in controlled environment
    $supplier = new RealSupplier(['test_mode' => true]);
    $result = $supplier->performOperation();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('status', $result);
}
```

## Best Practices

### **1. Isolation**
- Use mocks/stubs for external dependencies
- Test one supplier at a time
- Avoid testing multiple suppliers together

### **2. Realistic Scenarios**
- Test both success and failure paths
- Include network timeouts and service unavailability
- Test rate limiting and quota exceeded scenarios

### **3. Contract Verification**
- Define clear interfaces for suppliers
- Test that suppliers meet their contracts
- Use data providers for multiple test scenarios

### **4. Error Handling**
- Test graceful degradation when suppliers fail
- Verify appropriate error messages and logging
- Test retry mechanisms and circuit breakers

### **5. Performance**
- Test supplier response times
- Verify timeout handling
- Test concurrent supplier usage

## Tools and Libraries

### **Mocking**
- **PHPUnit Mocks**: Built-in mocking framework
- **Mockery**: More expressive mocking syntax
- **Prophecy**: Flexible mocking library

### **Virtual File Systems**
- **vfsStream**: Virtual file system for testing
- **Symfony Filesystem**: File system abstraction

### **HTTP Testing**
- **Guzzle Mock Handler**: HTTP client mocking
- **Symfony HttpClient**: HTTP client with testing support

### **Database Testing**
- **Laravel Testbench**: Database testing utilities
- **SQLite in-memory**: Fast database testing

## Example Test Structure

```
tests/
├── Suppliers/
│   ├── ApiClientSupplierTest.php
│   ├── DatabaseSupplierTest.php
│   ├── FileSystemSupplierTest.php
│   ├── CacheSupplierTest.php
│   └── QueueSupplierTest.php
├── Integration/
│   └── SupplierIntegrationTest.php
└── Contracts/
    └── SupplierContractTest.php
```

## Running Supplier Tests

```bash
# Run all supplier tests
ddev exec ./vendor/bin/phpunit --testsuite suppliers

# Run specific supplier test
ddev exec ./vendor/bin/phpunit tests/Suppliers/ApiClientSupplierTest.php

# Run with coverage
ddev exec ./vendor/bin/phpunit --coverage-html coverage tests/Suppliers/
```

## Continuous Integration

```yaml
# .github/workflows/supplier-tests.yml
name: Supplier Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      redis: redis:6-alpine
      mysql: mysql:8.0
    steps:
      - uses: actions/checkout@v3
      - name: Run supplier tests
        run: ./vendor/bin/phpunit --testsuite suppliers
```

This strategy ensures your application is robust against external dependency failures and provides confidence in supplier integrations.