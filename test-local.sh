#!/bin/bash

# Local Testing Script for Laravel Model Schema Checker
# This will help you test the package before publishing

echo "🚀 Laravel Model Schema Checker - Local Test"
echo "============================================"

# Check if we're in the right directory
if [ ! -f "composer.json" ] || [ ! -d "src" ]; then
    echo "❌ Error: Please run this from the laravel-model-schema-checker root directory"
    exit 1
fi

echo "📋 Step 1: Testing Package Structure..."
if [ -f "src/ModelSchemaCheckerServiceProvider.php" ]; then
    echo "✅ Service Provider exists"
else
    echo "❌ Service Provider missing"
    exit 1
fi

if [ -f "config/model-schema-checker.php" ]; then
    echo "✅ Configuration file exists"
else
    echo "❌ Configuration file missing"
    exit 1
fi

echo "📋 Step 2: Composer Validation..."
composer validate --no-check-all --strict
if [ $? -eq 0 ]; then
    echo "✅ composer.json is valid"
else
    echo "❌ composer.json has issues"
    exit 1
fi

echo "📋 Step 3: Creating Test Laravel Project..."
rm -rf test-laravel-project
composer create-project laravel/laravel test-laravel-project --prefer-dist --no-progress --quiet

if [ $? -ne 0 ]; then
    echo "❌ Failed to create Laravel project"
    exit 1
fi

echo "✅ Laravel project created"

echo "📋 Step 4: Installing Package Locally..."
cd test-laravel-project

# Add local repository
composer config repositories.local path ../
composer require ndestates/laravel-model-schema-checker *@dev --quiet

if [ $? -ne 0 ]; then
    echo "❌ Failed to install package"
    exit 1
fi

echo "✅ Package installed successfully"

echo "📋 Step 5: Testing Package Commands..."

# Test basic help
php vendor/ndestates/laravel-model-schema-checker/check.php --help
if [ $? -eq 0 ]; then
    echo "✅ Help command works"
else
    echo "❌ Help command failed"
    exit 1
fi

# Test Laravel integration
php artisan --version
if [ $? -eq 0 ]; then
    echo "✅ Laravel is working"
else
    echo "❌ Laravel integration failed"
    exit 1
fi

echo ""
echo "🎉 SUCCESS! Local testing completed"
echo ""
echo "📋 Test Results:"
echo "• Package structure: ✅ Valid"
echo "• Composer validation: ✅ Passed"
echo "• Laravel integration: ✅ Working"
echo "• Command functionality: ✅ Working"
echo ""
echo "📋 Next steps:"
echo "1. Run multi-version testing: ./test-multi-version.sh"
echo "2. Run DDEV integration test: ./test-ddev.sh"
echo "3. Run code quality checks: composer test:all"
echo "4. Update documentation and prepare for release"
echo ""

# Cleanup
cd ..
rm -rf test-laravel-project

echo "🧹 Cleaned up test project"