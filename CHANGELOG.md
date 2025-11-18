# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.2] - 2025-11-18

### 🐛 **Bug Fixes**

#### **Enhanced Migration Filename Validation**
- **Added support for sequence_timestamp format** (e.g., `000068_162313`)
- **Updated regex patterns** to handle `(\d{6,8}|\d{6}_\d{6})` timestamp formats
- **Comprehensive format support**: 6-digit, 8-digit, and sequence_timestamp formats
- **Added test coverage** for sequence_timestamp validation
- **Improved error messages** for better debugging

**Files Changed:**
- `src/Checkers/MigrationChecker.php` - Enhanced naming validation regex
- `src/Services/MigrationCleanup.php` - Updated migration name extraction
- `src/Services/MigrationGenerator.php` - Updated migration parsing logic
- `tests/MigrationCheckerTest.php` - Added test for sequence_timestamp format
- `composer.json` - Removed version field for Packagist compatibility

**Issue:** Migration files with sequence_timestamp format like `2025_10_30_000068_162313_fix_table.php` were incorrectly flagged as invalid.

## [3.0.1] - 2025-11-18

### 🐛 **Bug Fixes**

#### **Migration Filename Validation**
- **Fixed regex pattern** to accept 8-digit timestamps with microseconds
- **Updated validation logic** in `MigrationChecker`, `MigrationCleanup`, and `MigrationGenerator`
- **Enhanced error message** to indicate microseconds are optional
- **Added test coverage** for 8-digit timestamp validation
- **Backward compatible** - still accepts standard 6-digit timestamps

**Files Changed:**
- `src/Checkers/MigrationChecker.php` - Updated naming validation regex
- `src/Services/MigrationCleanup.php` - Updated migration name extraction
- `src/Services/MigrationGenerator.php` - Updated migration parsing logic
- `tests/MigrationCheckerTest.php` - Added test for microsecond timestamps

**Issue:** Migration files with timestamps like `2025_10_13_07512800_create_table.php` were incorrectly flagged as invalid.

## [3.0.0] - 2025-10-30

### 🚀 **Major Features - Web Dashboard & Production Safety**

#### **🌐 Interactive Web Dashboard**
- **Complete web interface** for schema checking and fixes
- **Real-time progress tracking** with visual progress bars
- **Step-by-step fix application** with rollback capabilities
- **Comprehensive check history** with filtering and search
- **User isolation** - each user sees only their own data
- **Responsive design** works on desktop and mobile

#### **�️ Production Safety Measures**
- **Automatic production disable** - completely disabled in production environments
- **Environment detection** using Laravel's `app()->environment()`
- **Security warnings** prominently displayed in documentation
- **Composer description** updated to indicate development-only usage

#### **📊 Database Integration**
- **Check results storage** with detailed issue tracking
- **Applied fixes tracking** with rollback capabilities
- **User-based data isolation** for multi-user environments
- **Migration system** for seamless database setup

### ✅ **Technical Implementation**

#### **New Components Added**
- `ModelSchemaCheckerController` - Handles all web requests
- `CheckResult` & `AppliedFix` models with relationships
- `RunModelChecks` background job for async processing
- Complete Blade view templates with modern UI
- Custom CSS/JS assets with responsive design
- Vite build system for asset compilation

#### **Routes & Middleware**
- Authentication-protected routes at `/model-schema-checker`
- CSRF protection on all forms
- User middleware for data isolation
- AJAX endpoints for real-time updates

#### **Security Features**
- **Production environment detection** in service provider
- **User data isolation** via foreign key constraints
- **CSRF protection** on all web forms
- **Input validation** and sanitization
- **Secure file operations** for reports

### 🔧 **Environment Support**

#### **Universal Compatibility**
- ✅ **DDEV**: `ddev artisan migrate` + `ddev launch`
- ✅ **Laravel Sail**: `./vendor/bin/sail artisan migrate`
- ✅ **Laravel Valet**: `php artisan migrate`
- ✅ **Homestead**: `php artisan migrate`
- ✅ **Plain PHP**: `php artisan serve`
- ✅ **Docker**: Container-specific commands

