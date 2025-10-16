# Laravel Model Schema Checker

A comprehensive Laravel tool for validating models, relationships, security, perfo### 💾 **Data Preservation**
- **Compressed Exports**: Export all table data to compressed `.sql.gz` files
- **Space Efficient**: Dramatically reduce file sizes for large databases (typically 70-90% compression)
- **Performance**: Faster export/import for large datasets
- **Foreign Key Management**: Handle constraints during export/import
- **Backward Compatible**: Can import both compressed and uncompressed filese, and code quality across your entire Laravel application.

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
- `--fix` - Fix model fillable properties and code quality issues (class naming, etc.)
- `--generate-migrations` - Generate Laravel migrations
- `--json` - Output results in JSON format

#### Specialized Checks
- `--relationships` - Check model relationships and foreign keys
- `--migrations` - Check migration consistency and indexes
- `--validation` - Check validation rules against schema
- `--performance` - Check for N+1 queries and optimization opportunities
- `--quality` - Check code quality and Laravel best practices
- `--filament` - Check Filament forms and relationships
- `--filament-resource=` - Check specific Filament resource
- `--security` - Check for XSS and CSRF vulnerabilities
- `--laravel-forms` - Check Laravel forms (Blade templates, Livewire)

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

# Fix model fillable properties and code quality issues
php artisan model:schema-check --fix

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
- Naming convention enforcement (PascalCase classes, camelCase methods)
- Deprecated feature detection
- Code smell identification
- Unused import detection
- **Automatic fixes** for naming convention violations

### 🎨 **Laravel-Specific Features**
- Filament form and relationship validation
- Blade template security checks
- Livewire component validation
- API resource validation

## Output & Reporting

The checker provides detailed reports categorized by issue type:

- **Models**: Fillable property validation
- **Relationships**: Foreign key and relationship checks
- **Security**: Vulnerability detection
- **Performance**: Query optimization suggestions
- **Code Quality**: Best practice violations
- **Migrations**: Schema consistency issues
- **Validation**: Rule validation problems

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
- **Automatic Fixes**: Use `--fix` to automatically correct class naming violations (converts to PascalCase)

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x
- MySQL, PostgreSQL, or SQLite database
- File system permissions for log writing

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.