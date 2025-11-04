# PHPStan Static Analysis Fixes - Learning Guide

## Overview

This document explains the PHPStan static analysis errors that were discovered and fixed in the Laravel Model Schema Checker package. These fixes improve code quality, type safety, and prevent runtime errors.

## What is PHPStan?

PHPStan is a static analysis tool for PHP that focuses on finding bugs in your code without actually running it. It performs type checking, detects potential issues, and enforces coding standards.

### Why We Added PHPStan

- **Early Bug Detection**: Catches type errors and potential bugs before they reach production
- **Improved Code Quality**: Enforces better coding practices and type safety
- **Better IDE Support**: Provides better autocomplete and error detection
- **Documentation**: Serves as inline documentation for expected types

## The Errors Found

### 1. Missing Type Hints for Method Parameters

**Error**: `Method has parameter $param with no type specified`

**Examples Found**:
- `checkModel($file)` - parameter should be `\SplFileInfo`
- `getNamespaceFromFile($filePath)` - parameter should be `string`
- `checkModelFillableProperties($model, string $className)` - first parameter should be `\Illuminate\Database\Eloquent\Model`

### 2. ReflectionClass Type Issues

**Error**: `Parameter #1 $objectOrClass of class ReflectionClass constructor expects class-string<T>|T, string given`

**Problem**: PHPStan couldn't verify that string parameters passed to `ReflectionClass` were valid class names.

### 3. Missing Class Checks

**Error**: `Class Filament\Resources\Resource not found`

**Problem**: Code was checking `is_subclass_of()` against Filament classes without verifying Filament was installed.

### 4. String Operation Issues

**Error**: `Parameter #1 $haystack of function str_contains expects string, array|string|true given`

**Problem**: Command options can return various types, not just strings.

### 5. Nullable Return Values

**Error**: `Parameter expects string, string|null given`

**Problems**:
- `json_encode()` can return `false`
- `preg_replace()` can return `null`

## Fixes Applied

### 1. Added Proper Type Hints

```php
// Before
protected function checkModel($file): void

// After
protected function checkModel(\SplFileInfo $file): void
```

**Why**: Type hints document expected parameter types and enable static analysis.

### 2. Added Class Existence Checks Before Reflection

```php
// Before
$reflection = new ReflectionClass($className);

// After
if (!class_exists($className)) {
    $this->error("Class {$className} does not exist");
    return;
}
$reflection = new ReflectionClass($className);
```

**Why**: Ensures the class exists before attempting reflection, preventing runtime errors.

### 3. Added Optional Dependency Checks

```php
// Before
if (is_subclass_of($class, \Filament\Resources\Resource::class)) {

// After
if (class_exists(\Filament\Resources\Resource::class) &&
    is_subclass_of($class, \Filament\Resources\Resource::class)) {
```

**Why**: Prevents errors when optional dependencies like Filament are not installed.

### 4. Added Type Casting for Command Options

```php
// Before
$specificResource = $this->option('filament-resource');
if (!str_contains($specificResource, '\\')) {

// After
$specificResource = $this->option('filament-resource');
if ($specificResource) {
    $specificResource = (string) $specificResource;
    if (!str_contains($specificResource, '\\')) {
```

**Why**: Command options can return various types; casting ensures string operations work correctly.

### 5. Handled Nullable Return Values

```php
// Before
$this->line(json_encode($output, JSON_PRETTY_PRINT));

// After
$jsonOutput = json_encode($output, JSON_PRETTY_PRINT);
if ($jsonOutput !== false) {
    $this->line($jsonOutput);
} else {
    $this->error('Failed to encode JSON output');
}
```

**Why**: Functions like `json_encode()` can fail and return `false`, which would cause type errors.

```php
// Before
$content = preg_replace(...);
File::put($filePath, $content);

// After
$content = preg_replace(...);
if ($content !== null) {
    File::put($filePath, $content);
} else {
    $this->error("Failed to update file");
}
```

