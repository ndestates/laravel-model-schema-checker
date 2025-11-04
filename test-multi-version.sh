#!/bin/bash

# Multi-Version Testing Script for Laravel Model Schema Checker
# Tests against Laravel 10, 11, and 12 using DDEV

set -e

echo "🧪 Laravel Model Schema Checker - Multi-Version Test"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if DDEV is available
if ! command -v ddev &> /dev/null; then
    print_error "DDEV is not installed or not in PATH"
    echo "Please install DDEV: https://ddev.readthedocs.io/en/latest/users/install/"
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "composer.json" ] || [ ! -d "src" ]; then
    print_error "Please run this from the laravel-model-schema-checker root directory"
    exit 1
fi

# Store the project root directory
PROJECT_ROOT="$(pwd)"

# Laravel versions to test
LARAVEL_VERSIONS=("10" "11" "12")

print_status "Testing against Laravel versions: ${LARAVEL_VERSIONS[*]} using DDEV"
print_status "Each version will run in its own isolated DDEV environment"

# Function to test a specific Laravel version
test_laravel_version() {
    local laravel_version=$1
    local project_name="test-laravel-$laravel_version"
    local project_dir="/tmp/$project_name"

    print_status "Testing Laravel $laravel_version with DDEV..."

    # Clean up any existing DDEV project
    if ddev list | grep -q "$project_name"; then
        print_status "Removing existing DDEV project: $project_name"
        ddev stop "$project_name" > /dev/null 2>&1 || true
        ddev delete "$project_name" --omit-snapshot > /dev/null 2>&1 || true
    fi

    # Clean up project directory
    rm -rf "$project_dir"

    # Create project directory
    mkdir -p "$project_dir"
    cd "$project_dir"

    # Initialize DDEV project
    print_status "Initializing DDEV project for Laravel $laravel_version..."

    # Copy DDEV configuration template
    if [ -f "$PROJECT_ROOT/ddev-configs/laravel-$laravel_version.yaml" ]; then
        mkdir -p .ddev
        cp "$PROJECT_ROOT/ddev-configs/laravel-$laravel_version.yaml" .ddev/config.yaml
        # Update the project name in the config
        sed -i "s/name: test-laravel-$laravel_version/name: $project_name/" .ddev/config.yaml
    else
        print_error "DDEV configuration template not found for Laravel $laravel_version at $PROJECT_ROOT/ddev-configs/laravel-$laravel_version.yaml"
        cd - > /dev/null
        return 1
    fi

    # Create Laravel project using DDEV
    print_status "Creating Laravel $laravel_version project with DDEV..."
    if ! ddev composer create --no-interaction --prefer-dist laravel/laravel:^${laravel_version}.0 .; then
        print_error "Failed to create Laravel $laravel_version project with DDEV"
        cd - > /dev/null
        return 1
    fi

    print_success "Laravel $laravel_version project created with DDEV"

    # Start DDEV project
    print_status "Starting DDEV project..."
    if ! ddev start > /dev/null 2>&1; then
        print_error "Failed to start DDEV project for Laravel $laravel_version"
        cd - > /dev/null
        return 1
    fi

    # Install package by copying files directly to vendor directory
    print_status "Installing Laravel Model Schema Checker package..."

    # Create vendor directory structure
    mkdir -p vendor/ndestates

    # Copy package files directly
    cp -r "$PROJECT_ROOT" vendor/ndestates/laravel-model-schema-checker

    # Remove the copied test directories to avoid conflicts
    rm -rf vendor/ndestates/laravel-model-schema-checker/test-package
    rm -rf vendor/ndestates/laravel-model-schema-checker/ddev-configs

    # Run composer install to ensure dependencies are resolved
    if ! ddev composer install --no-interaction; then
        print_error "Failed to install dependencies in Laravel $laravel_version"
        ddev stop > /dev/null 2>&1 || true
        cd - > /dev/null
        return 1
    fi

    # Register the package
    if ! ddev artisan package:discover --ansi; then
        print_error "Failed to discover package in Laravel $laravel_version"
        ddev stop > /dev/null 2>&1 || true
        cd - > /dev/null
        return 1
    fi

    print_success "Package installed in Laravel $laravel_version"

    # Test basic functionality
    print_status "Testing basic functionality..."

    # Test help command
    if ddev artisan model:schema-check --help > /dev/null 2>&1; then
        print_success "Help command works in Laravel $laravel_version"
    else
        print_error "Help command failed in Laravel $laravel_version"
        ddev stop > /dev/null 2>&1 || true
        cd - > /dev/null
        return 1
    fi

    # Test dry run
    if ddev artisan model:schema-check --dry-run > /dev/null 2>&1; then
        print_success "Dry run works in Laravel $laravel_version"
    else
        print_error "Dry run failed in Laravel $laravel_version"
        ddev stop > /dev/null 2>&1 || true
        cd - > /dev/null
        return 1
    fi

    # Test backup functionality
    if ddev artisan model:schema-check --backup > /dev/null 2>&1; then
        print_success "Backup functionality works in Laravel $laravel_version"
    else
        print_error "Backup functionality failed in Laravel $laravel_version"
        ddev stop > /dev/null 2>&1 || true
        cd - > /dev/null
        return 1
    fi

    # Stop DDEV project
    print_status "Stopping DDEV project..."
    ddev stop > /dev/null 2>&1 || true

    cd - > /dev/null

    # Cleanup
    rm -rf "$project_dir"
    print_success "Laravel $laravel_version test completed successfully"
    return 0
}

# Run tests for each Laravel version
FAILED_VERSIONS=()

for version in "${LARAVEL_VERSIONS[@]}"; do
    if test_laravel_version "$version"; then
        print_success "✅ Laravel $version: PASSED"
    else
        print_error "❌ Laravel $version: FAILED"
        FAILED_VERSIONS+=("$version")
    fi
    echo ""
done

# Run local code quality checks
print_status "Running code quality checks..."

# Run PHPStan
if composer stan > /dev/null 2>&1; then
    print_success "PHPStan analysis passed"
else
    print_error "PHPStan analysis failed"
    exit 1
fi

# Run PHPUnit
if composer test > /dev/null 2>&1; then
    print_success "Unit tests passed"
else
    print_error "Unit tests failed"
    exit 1
fi

# Run PHP CodeSniffer
if composer cs > /dev/null 2>&1; then
    print_success "Code style check passed"
else
    print_error "Code style check failed"
    exit 1
fi

# Run PHPMD
if composer md > /dev/null 2>&1; then
    print_success "Mess detection passed"
else
    print_warning "Mess detection found issues (review output above)"
fi

# Summary
echo ""
echo "📊 Test Summary"
echo "=============="

if [ ${#FAILED_VERSIONS[@]} -eq 0 ]; then
    print_success "🎉 All tests passed!"
    print_success "Laravel Model Schema Checker is compatible with Laravel ${LARAVEL_VERSIONS[*]} using DDEV"
else
    print_error "❌ Some tests failed for Laravel versions: ${FAILED_VERSIONS[*]}"
    exit 1
fi

echo ""
print_status "Next steps:"
echo "1. Review any code quality issues found"
echo "2. Run manual testing: ./test-ddev.sh"
echo "3. Update documentation if needed"
echo "4. Prepare for v3.0.0 release"
echo ""
print_status "Note: All DDEV test environments have been cleaned up"