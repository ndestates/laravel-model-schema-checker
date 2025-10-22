# Laravel Model Schema Checker

A comprehensive Laravel tool for validating models, relationships, security,
performance, code quality, migrations, and **form amendment suggestions** across your entire
Laravel application.

## Compatibility

- **Laravel**: 10.x, 11.x, 12.x
- **PHP**: 8.1+
- **Version**: 3.0.0-dev (Major rewrite in development)

## Installation

Install the package via Composer:

```bash
composer require ndestates/laravel-model-schema-checker --dev
```

### Laravel Auto-Discovery

The package uses Laravel's auto-discovery feature, so it will be automatically
registered.

### Publish Configuration (Optional)

You can publish the configuration file to customize the behavior:

```bash
php artisan vendor:publish --provider="NDEstates\LaravelModelSchemaChecker\ModelSchemaCheckerServiceProvider" --tag="config"
```

This will create a `config/model-schema-checker.php` file where you can customize:

- Model directory
- Excluded fields and models
- Database connection and validation modes
- Migration directory and exclusions
- Environment-specific settings
- Performance thresholds
- Output formats and verbosity
- Rule enable/disable controls

## 🚀 **Version 3.0 - Modular Architecture (Complete)**

**Current Status: ✅ 100% Complete** - Major architecture overhaul with comprehensive configuration system, supplier testing framework, and all facade dependencies resolved.

The v3.0 release transforms the previous monolithic architecture into a modular, maintainable system with extensive configuration options and comprehensive testing infrastructure.

### ✅ **Completed Components**

#### **Core Architecture**
- **Modular Services**: `CheckerManager`, `IssueManager`, `MigrationGenerator`, `DataExporter`, `DataImporter`, `MigrationCleanup`
- **Contracts**: `CheckerInterface` for extensible checker system
- **Base Classes**: `BaseChecker` with common functionality and configuration support

#### **Enhanced Checkers (9 Total)**
- **ModelChecker**: Fillable properties, table existence, schema alignment
- **RelationshipChecker**: Model relationships integrity and consistency
- **MigrationChecker**: Syntax validation, best practices, configurable database schema validation
- **ValidationChecker**: Form validation rules against database schema
- **SecurityChecker**: XSS, CSRF, SQL injection, and path traversal detection
- **PerformanceChecker**: N+1 query detection and optimization opportunities
- **CodeQualityChecker**: Code quality and maintainability checks
- **LaravelFormsChecker**: Form field validation and amendment suggestions
- **FilamentChecker**: Filament resource validation with autoloading support

#### **Configuration System**
- **Environment-Specific Settings**: Different validation modes for local/testing/production
- **Comprehensive Exclusions**: Models, files, migrations, and database tables
- **Performance Thresholds**: Configurable timeouts and limits
- **Output Formats**: Console, JSON, XML with verbosity controls
- **Rule Controls**: Enable/disable individual validation rules
- **Default Exclusions**: User model, common Laravel tables, migration subdirectories

#### **Migration Validation Revolution**
- **PHP Syntax Validation**: Catches syntax errors before execution
- **Malformed Method Call Detection**: Prevents `$table->string('key'(255))` runtime errors
- **Database-Agnostic Validation**: Works regardless of current database connection
- **Configurable Schema Validation**: Choose migration-files, database-schema, or both modes
- **Smart Exclusions**: Automatically skips old/, archive/, legacy/ migration directories

### 🔧 **Recent Fixes & Improvements**
- **PerformanceChecker N+1 Detection**: Fixed regex patterns to properly detect relationship access with method calls (e.g., `$user->posts->count()`)
- **PerformanceChecker Config Paths**: Updated to use configurable paths instead of hardcoded `app_path()` calls for better testability
- **PerformanceChecker Facade Agnostic**: Added facade-agnostic file operations to work in test environments without Laravel facades
- **PerformanceCheckerTest Array Keys**: Fixed test assertions to properly handle array keys after filtering operations
- **Test Suite**: All PerformanceChecker tests now passing (14/14 tests)