**Why**: `preg_replace()` returns `null` on failure, which `File::put()` doesn't accept.

## Configuration Files Added

### phpstan.neon
```neon
parameters:
    level: 8
    paths:
        - src
    excludePaths:
        - vendor/*
    checkMissingIterableValueType: false
```

**Level 8**: Strictest analysis level
**Paths**: Only analyze source code
**Exclusions**: Skip vendor dependencies

### phpcs.xml
```xml
<ruleset name="LaravelModelSchemaChecker">
    <rule ref="PSR12">
        <exclude name="PSR12.Files.FileHeader"/>
    </rule>
    <rule ref="Generic.Files.LineLength">
        <properties>
            <property name="lineLimit" value="120"/>
        </properties>
    </rule>
</ruleset>
```

**PSR-12**: Modern PHP coding standard
**Line Length**: 120 characters (reasonable limit)

### phpmd.xml
```xml
<ruleset name="LaravelModelSchemaChecker">
    <rule ref="rulesets/codesize.xml"/>
    <rule ref="rulesets/unusedcode.xml"/>
    <rule ref="rulesets/naming.xml"/>
</ruleset>
```

**Code Size**: Detects overly complex code
**Unused Code**: Finds dead code
**Naming**: Enforces naming conventions

## CI/CD Integration

The workflow now runs:

```yaml
- name: Run PHPStan static analysis
  run: vendor/bin/phpstan analyse --error-format=github

- name: Run PHPCS code style check
  run: vendor/bin/phpcs --standard=phpcs.xml --report=checkstyle

- name: Run PHPMD mess detector
  run: vendor/bin/phpmd src text phpmd.xml --reportfile=phpmd-report.xml || true
```

## Benefits Achieved

### 1. **Type Safety**
- Prevents type-related runtime errors
- Better IDE support and autocomplete
- Self-documenting code

### 2. **Early Error Detection**
- Catches bugs before they reach production
- Reduces debugging time
- Improves code reliability

### 3. **Code Quality**
- Enforces consistent coding standards
- Removes dead/unused code
- Prevents code complexity issues

### 4. **Maintainability**
- Easier refactoring with confidence
- Better code documentation
- Reduced technical debt

## Best Practices Learned

### 1. **Always Add Type Hints**
```php
// Good
public function processUser(User $user): bool

// Bad
public function processUser($user)
```

### 2. **Check Class Existence Before Reflection**
```php
if (class_exists($className)) {
    $reflection = new ReflectionClass($className);
}
```

### 3. **Handle Optional Dependencies**
```php
if (class_exists(\Optional\Package\Class::class)) {
    // Use optional functionality
}
```

### 4. **Validate Function Return Values**
```php
$result = json_encode($data);
if ($result !== false) {
    // Safe to use $result
}
```

### 5. **Use Static Analysis in CI/CD**
- Run static analysis on every PR
- Fail builds on type errors
- Use appropriate strictness levels

## Running the Tools Locally

```bash
# Install dependencies
composer install

# Run PHPStan
vendor/bin/phpstan analyse

# Run PHPCS
vendor/bin/phpcs --standard=phpcs.xml

# Run PHPMD
vendor/bin/phpmd src text phpmd.xml

# Run all checks
composer test:all
```

## Conclusion

These fixes demonstrate the value of static analysis in modern PHP development. By catching type errors, enforcing coding standards, and validating optional dependencies, we've significantly improved the code quality and reliability of the Laravel Model Schema Checker package.

The investment in static analysis pays dividends in:
- Fewer runtime bugs
- Easier maintenance
- Better developer experience
- Higher code quality standards

**Remember**: Static analysis is not a replacement for testing, but a powerful complement that catches different types of issues early in development.</content>
<parameter name="filePath">/home/nickd/projects/laravel-model-schema-checker/PHPSTAN_FIXES_GUIDE.md