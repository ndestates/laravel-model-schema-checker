# Changelog

All notable changes to- **ModelChecker, LaravelFormsChecker, ValidationChecker**: Fixed abstract class instantiation errors (commit: 79bd645)
  - Added ReflectionClass abstract checks to all checkers that instantiate model classes
  - Prevents "Cannot instantiate abstract class" errors across all analysis methods
  - Abstract model classes are now properly skipped in relationship, form, and validation analysis
- **LaravelFormsChecker**: Fixed validation rules parsing error (commit: 916720f)
  - Fixed TypeError when explode() receives array instead of string for validation rules
  - Added proper handling for both string and array rule formats from parseRulesArray()
- **FilamentChecker**: Fixed invalid class detection (commit: 1dcb3ca)
  - Removed autoload prevention in class_exists() check
  - Allows proper validation of Filament resource classes that haven't been loaded yet
  - Eliminates false "invalid class" warnings for valid Filament files
- **FilamentChecker**: Enhanced class loading and detection (commit: f03b6bd)
  - Added fallback file inclusion when autoloading fails for Filament classes
  - Improved class detection to handle abstract and final class declarations
  - Skip non-PHP files to avoid processing README.md and other non-code files
  - Added detailed debug logging to identify why files are skipped

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2025-10-19 (Unreleased - Feature Branch)

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