## 📖 **Usage**

### Basic Usage

```bash
php artisan model-schema:check
```

### Advanced Usage

```bash
# Check specific components
php artisan model-schema:check --check-models --check-migrations

# Use different migration validation modes
php artisan model-schema:check --migration-mode=database_schema

# Output formats
php artisan model-schema:check --format=json --verbose

# Environment-specific validation
php artisan model-schema:check --env=production
```

### Configuration Examples

#### Environment-Specific Settings
```php
'environments' => [
    'local' => [
        'strict_mode' => false,
        'skip_performance_checks' => true,
    ],
    'testing' => [
        'strict_mode' => true,
        'skip_performance_checks' => false,
    ],
    'production' => [
        'strict_mode' => true,
        'skip_performance_checks' => false,
    ],
],
```

#### Migration Validation Modes
```php
'migration_validation_mode' => 'migration_files', // 'migration_files', 'database_schema', 'both'
```

#### Default Exclusions
```php
'excluded_models' => ['App\Models\User'],
'exclude_patterns' => [
    'migrations' => ['**/migrations/old/**', '**/migrations/archive/**'],
],
```

## 🔧 **Environment Variables**

```env
# Migration validation mode
MSC_MIGRATION_MODE=migration_files  # migration_files, database_schema, both

# Output configuration
MSC_OUTPUT_FORMAT=console          # console, json, xml
MSC_VERBOSE=false
MSC_SHOW_PROGRESS=true
MSC_FAIL_ON_WARNINGS=false

# Performance tuning
MSC_QUERY_TIMEOUT=1000
MSC_MEMORY_LIMIT=128
MSC_MAX_RELATIONSHIPS=10

# Caching
MSC_CACHE_ENABLED=true
MSC_CACHE_TTL=3600
```

## 📋 **What It Checks**

### Models
- Fillable properties vs database columns
- Table existence and naming conventions
- Model relationships integrity
- Abstract class handling

### Relationships
- Foreign key constraints
- Relationship method consistency
- Inverse relationship validation
- N+1 query prevention

### Migrations
- PHP syntax validation
- Malformed method calls detection
- Foreign key index requirements
- Database-agnostic validation
- Configurable schema validation

### Security
- XSS vulnerability detection
- CSRF protection validation
- SQL injection prevention
- Path traversal protection
- Mass assignment vulnerabilities

### Performance
- N+1 query detection
- Missing index identification
- Query optimization suggestions
- Memory usage monitoring

### Code Quality
- Code style consistency
- Maintainability checks
- Best practice adherence
- Documentation requirements

### Laravel Forms
- Form field validation
- Amendment suggestions
- Schema alignment
- Validation rule consistency

### Filament Resources
- Resource class validation
- Form and table method checking
- Relationship integrity
- Autoloading support

## 🎯 **Key Features**

- **Database Agnostic**: Works with SQLite, MySQL, PostgreSQL
- **Environment Aware**: Different validation modes per environment
- **Highly Configurable**: Extensive configuration options
- **Modular Architecture**: Easy to extend with new checkers
- **Performance Optimized**: Caching support for large codebases
- **Multiple Output Formats**: Console, JSON, XML support
- **Smart Exclusions**: Automatic exclusion of common files/models
- **Production Ready**: Comprehensive error detection and reporting

## 📊 **Development Status**

- ✅ **Architecture**: Complete (Modular services implemented)
- ✅ **Checkers**: Complete (All major checkers implemented and enhanced)
- ✅ **Configuration**: Complete (Comprehensive config system with environment support)
- ✅ **Testing**: Complete (51 tests passing with supplier testing framework)
- ✅ **Documentation**: Complete (Comprehensive README and CHANGELOG)
- ✅ **Production Ready**: Ready for release (All facade dependencies resolved)

## 🤝 **Contributing**

Contributions are welcome! Please see our contributing guidelines and submit pull requests to the `feature/version-3` branch.

## 📄 **License**