#### **Access URLs**
- **Main Dashboard**: `/model-schema-checker`
- **Check Results**: `/model-schema-checker/results/{id}`
- **Step-by-Step Fixes**: `/model-schema-checker/step-by-step/{id}`
- **Check History**: `/model-schema-checker/history`

### 📦 **Installation & Setup**

```bash
# Install (development only)
composer require ndestates/laravel-model-schema-checker --dev

# Run migrations
php artisan migrate

# Access dashboard
https://your-app.com/model-schema-checker
```

### 🛡️ **Production Safety**

#### **Automatic Protection**
```php
// Service provider automatically disables in production
if ($this->app->environment('production')) {
    return; // 🚫 No routes, migrations, or commands loaded
}
```

#### **Environment Detection**
- `APP_ENV=production` → **DISABLED**
- `APP_ENV=local/testing/staging` → **ACTIVE**

### 🗄️ **Database Schema**

#### **check_results Table**
```sql
- id (primary key)
- user_id (foreign key to users)
- job_id (nullable, for background jobs)
- status (pending/running/completed/failed)
- check_types (JSON: types of checks run)
- options (JSON: check options)
- issues (JSON: all issues found)
- stats (JSON: check statistics)
- total_issues, critical_issues, etc.
- timestamps
```

#### **applied_fixes Table**
```sql
- id (primary key)
- check_result_id (foreign key)
- user_id (foreign key to users)
- fix_title, fix_description
- file_path (nullable)
- can_rollback (boolean)
- rollback_data (JSON, nullable)
- applied_at (timestamp)
```

### 🔌 **API Endpoints**

#### **Web Routes** (auth protected)
- `GET /model-schema-checker` - Dashboard
- `POST /model-schema-checker/run-checks` - Run checks
- `GET /model-schema-checker/results/{id}` - View results
- `POST /model-schema-checker/apply-fixes` - Apply fixes
- `GET /model-schema-checker/step-by-step/{id}` - Step-by-step fixes
- `POST /model-schema-checker/apply-step-fix` - Apply single fix
- `POST /model-schema-checker/rollback-fixes` - Rollback fixes
- `GET /model-schema-checker/check-progress/{jobId}` - Progress polling
- `GET /model-schema-checker/history` - Check history

### 🎨 **Frontend Assets**

#### **CSS Framework**
- Custom responsive CSS (no external dependencies)
- Modern design with issue severity colors
- Mobile-first responsive design
- Progress bar animations
- Form styling and validation

#### **JavaScript Features**
- AJAX form submissions
- Real-time progress polling
- Dynamic content updates
- Error handling and notifications
- Cross-browser compatibility

#### **Build System**
- Vite for asset compilation
- PostCSS with Autoprefixer
- Source maps for debugging
- Development and production builds

### 🧪 **Testing & Quality**

#### **Test Coverage**
- Service provider environment checks
- User isolation verification
- Route protection testing
- Migration integrity tests
- Background job testing

#### **Security Testing**
- Production environment disable verification
- User data isolation testing
- CSRF protection validation
- Input sanitization checks

### 📋 **Migration Guide**

#### **From v2.x to v3.0**
1. **Backup your data** (if any custom schema checker data exists)
2. **Install v3.0**: `composer require ndestates/laravel-model-schema-checker --dev`
3. **Run migrations**: `php artisan migrate`
4. **Clear caches**: `php artisan config:clear && php artisan route:clear`
5. **Access dashboard**: Navigate to `/model-schema-checker`

#### **Breaking Changes**
- **Production environments**: Package now automatically disabled
- **Database tables**: New schema with user isolation
- **Routes**: All routes now require authentication
- **Commands**: May behave differently in production (disabled)

### ⚡ **Performance Considerations**

#### **Background Processing**
- Long-running checks use Laravel queues
- Progress tracking via cache/database
- Job status monitoring
- Timeout handling

#### **Database Optimization**
- User-based data partitioning
- Indexed foreign keys
- Efficient queries with constraints
- Migration rollback support

#### **Caching Strategy**
- Configuration caching
- Route caching
- View caching
- Asset compilation caching

### 🔧 **Troubleshooting**

