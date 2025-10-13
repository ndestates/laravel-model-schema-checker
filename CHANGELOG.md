# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- `php check.php` - Run basic validation
- `php check.php --check-all` - Run comprehensive checks
- `php check.php --fix` - Apply automatic fixes
- `./run-checker.sh` - Environment-aware execution