This package is open-sourced software licensed under the [MIT license](LICENSE).
- **Encrypted Fields Security**: Advanced security analysis for encrypted database fields

### 🔄 **Planned Modular Features**

- **Individual Checkers**: Separate, focused checker classes for each validation type
- **Code Improvement System**: Automatic suggestions with severity levels and auto-fix capabilities
- **Enhanced User Experience**: Better output formatting, interactive fixes, and JSON reporting
- **Plugin Architecture**: Extensible system for third-party checkers

### 📁 **Target Architecture**

```bash
src/
├── Checkers/          # Individual checker classes
├── Contracts/         # Interfaces for checkers and improvements
├── Services/          # Core services (IssueManager, CheckerManager)
├── Models/            # Code improvement models
└── Commands/          # Refactored main command
```

For detailed progress, see [VERSION_3_PLAN.md](VERSION_3_PLAN.md).

## Usage

### Basic Usage

Run the comprehensive checker with Artisan command:

```bash
php artisan model:schema-check
```

### Available Options

#### Core Options

- `--help` - Show help information
- `--dry-run` - Show what would be changed without making changes
- `--fix` - Fix model fillable properties
- `--generate-migrations` - Generate Laravel migrations
- `--json` - Output results in JSON format

#### Specialized Checks

- `--relationships` - Check model relationships and foreign keys
- `--migrations` - Check migration consistency and indexes
- `--validation` - Check validation rules against schema
- `--performance` - Check for N+1 queries and optimization opportunities
- `--quality` - Check code quality and Laravel best practices
- `--check-models` - Check model quality (fillable, relationships, etc.)
- `--check-models-exclude=*` - Exclude specific model files from checks
- `--check-controllers` - Check controller quality and best practices
- `--check-controllers-exclude=*` - Exclude specific controller files from checks
- `--check-migrations-quality` - Check migration file quality and best practices
- `--check-migrations-quality-exclude=*` - Exclude specific migration files from quality checks
- `--filament` - Check Filament forms and relationships
- `--filament-resource=` - Check specific Filament resource
- `--security` - Check for XSS and CSRF vulnerabilities
- `--laravel-forms` - Check Laravel forms (Blade templates, Livewire) and suggest amendments
- `--check-encrypted-fields` - Check encrypted fields in database, models, controllers, and views

#### Migration Synchronization

- `--sync-migrations` - Generate fresh migrations from current database schema
- `--export-data` - Export database data before migration changes
- `--import-data` - Import previously exported data
- `--cleanup-migrations` - Safely remove old migration files

#### Combined Checks

- `--all` - Run all available checks

### Migration Synchronization Examples

When your migrations are out of sync with your database:

```bash
# Manual data import (for compressed files)
gunzip -c database/exports/data_export_2025-10-15_14-30-00.sql.gz | mysql -u username -p database

# Step 2: Generate fresh migrations from current database schema
php artisan model:schema-check --sync-migrations

# Step 3: Review and run the new migrations
php artisan migrate:fresh

# Step 4: Import your data back
php artisan model:schema-check --import-data
```

### Advanced Migration Management

```bash
# Clean up old migration files (with backup)
php artisan model:schema-check --cleanup-migrations

# Export data only
php artisan model:schema-check --export-data

# Import data only
php artisan model:schema-check --import-data
```

## Migration Synchronization Features

### 🔄 **Complete Migration Recreation**

- **Schema Analysis**: Introspect current database structure
- **Fresh Migration Generation**: Create new migrations from existing schema
- **Index Preservation**: Maintain database indexes and constraints
- **Foreign Key Handling**: Preserve relationships and constraints

### 💾 **Data Preservation**

- **Safe Data Export**: Export all table data before changes
- **SQL Format**: Generate portable SQL export files
- **Foreign Key Management**: Handle constraints during import/export
- **Rollback Capability**: Restore data if needed

### 🧹 **Migration Cleanup**