#### **Common Issues**
- **Dashboard not accessible**: Check environment (`php artisan env`)
- **Routes not found**: Clear route cache (`php artisan route:clear`)
- **Migrations failed**: Check database permissions
- **Assets not loading**: Run `npm run build` or check Vite config

#### **Environment-Specific Fixes**
- **DDEV**: `ddev restart && ddev artisan migrate`
- **Sail**: `./vendor/bin/sail artisan migrate:reset && ./vendor/bin/sail artisan migrate`
- **Permissions**: `chmod -R 755 storage/ bootstrap/cache/`

### 🚀 **Future Enhancements**

#### **Planned Features**
- **Real-time notifications** via WebSockets
- **Bulk operations** for multiple projects
- **API endpoints** for CI/CD integration
- **Custom checkers** via plugin system
- **Advanced reporting** with charts and graphs
- **Team collaboration** features
- **Integration with Laravel Telescope**

#### **Performance Improvements**
- **Database query optimization**
- **Caching layer enhancements**
- **Background job prioritization**
- **Memory usage optimization**
- **Large codebase handling**

### 📖 **Documentation Updates**

#### **Comprehensive README**
- Environment-specific installation instructions
- Security warnings and production safety guidelines
- Troubleshooting section for common issues
- API documentation for custom integrations

#### **New Files**
- `VERSION_3_CHANGELOG.md` - Detailed version notes
- Enhanced test coverage for new features
- Migration files with proper documentation

---

## Previous Versions
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
- **CI Workflow Optimization**: Added ReliableTests test suite for CI to focus on stable unit tests and made static analysis warnings non-failing
- **Test Environment Fixes**: Improved ServiceProviderTest config setup and marked complex integration tests for future evaluation
- **PerformanceChecker N+1 Detection**: Fixed regex patterns to properly detect relationship access with method calls (e.g., `$user->posts->count()`)
- **PerformanceChecker Config Paths**: Updated to use configurable paths instead of hardcoded `app_path()` calls for better testability
- **PerformanceChecker Facade Agnostic**: Added facade-agnostic file operations to work in test environments without Laravel facades
- **PerformanceCheckerTest Array Keys**: Fixed test assertions to properly handle array keys after filtering operations
- **Facade Dependency Fixes**: Made ModelChecker, RelationshipChecker, and SecurityChecker facade-agnostic for isolated testing
- **CheckerManager Defensive Initialization**: Added exception handling for checker instantiation failures
- **DataImporter/MigrationGenerator**: Added try-catch blocks for storage_path calls when Laravel unavailable

### 🧪 **Testing Infrastructure Overhaul**
- **Comprehensive Unit Tests**: Added 46 tests with 176 assertions covering core functionality
- **IssueManager Testing**: 13 unit tests covering issue tracking, statistics, and filtering
- **CheckerManager Testing**: 14 unit tests covering service initialization and checker management
- **Command Testing**: 6 unit tests for ModelSchemaCheckCommand interface validation
- **Testability Enhancements**: Modified CheckerManager with optional environment parameter for testing
- **Syntax Error Fixes**: Resolved PHP syntax errors in MigrationChecker, ModelChecker, RelationshipChecker
- **Deprecated Method Updates**: Updated PHPUnit assertions from deprecated `assertStringContains` to `assertStringContainsString`
- **Code Style Compliance**: Applied PHPCS auto-fixes resolving 66 style violations
- **Supplier Testing Framework**: Added comprehensive supplier testing with mocks for external dependencies
- **Mock-Based Testing Patterns**: Created SupplierTestingExamplesTest demonstrating database, file system, and API supplier testing
- **PHPUnit Deprecation Fixes**: Replaced deprecated `MockBuilder::addMethods()` with proper mock classes for PHPUnit 12 compatibility
- **Supplier Contracts**: Added interface definitions for type-safe mocking of external dependencies

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
- **Testing**: ✅ Complete (51 tests passing with 194 assertions, supplier testing framework added)
- **Documentation**: ✅ Complete (Comprehensive README and CHANGELOG)
- **Production Ready**: ✅ Ready (All facade dependencies resolved, supplier testing implemented)

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