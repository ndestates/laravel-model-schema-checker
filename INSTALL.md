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
php artisan model:schema-check --help
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
php artisan model:schema-check --dry-run

# Actually fix the models
php artisan model:schema-check --fix
```

### Migration Generation
```bash
# Generate migrations for schema differences
php artisan model:schema-check --generate-migrations
```

### Analysis and Reporting
```bash
# Full analysis with JSON output
php artisan model:schema-check --analyze --json
```

## Web Dashboard Setup

The package includes a web dashboard for visual analysis and management:

1. **Run database migrations** to create required tables:
   ```bash
   php artisan migrate
   ```

2. **Publish assets** for proper styling:
   ```bash
   php artisan model-schema-checker:publish-assets
   ```
   
   Or manually:
   ```bash
   php artisan vendor:publish --tag=model-schema-checker-assets
   ```

3. **Access the dashboard** at `/model-schema-checker/dashboard`
4. **Smart authentication handling**:
   - **Production**: Authentication required (automatically disabled anyway)
   - **Development**: Works with or without authentication (guest users supported)

## Troubleshooting

### Package Not Found
If you get "Package not found" errors:
```bash
composer dump-autoload
```

### Permission Issues
If you get permission errors:
```bash
# Make sure artisan is executable
chmod +x artisan
```

### Laravel Not Detected
Make sure you're running from your Laravel project root directory where `artisan` exists.

## Development Setup

If you're contributing to this package:

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`
4. Check CI status before submitting PRs