- **Backup Creation**: Automatic backup of existing migrations
- **Safe Deletion**: Remove old files only after backup
- **Timestamp Tracking**: Organize backups by date/time
- **Recovery Options**: Easy restoration if needed

### 📊 **Schema Diffing**

- **Current vs Expected**: Compare database with migration expectations
- **Missing Elements**: Identify missing tables, columns, indexes
- **Extra Elements**: Find database elements not in migrations
- **Synchronization Reports**: Detailed change recommendations

## Migration Synchronization Workflow

### Scenario: Migrations Out of Sync

When your `database/migrations/` files no longer match your actual database structure:

```text
1. Database has evolved with direct SQL changes
2. Migration files are missing or incorrect
3. Need to recreate migrations from scratch
4. Must preserve existing data
```

### Safe Synchronization Process

```bash
# 📤 STEP 1: Export Current Data
php artisan model:schema-check --export-data
# Creates: database/exports/data_export_2025-10-15_14-30-00.sql.gz
# 💡 Compressed format saves 70-90% disk space for large databases

# 🔄 STEP 2: Generate Fresh Migrations
php artisan model:schema-check --sync-migrations
# - Analyzes current database schema
# - Backs up old migrations to database/migrations_backup_2025-10-15_14-30-00/
# - Generates new migrations in database/migrations/

# 🏗️ STEP 3: Reset Database (Optional)
php artisan migrate:fresh
# Recreates database from new migrations

# 📥 STEP 4: Import Data Back
php artisan model:schema-check --import-data
# Imports data from the most recent export file
```

### Recovery Options

```bash
# View available exports
ls database/exports/

# Restore old migrations if needed
cp -r database/migrations_backup_*/database/migrations/

# Check migration status
php artisan migrate:status
```

### Safety Features

- **Automatic Backups**: All operations create timestamped backups
- **Data Validation**: Export/import includes foreign key management
- **Dry Run Support**: Test operations without making changes
- **Rollback Capability**: Restore from backups if needed
- **Confirmation Prompts**: Interactive confirmation for destructive operations

```bash
# Basic model and schema check
php artisan model:schema-check

# Run all checks
php artisan model:schema-check --all

# Check security vulnerabilities only
php artisan model:schema-check --security

# Check performance issues
php artisan model:schema-check --performance

# Check relationships and foreign keys
php artisan model:schema-check --relationships

# Fix model fillable properties
php artisan model:schema-check --fix

# Check Laravel forms and get amendment suggestions
php artisan model:schema-check --laravel-forms

# Check forms with automatic fixing (interactive)
php artisan model:schema-check --laravel-forms --fix

# Check encrypted fields security and sizing
php artisan model:schema-check --check-encrypted-fields

# Check encrypted fields and apply automatic fixes
php artisan model:schema-check --check-encrypted-fields --fix

# Check model quality only
php artisan model:schema-check --check-models

# Check controller quality only
php artisan model:schema-check --check-controllers

# Check migration quality only
php artisan model:schema-check --check-migrations-quality

# Check models excluding API models
php artisan model:schema-check --check-models --check-models-exclude="**/Api/**"

# Check controllers excluding resource controllers
php artisan model:schema-check --check-controllers --check-controllers-exclude="**/ResourceController.php"

# Generate migrations for schema differences
php artisan model:schema-check --generate-migrations --dry-run
```

## Features

### 🔍 **Comprehensive Model Validation**

- Compare model fillable properties with database columns
- Validate mass assignment protection
- Check for missing or extra fillable properties

### 🔗 **Relationship & Foreign Key Validation**

- Validate model relationship definitions
- Check foreign key constraints against database
- Verify relationship naming conventions
- Detect missing inverse relationships

### 🛡️ **Security Vulnerability Detection**

- XSS (Cross-Site Scripting) vulnerability detection
- CSRF (Cross-Site Request Forgery) protection validation
- SQL injection vulnerability scanning
- Path traversal attack prevention
- File upload security validation

## Form Amendment Suggestions

### ✅ **Validation Rule Analysis**

