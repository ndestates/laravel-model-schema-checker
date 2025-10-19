# Laravel Model Schema Checker

A comprehensive Laravel tool for validating models, relationships, security, performance, code quality, and **form amendment suggestions** across your entire Laravel application.

## Compatibility

- **Laravel**: 10.x, 11.x, 12.x
- **PHP**: 8.1+

## Installation

Install the package via Composer:

```bash
composer require ndestates/laravel-model-schema-checker --dev
```

### Laravel Auto-Discovery

The package uses Laravel's auto-discovery feature, so it will be automatically registered.

### Publish Configuration (Optional)

You can publish the configuration file to customize the behavior:

```bash
php artisan vendor:publish --provider="NDEstates\LaravelModelSchemaChecker\ModelSchemaCheckerServiceProvider" --tag="config"
```

This will create a `config/model-schema-checker.php` file where you can customize:
- Model directory
- Excluded fields
- Database connection
- Migration directory
- Other settings

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

```
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

### 📊 **Migration Consistency Checks**
- Validate migration naming conventions
- Check for missing database indexes
- Verify column constraints and defaults
- Ensure migration files follow Laravel standards

### ✅ **Validation Rule Analysis**
- Check validation rules against database schema
- Validate required field coverage
- Verify rule consistency with column types
- Check Form Request class structure

### ⚡ **Performance Optimization**
- N+1 query detection
- Eager loading validation
- Database index recommendations
- Inefficient query pattern identification

### 🎯 **Code Quality Assurance**
- Namespace validation
- Naming convention enforcement
- Deprecated feature detection
- Code smell identification
- Unused import detection
- **Granular Code Quality Checks**: Dedicated commands for models, controllers, and migrations
- **Path-Based Filtering**: Include/exclude specific files or directories
- **Targeted Analysis**: Run quality checks on specific application components

### 🔍 **Granular Code Quality Checks**

The Laravel Model Schema Checker provides dedicated commands for targeted code quality analysis, allowing you to focus on specific parts of your application:

#### **Model Quality Checks (`--check-models`)**
- Validates model fillable properties and mass assignment protection
- Checks relationship method implementations
- Analyzes scope method usage and efficiency
- Detects deprecated Eloquent methods
- Validates model naming conventions

#### **Controller Quality Checks (`--check-controllers`)**
- Validates controller method signatures and naming
- Checks validation usage in controller methods
- Analyzes authorization implementation
- Detects query efficiency issues
- Reviews controller structure and organization

#### **Migration Quality Checks (`--check-migrations-quality`)**
- Validates migration file naming conventions
- Checks column type definitions and constraints
- Analyzes index usage and foreign key constraints
- Detects potential migration issues
- Reviews migration file organization

#### **Path-Based Filtering**
All granular checks support path filtering for precise control:

```bash
# Check only API models
php artisan model:schema-check --check-models --check-models-exclude="**/User.php"

# Check only web controllers, exclude API controllers
php artisan model:schema-check --check-controllers --check-controllers-exclude="**/Api/**"

# Check specific migration files
php artisan model:schema-check --check-migrations-quality --check-migrations-quality-exclude="*_create_sessions_table.php"
```

### 🎨 **Laravel-Specific Features**
- Filament form and relationship validation
- Blade template security checks
- Livewire component validation
- API resource validation
- **Form Amendment Suggestions**: Automatic form improvement recommendations

### 📝 **Form Amendment Suggestions**
- **Missing Field Detection**: Identifies required fields missing from forms
- **Field Requirement Validation**: Checks for incorrect required field markings
- **Input Type Optimization**: Suggests better input types (email, password, number, date)
- **Model-Form Synchronization**: Compares forms against model definitions
- **Livewire Component Analysis**: Validates Livewire properties and validation rules
- **Automatic Form Updates**: Interactive option to automatically fix form issues

## Form Amendment Suggestions

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
```
Required field 'email' is missing from the form.
Suggestion: Add: <input type="text" name="email" value="{{ old('email') }}" required>
```

**Incorrect Field Type:**
```
Field 'email' appears to be an email field.
Suggestion: Consider using input type='email' for better validation and UX.
```

**Livewire Property Missing:**
```
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

## Testing

The package includes comprehensive tests:

```bash
composer test
```

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

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.