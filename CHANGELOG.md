# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2025-10-21 (In Development)

### 🚀 **Major Architecture Overhaul**
- **Modular Architecture**: Transformed monolithic command into modular, maintainable services
- **Service Layer**: Introduced dedicated services for issue management, checker management, and utilities
- **Configuration System**: Comprehensive configuration with environment-specific settings
- **Database Agnostic Design**: Migration validation works regardless of database connection

### ✅ **Completed Features**

#### **Core Architecture**
- **Service Classes**: `CheckerManager`, `IssueManager`, `MigrationGenerator`, `DataExporter`, `DataImporter`, `MigrationCleanup`
- **Contracts**: `CheckerInterface` for extensible checker system
- **Base Classes**: `BaseChecker` with common functionality and configuration support

#### **Enhanced Checkers**
- **ModelChecker**: Fillable properties, table existence, schema alignment
- **RelationshipChecker**: Model relationships integrity and consistency
- **MigrationChecker**: Syntax validation, best practices, configurable database schema validation
- **ValidationChecker**: Form validation rules against database schema
- **SecurityChecker**: XSS, CSRF, SQL injection, and path traversal detection
- **PerformanceChecker**: N+1 query detection and optimization opportunities
- **CodeQualityChecker**: Code quality and maintainability checks
- **LaravelFormsChecker**: Form field validation and amendment suggestions
- **FilamentChecker**: Filament resource validation with autoloading support

#### **Migration Validation Revolution**
- **PHP Syntax Validation** (commit: 3739a04): Catches syntax errors before execution
- **Malformed Method Call Detection** (commit: df38f60): Prevents `$table->string('key'(255))` errors
- **Database-Agnostic Validation** (commit: 31705c8): Works regardless of current database
- **Configurable Schema Validation** (commit: b6977d9): Choose migration-files, database-schema, or both modes
- **Default Exclusions** (commit: aa2cb8a): Automatic exclusion of old/, archive/, legacy/ migration directories

#### **Configuration System Overhaul**
- **Environment-Specific Settings** (commit: bc2fb83): Different validation modes for local/testing/production
- **Comprehensive Exclusions**: Models, files, migrations, and database tables
- **Performance Thresholds**: Configurable timeouts and limits
- **Output Formats**: Console, JSON, XML with verbosity controls
- **Rule Controls**: Enable/disable individual validation rules
- **Default Exclusions**: User model, common Laravel tables, migration subdirectories

#### **Bug Fixes & Improvements**
- **Abstract Class Handling** (commit: 79bd645): Prevents "Cannot instantiate abstract class" errors
- **Validation Rules Parsing** (commit: 916720f): Fixed array/string rule format handling
- **Filament Class Loading** (commit: 1dcb3ca, f03b6bd): Enhanced autoloading and detection
- **Security Fields**: Added password, two_factor_* to excluded fields
- **MigrationChecker Timestamps**: Removed timestamps validation since it's normal for some tables (pivot tables, lookup tables) to not have created_at/updated_at columns
- **Test Cleanup**: Removed test_detects_missing_timestamps test and cleaned up temporary debug output from MigrationCheckerTest

### 🧪 **Testing Infrastructure Overhaul**
- **Comprehensive Unit Tests**: Added 46 tests with 176 assertions covering core functionality
- **IssueManager Testing**: 13 unit tests covering issue tracking, statistics, and filtering
- **CheckerManager Testing**: 14 unit tests covering service initialization and checker management
- **Command Testing**: 6 unit tests for ModelSchemaCheckCommand interface validation
- **Testability Enhancements**: Modified CheckerManager with optional environment parameter for testing
- **Syntax Error Fixes**: Resolved PHP syntax errors in MigrationChecker, ModelChecker, RelationshipChecker
- **Deprecated Method Updates**: Updated PHPUnit assertions from deprecated `assertStringContains` to `assertStringContainsString`
- **Code Style Compliance**: Applied PHPCS auto-fixes resolving 66 style violations

### 📋 **Configuration Options**

#### **Migration Validation Modes**
```php
'migration_validation_mode' => 'migration_files', // 'migration_files', 'database_schema', 'both'
```

#### **Environment-Specific Settings**
```php
'environments' => [
    'local' => ['strict_mode' => false, 'skip_performance_checks' => true],
    'testing' => ['strict_mode' => true, 'skip_performance_checks' => false],
    'production' => ['strict_mode' => true, 'skip_performance_checks' => false],
],
```