- Check validation rules against database schema
- Validate required field coverage
- Verify rule consistency with column types
- Check Form Request class structure

The Laravel Model Schema Checker now includes intelligent form analysis and amendment suggestions to help you create better, more complete forms.

### 🔍 **Form Analysis Features**

#### **Model-Form Synchronization**

- Automatically identifies which model a form is associated with
- Compares form fields against model fillable properties
- Validates form fields against model validation rules
- Detects missing required fields in create/update/delete forms

#### **Field Requirement Validation**

- Identifies fields marked as required when they shouldn't be
- Finds required fields missing the HTML `required` attribute
- Suggests proper field requirements based on model validation rules

#### **Input Type Optimization**

- Recommends `type="email"` for email fields
- Suggests `type="password"` for password inputs
- Advises `type="number"` for numeric database columns
- Recommends `type="date"` for date/time fields
- Suggests `<textarea>` for longer text content

#### **Livewire Component Support**

- Analyzes Livewire component properties against model requirements
- Validates Livewire validation rules
- Suggests missing component properties and validation rules

### 📋 **Amendment Suggestion Examples**

**Missing Required Field:**

```html
Required field 'email' is missing from the form.
Suggestion: Add: <input type="text" name="email" value="{{ old('email') }}" required>
```

**Incorrect Field Type:**

```html
Field 'email' appears to be an email field.
Suggestion: Consider using input type='email' for better validation and UX.
```

**Livewire Property Missing:**

```php
Fillable property 'title' is missing from Livewire component.
Suggestion: Add: public $title; to the component properties.
```

### 🔧 **Automatic Form Updates**

The checker can offer to automatically apply form amendments:

```bash
# Check forms and get amendment suggestions
# Check Laravel forms and get amendment suggestions
php artisan model:schema-check --laravel-forms

# Check forms with automatic fixing (interactive)
php artisan model:schema-check --laravel-forms --fix

# The checker will prompt:
# "Would you like me to automatically fix these form issues? (y/n)"
```

When you choose to auto-fix, the checker will:

- Add missing required fields to forms
- Correct field requirement attributes
- Optimize input types based on database schema
- Add missing Livewire component properties
- Update validation rules in Livewire components

### 🛡️ **Safety Features**

- **Backup Creation**: Automatic backup of form files before changes
- **Dry Run Support**: Preview changes without applying them
- **Confirmation Prompts**: Interactive confirmation for each change
- **Rollback Capability**: Restore from backups if needed

## Output & Reporting

The checker provides detailed reports categorized by issue type:

- **Models**: Fillable property validation
- **Relationships**: Foreign key and relationship checks
- **Security**: Vulnerability detection
- **Performance**: Query optimization suggestions
- **Code Quality**: Best practice violations
- **Migrations**: Schema consistency issues
- **Validation**: Rule validation problems
- **Forms**: Form completeness and amendment suggestions

Each issue includes:

- File location and line number
- Specific problem description
- Remediation recommendations
- Severity classification

## Security Considerations

The Laravel Model Schema Checker helps identify multiple security vulnerabilities:

- **Mass Assignment**: Ensures proper `$fillable` configuration
- **SQL Injection**: Detects unsafe database queries
- **XSS Vulnerabilities**: Identifies unescaped output in forms
- **CSRF Protection**: Validates token implementation
- **Path Traversal**: Checks file operation security
- **File Upload Security**: Validates upload handling

## Performance Optimization

The tool provides actionable recommendations for:

- **N+1 Query Prevention**: Detects relationship access in loops
- **Eager Loading**: Suggests `->with()` usage
- **Database Indexes**: Recommends indexes for large tables
- **Query Optimization**: Identifies inefficient patterns

## Code Quality Standards

Enforced Laravel best practices:

- **PSR Standards**: Namespace and naming conventions
- **Laravel Conventions**: Proper facade usage
- **Modern PHP**: Avoids deprecated features
- **Code Smells**: Detects long methods and duplicate code

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x
- MySQL, PostgreSQL, or SQLite database
- File system permissions for log writing

