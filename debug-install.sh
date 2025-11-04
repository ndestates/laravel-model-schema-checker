#!/bin/bash

# Debug script for package installation issues
echo "🔍 Debugging Package Installation..."

# Check if we're in DDEV
if [ -z "$DDEV_PROJECT" ]; then
    echo "❌ Not in DDEV environment"
    exit 1
fi

echo "✅ In DDEV environment: $DDEV_PROJECT"

# Create test Laravel project
echo "📦 Creating test Laravel project..."
rm -rf /tmp/test-laravel-debug
composer create-project laravel/laravel /tmp/test-laravel-debug --prefer-dist --quiet

if [ $? -ne 0 ]; then
    echo "❌ Failed to create Laravel project"
    exit 1
fi

echo "✅ Laravel project created"

# Try to install package
echo "📦 Installing package..."
cd /tmp/test-laravel-debug
composer config repositories.local path /var/www/html
composer require ndestates/laravel-model-schema-checker *@dev --quiet

if [ $? -ne 0 ]; then
    echo "❌ Package installation failed"
    echo "📋 Checking Laravel logs..."
    tail -20 storage/logs/laravel.log 2>/dev/null || echo "No Laravel logs found"
    exit 1
fi

echo "✅ Package installed successfully"

# Test basic functionality
echo "🧪 Testing basic functionality..."
php artisan model:schema-check --help

if [ $? -eq 0 ]; then
    echo "✅ Command help works"
else
    echo "❌ Command help failed"
    exit 1
fi

echo "🎉 All tests passed!"