#!/bin/bash

# Simple Test Script for Laravel Model Schema Checker
# Tests installation in a single Laravel version using DDEV

set -e

echo "🧪 Laravel Model Schema Checker - Simple Test"
echo "============================================="

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

# Test Laravel 11 (current LTS)
LARAVEL_VERSION="11"
PROJECT_NAME="test-laravel-$LARAVEL_VERSION"
PROJECT_DIR="/tmp/$PROJECT_NAME"

print_status "Testing Laravel $LARAVEL_VERSION with DDEV..."

# Clean up any existing DDEV project
if ddev list | grep -q "$PROJECT_NAME"; then
    print_status "Removing existing DDEV project: $PROJECT_NAME"
    ddev stop "$PROJECT_NAME" > /dev/null 2>&1 || true
    ddev delete "$PROJECT_NAME" --omit-snapshot > /dev/null 2>&1 || true
fi

# Clean up project directory
rm -rf "$PROJECT_DIR"

# Create project directory
mkdir -p "$PROJECT_DIR"
cd "$PROJECT_DIR"

# Initialize DDEV project
print_status "Initializing DDEV project for Laravel $LARAVEL_VERSION..."

# Copy DDEV configuration template
if [ -f "$PROJECT_ROOT/ddev-configs/laravel-$LARAVEL_VERSION.yaml" ]; then
    mkdir -p .ddev
    cp "$PROJECT_ROOT/ddev-configs/laravel-$LARAVEL_VERSION.yaml" .ddev/config.yaml
    # Update the project name in the config
    sed -i "s/name: test-laravel-$LARAVEL_VERSION/name: $PROJECT_NAME/" .ddev/config.yaml
else
    print_error "DDEV configuration template not found for Laravel $LARAVEL_VERSION"
    cd - > /dev/null
    exit 1
fi

# Create Laravel project using DDEV
print_status "Creating Laravel $LARAVEL_VERSION project with DDEV..."
if ! ddev composer create --no-interaction --prefer-dist laravel/laravel:^${LARAVEL_VERSION}.0 .; then
    print_error "Failed to create Laravel $LARAVEL_VERSION project with DDEV"
    cd - > /dev/null
    exit 1
fi

print_success "Laravel $LARAVEL_VERSION project created with DDEV"

# Start DDEV project
print_status "Starting DDEV project..."
if ! ddev start > /dev/null 2>&1; then
    print_error "Failed to start DDEV project for Laravel $LARAVEL_VERSION"
    cd - > /dev/null
    exit 1
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
    print_error "Failed to install dependencies in Laravel $LARAVEL_VERSION"
    ddev stop > /dev/null 2>&1 || true
    cd - > /dev/null
    exit 1
fi

# Register the package
if ! ddev artisan package:discover --ansi; then
    print_error "Failed to discover package in Laravel $LARAVEL_VERSION"
    ddev stop > /dev/null 2>&1 || true
    cd - > /dev/null
    exit 1
fi

print_success "Package installed in Laravel $LARAVEL_VERSION"

# Test basic functionality
print_status "Testing basic functionality..."

# Test help command
if ddev artisan model:schema-check --help > /dev/null 2>&1; then
    print_success "Help command works in Laravel $LARAVEL_VERSION"
else
    print_error "Help command failed in Laravel $LARAVEL_VERSION"
    ddev stop > /dev/null 2>&1 || true
    cd - > /dev/null
    exit 1
fi

# Test dry run
if ddev artisan model:schema-check --dry-run > /dev/null 2>&1; then
    print_success "Dry run works in Laravel $LARAVEL_VERSION"
else
    print_error "Dry run failed in Laravel $LARAVEL_VERSION"
    ddev stop > /dev/null 2>&1 || true
    cd - > /dev/null
    exit 1
fi

# Stop DDEV project
print_status "Stopping DDEV project..."
ddev stop > /dev/null 2>&1 || true

cd - > /dev/null

# Cleanup
rm -rf "$PROJECT_DIR"
print_success "Laravel $LARAVEL_VERSION test completed successfully"

print_success "✅ Laravel $LARAVEL_VERSION: PASSED"
echo ""
print_success "🎉 Package installation and basic functionality verified!"