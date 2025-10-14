# Installation Guide

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x

## Installation Steps

### Step 1: Install via Composer

For **development/testing** purposes (recommended):
```bash
composer require ndestates/laravel-model-schema-checker --dev
```

For **production** use:
```bash
composer require ndestates/laravel-model-schema-checker
```

### Step 2: Verify Installation

Check that the package is installed correctly:
```bash
php vendor/ndestates/laravel-model-schema-checker/check.php --help
```

### Step 3: Optional Configuration

Publish the configuration file to customize settings:
```bash
php artisan vendor:publish --provider="NDEstates\LaravelModelSchemaChecker\ModelSchemaCheckerServiceProvider" --tag="config"
```

## Laravel Version Specific Notes

### Laravel 10.x
- Fully supported
- All features available
- Tested with PHP 8.1, 8.2, 8.3

### Laravel 11.x  
- Fully supported
- All features available
- Tested with PHP 8.2, 8.3
- **Note**: Laravel 11 requires PHP 8.2+

### Laravel 12.x
- Fully supported
- All features available
- Tested with PHP 8.2, 8.3
- **Note**: Laravel 12 requires PHP 8.2+

## Usage Examples

### Basic Model Checking
```bash
# Dry run (safe, shows what would change)
php vendor/ndestates/laravel-model-schema-checker/check.php --dry-run

# Actually fix the models
php vendor/ndestates/laravel-model-schema-checker/check.php --fix
```

### Migration Generation
```bash
# Generate migrations for schema differences
php vendor/ndestates/laravel-model-schema-checker/check.php --generate-migrations
```

### Analysis and Reporting
```bash
# Full analysis with JSON output
php vendor/ndestates/laravel-model-schema-checker/check.php --analyze --json
```

## Troubleshooting

### Package Not Found
If you get "Package not found" errors:
```bash
composer dump-autoload
```

### Permission Issues
If you get permission errors:
```bash
chmod +x vendor/ndestates/laravel-model-schema-checker/check.php
```

### Laravel Not Detected
Make sure you're running from your Laravel project root directory where `artisan` exists.

## Development Setup

If you're contributing to this package:

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`
4. Check CI status before submitting PRs