## Testing

The Laravel Model Schema Checker includes comprehensive testing to ensure compatibility across Laravel versions and maintain code quality.

### Test Suites

The package includes multiple test suites for different testing scenarios:

```bash
# Run all tests
ddev exec ./vendor/bin/phpunit

# Run supplier tests (external dependency testing)
ddev exec ./vendor/bin/phpunit --testsuite Suppliers

# Run reliable tests (stable unit tests for CI)
ddev exec ./vendor/bin/phpunit --testsuite ReliableTests

# Run specific test class
ddev exec ./vendor/bin/phpunit tests/ModelCheckerTest.php
```

### Supplier Testing Framework

Version 3.0 introduces comprehensive supplier testing to ensure robust handling of external dependencies:

- **Database Suppliers**: Mock PDO operations and connection failures
- **File System Suppliers**: Mock file operations and permission scenarios
- **API Suppliers**: Mock HTTP responses and network failures
- **Laravel Framework Suppliers**: Test facade-agnostic operations

```php
// Example supplier test pattern
public function test_database_supplier_failure()
{
    $dbMock = $this->createMock(\PDO::class);
    $dbMock->expects($this->once())
        ->method('prepare')
        ->willThrowException(new \PDOException('Connection lost'));

    $this->expectException(\PDOException::class);
    $this->executeQuery($dbMock, 'SELECT * FROM users');
}
```

### Running Tests

#### Local Testing

```bash
# Run basic local tests
./test-local.sh

# Run multi-version Laravel compatibility tests (uses DDEV)
./test-multi-version.sh

# Run DDEV integration tests
./test-ddev.sh
```

#### Composer Scripts

```bash
# Run all tests and code quality checks
composer test:all

# Run unit tests only
composer test

# Run with coverage
composer test:coverage

# Run PHPStan static analysis
composer stan

# Run PHP CodeSniffer
composer cs

# Fix code style issues
composer cs:fix

# Run PHPMD mess detector
composer md
```

### Test Matrix

The package is tested against multiple Laravel and PHP versions:

| Laravel Version | PHP Version | Status |
|----------------|-------------|--------|
| 10.x           | 8.1, 8.2, 8.3 | ✅ Supported |
| 11.x           | 8.2, 8.3     | ✅ Supported |
| 12.x           | 8.2, 8.3     | ✅ Supported |

### Continuous Integration

GitHub Actions automatically runs the full test suite on:

- Push to `main`, `master`, or `feature/version-3` branches
- Pull requests to `main`, `master`, or `feature/version-3`
- Tag pushes (for releases)

Tests include:

- Unit tests with PHPUnit
- Static analysis with PHPStan
- Code style checking with PHP CodeSniffer
- Mess detection with PHPMD
- Multi-version Laravel compatibility
- Security vulnerability scanning
- Composer validation

### Development Testing

For development and contribution:

1. **Setup**: Ensure DDEV is installed and configured
2. **Local Tests**: Run `./test-local.sh` for basic functionality
3. **Multi-Version**: Run `./test-multi-version.sh` for Laravel compatibility (uses isolated DDEV environments)
4. **Code Quality**: Run `composer test:all` before committing
5. **Integration**: Run `./test-ddev.sh` for full integration testing

### Test Coverage

Current test coverage includes:

- ✅ Package structure validation
- ✅ Service provider registration
- ✅ Command functionality
- ✅ Laravel version compatibility (10.x, 11.x, 12.x) using DDEV
- ✅ Code quality standards
- ✅ Static analysis
- ✅ Security checks

### DDEV Testing Environment

The multi-version tests use isolated DDEV environments for each Laravel version:

- **Laravel 10**: PHP 8.1, MariaDB 10.4
- **Laravel 11**: PHP 8.2, MariaDB 10.4
- **Laravel 12**: PHP 8.2, MariaDB 10.4

DDEV configurations are stored in `ddev-configs/` directory and automatically create clean, isolated test environments that are cleaned up after testing.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