#### **Default Exclusions**
```php
'excluded_models' => ['App\Models\User'],
'excluded_tables' => ['migrations', 'failed_jobs', 'cache', 'sessions'],
'exclude_patterns' => [
    'migrations' => ['**/migrations/old/**', '**/migrations/archive/**', ...],
],
```

### 🔧 **Environment Variables**
```env
MSC_MIGRATION_MODE=database_schema
MSC_OUTPUT_FORMAT=json
MSC_VERBOSE=true
MSC_CACHE_ENABLED=true
```

### 📊 **Development Status**
- **Architecture**: ✅ Complete (Modular services implemented)
- **Checkers**: ✅ Complete (All major checkers implemented and enhanced)
- **Configuration**: ✅ Complete (Comprehensive config system)
- **Testing**: ✅ Complete (46 tests passing with 176 assertions)
- **Documentation**: 🚧 In Progress
- **Production Ready**: ❌ Not yet (Requires documentation completion)

---

## [2.x] - Previous Versions
- Legacy monolithic architecture
- Basic model and relationship validation
- Limited configuration options
- **Granular Code Quality Checks**: New dedicated commands for targeted code quality analysis
  - `--check-models`: Check model quality (fillable, relationships, etc.)
  - `--check-models-exclude=*`: Exclude specific model files from checks
  - `--check-controllers`: Check controller quality and best practices
  - `--check-controllers-exclude=*`: Exclude specific controller files from checks
  - `--check-migrations-quality`: Check migration file quality and best practices
  - `--check-migrations-quality-exclude=*`: Exclude specific migration files from quality checks
- **Automatic Path Inclusion**: Commands automatically include relevant paths (Models/, Http/Controllers/, database/migrations/)
- **Enhanced Path Filtering**: Improved path-based filtering for targeted code analysis
- **Targeted Check Execution**: CodeQualityChecker now supports running specific check types based on include paths

### Enhanced
- **CodeQualityChecker**: Updated to support granular, targeted checks with automatic path filtering
- **Command Handler Methods**: Added dedicated handler methods for each granular check type
- **Documentation**: Updated README.md with new command options and usage examples

### Fixed
- **LaravelFormsChecker**: Fixed undefined method error when calling `$this->issue()` (commit: af880da)
  - Replaced incorrect `$this->issue()` calls with proper `$this->addIssue()` method calls
  - Updated method signatures to match BaseChecker interface requirements
  - Fixed `--check-laravel-forms` command execution errors
- **ModelChecker**: Fixed abstract class instantiation error (commit: a9e7ac1)
  - Added ReflectionClass check to skip abstract model classes before instantiation
  - Prevents "Cannot instantiate abstract class" errors during model scanning
  - Abstract model base classes are now properly handled without causing exceptions
- **RelationshipChecker, LaravelFormsChecker, ValidationChecker**: Fixed abstract class instantiation errors (commit: 79bd645)
  - Added ReflectionClass abstract checks to all checkers that instantiate model classes
  - Prevents "Cannot instantiate abstract class" errors across all analysis methods
  - Abstract model classes are now properly skipped in relationship, form, and validation analysis

## [1.0.0] - 2024-12-19

### Added
- Initial release of Laravel Model-Database Schema Checker
- Comprehensive model fillable property validation against database schema
- Filament relationship integrity checking
- Automatic environment detection (DDEV, Docker, Local)
- Support for Laravel 10.x and 11.x
- PHP 8.1+ compatibility
- Automated fix suggestions for common issues
- Detailed logging and reporting
- Composer package installation
- Standalone script execution
- Comprehensive documentation

### Features
- Model-Database Schema Validation
- Filament Form Relationship Checking
- Migration Consistency Validation
- Encrypted Fields Verification
- Missing Tables Detection
- Environment-Aware Execution
- Automated Fixes and Suggestions
- Detailed Error Reporting
- JSON Output Support
- Backup and Recovery Options

### Installation
- `composer require ndestates/laravel-model-schema-checker --dev`
- Or use the provided `install.sh` script for manual installation

### Usage
- `php artisan model:schema-check` - Run basic validation
- `php artisan model:schema-check --check-all` - Run comprehensive checks
- `php artisan model:schema-check --fix` - Apply automatic fixes