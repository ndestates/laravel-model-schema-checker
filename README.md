# Laravel Model Schema Checker

A comprehensive Laravel tool for validating model fillable properties against database schema and checking Filament relationship integrity.

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

Run the checker with the basic command:

```bash
php vendor/ndestates/laravel-model-schema-checker/check.php
```

### Available Options

- `--help` - Show help information
- `--dry-run` - Show what would be changed without making changes
- `--fix` - Fix model fillable properties
- `--generate-migrations` - Generate Laravel migrations
- `--analyze` - Analyze models and database schema
- `--json` - Output results in JSON format

### Examples

```bash
# Check models against database schema (dry run)
php vendor/ndestates/laravel-model-schema-checker/check.php --dry-run

# Fix model fillable properties
php vendor/ndestates/laravel-model-schema-checker/check.php --fix

# Generate migrations for schema differences
php vendor/ndestates/laravel-model-schema-checker/check.php --generate-migrations

# Analyze and output JSON report
php vendor/ndestates/laravel-model-schema-checker/check.php --analyze --json
```

## Features

- **Model Validation**: Compare model fillable properties with database columns
- **Schema Analysis**: Analyze database schema and model relationships
- **Migration Generation**: Generate Laravel migrations for schema differences
- **Filament Integration**: Check Filament form and relationship integrity
- **Flexible Configuration**: Customizable via configuration file
- **Multiple Output Formats**: Human-readable and JSON output

## Testing

The package includes comprehensive tests:

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Features

- **Model-Database Validation**: Automatically scans all Eloquent models and compares their `$fillable` attributes with corresponding database table columns
- **Filament Relationship Checking**: Validates that all relationships referenced in Filament resources actually exist on their models
- **Automatic Fixes**: Can automatically fix model fillable arrays to match database schema
- **Migration Generation**: Generate Laravel migrations for missing database columns
- **Comprehensive Logging**: Detailed logs with timestamps for all operations
- **Environment Detection**: Automatically detects DDEV, Docker, or local environments

## Installation

### Option 1: Composer Package (Recommended)

```bash
composer require ndestates/laravel-model-schema-checker --dev
```

### Option 2: Automated Installation Script

Download and run the installation script:

```bash
curl -fsSL https://raw.githubusercontent.com/ndestates/laravel-model-schema-checker/main/install.sh | bash
```

Or download and run manually:

```bash
wget https://raw.githubusercontent.com/ndestates/laravel-model-schema-checker/main/install.sh
chmod +x install.sh
./install.sh
```

### Option 3: Manual Installation

1. Download or clone this repository
2. Copy the `check.php` file to your Laravel project root
3. Copy the `check/` directory to your Laravel project root
4. Copy the `run-checker.sh` script to your Laravel project root
5. Make the script executable:

```bash
chmod +x run-checker.sh
```

## Package Structure

```
laravel-model-schema-checker/
├── check.php                    # Main validation script
├── run-checker.sh              # Environment-aware runner
├── install.sh                  # Automated installation script
├── check/                      # Core validation logic
│   ├── CHECK.md               # Comprehensive documentation
│   ├── commands/              # Command classes
│   └── utils/                 # Utility classes
├── src/                       # Composer package source
├── tests/                     # Test suite
├── composer.json              # Package configuration
├── phpunit.xml               # Test configuration
├── .github/                   # GitHub Actions workflows
└── README.md                  # This file
```

## Usage

### Basic Model-Database Check

```bash
# Using DDEV (recommended)
ddev exec php check.php

# Using standard PHP
php check.php
```

### Available Commands

```bash
php check.php                           # Compare models with database
php check.php --fix                     # Fix model fillable properties automatically
php check.php --dry-run                 # Show what would be changed without applying
php check.php --generate-migrations     # Generate Laravel migrations
php check.php --check-filament          # Check Filament relationship integrity
php check.php --check-all               # Run all available checks
```

### Environment-Aware Runner

The `run-checker.sh` script automatically detects your environment:

```bash
# Auto-detects DDEV/local/Docker environment
./run-checker.sh

# With backup option
./run-checker.sh --backup

# With automatic fixes
./run-checker.sh --fix
```

## Configuration

You can customize the script's behavior by modifying variables at the top of `check.php`:

- `$modelsDir`: Path to your models directory (defaults to `app/Models`)
- `$excludedFields`: Array of common fields to ignore during comparison
- `$databaseConnection`: Database connection to use

## Output

The script creates timestamped log files in `check/logs/` with detailed reports of:
- Model fillable properties that don't match database columns
- Missing database columns
- Extra fillable properties not in database
- Filament relationship validation results

## Security Considerations

- **Mass-Assignment Vulnerabilities**: Identifies discrepancies in `$fillable` arrays that could lead to security issues
- **Data Validation**: Ensures model properties align with database schema
- **Relationship Integrity**: Validates Filament resource relationships prevent runtime errors

## Requirements

- PHP 8.1+
- Laravel 10.x or 11.x
- MySQL/PostgreSQL/SQLite database

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

For support, please create an issue on GitHub or contact support@ndestates.com.