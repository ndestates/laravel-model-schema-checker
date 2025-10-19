# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-10-19

### Added
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