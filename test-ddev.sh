#!/bin/bash

# DDEV Testing Script for Laravel Model Schema Checker
# This will help you test the package using DDEV before publishing

echo "🚀 Laravel Model Schema Checker - DDEV Test"
echo "==========================================="

# Check if DDEV is available
if ! command -v ddev &> /dev/null; then
    echo "❌ Error: DDEV is not installed or not in PATH"
    echo "Please install DDEV: https://ddev.readthedocs.io/en/latest/users/install/"
    exit 1
fi

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

echo "📋 Step 2: Starting DDEV..."
ddev start

if [ $? -ne 0 ]; then
    echo "❌ Failed to start DDEV"
    exit 1
fi

echo "✅ DDEV started successfully"

echo "📋 Step 3: Composer Validation..."
ddev composer validate --no-check-all --strict
if [ $? -eq 0 ]; then
    echo "✅ composer.json is valid"
else
    echo "❌ composer.json has issues"
    exit 1
fi

echo "📋 Step 4: Creating Test Laravel Project..."
ddev exec rm -rf /tmp/test-laravel-project
ddev exec bash -c "cd /tmp && composer create-project laravel/laravel test-laravel-project --prefer-dist --no-progress --quiet"

if [ $? -ne 0 ]; then
    echo "❌ Failed to create Laravel project"
    exit 1
fi

echo "✅ Laravel project created in /tmp"

echo "📋 Step 5: Installing Package Locally..."

# Add local repository and install package via DDEV
ddev exec bash -c "cd /tmp/test-laravel-project && composer config repositories.local path /var/www/html"
ddev exec bash -c "cd /tmp/test-laravel-project && composer require ndestates/laravel-model-schema-checker *@dev --quiet"

if [ $? -ne 0 ]; then
    echo "❌ Failed to install package"
    exit 1
fi

echo "✅ Package installed successfully"

echo "📋 Step 6: Setting up Laravel..."
# Copy environment file and generate key
ddev exec bash -c "cd /tmp/test-laravel-project && cp .env.example .env"
ddev exec bash -c "cd /tmp/test-laravel-project && php artisan key:generate --no-interaction"

if [ $? -ne 0 ]; then
    echo "❌ Failed to setup Laravel"
    exit 1
fi

echo "✅ Laravel configured"

echo "📋 Step 7: Testing Package Commands..."

# Test basic help
ddev exec bash -c "cd /tmp/test-laravel-project && php artisan model:schema-check --help"
if [ $? -eq 0 ]; then
    echo "✅ Help command works"
else
    echo "❌ Help command failed"
    exit 1
fi

# Test Laravel integration
ddev exec bash -c "cd /tmp/test-laravel-project && php artisan --version"
if [ $? -eq 0 ]; then
    echo "✅ Laravel is working"
else
    echo "❌ Laravel integration failed"
    exit 1
fi

echo ""
echo "🎉 SUCCESS! Package development testing completed"
echo ""
echo "📊 DDEV URLs:"
echo "- Web: https://laravel-model-schema-checker.ddev.site"
echo "- Mailpit: https://laravel-model-schema-checker.ddev.site:8026"
echo ""
echo "📋 Manual Testing Commands (run these in the test project):"
echo "ddev ssh"
echo "cd /tmp/test-laravel-project"
echo "php artisan model:schema-check --dry-run"
echo ""
echo "� Development Status - Version 3.0 (IN DEVELOPMENT):"
echo "• Core functionality: ✅ Implemented"
echo "• Migration safety features: ✅ Implemented"
echo "• Backup/rollback features: ✅ Implemented"
echo "• Testing: ✅ In progress"
echo "• Documentation: ⏳ Pending"
echo "• Publishing: ❌ Not ready"
echo ""
echo "📋 Next Development Steps:"
echo "1. Complete comprehensive testing"
echo "2. Update VERSION_3_PLAN.md with completion status"
echo "3. Write documentation and usage examples"
echo "4. Final integration testing"
echo "5. Code review and optimization"
echo "6. THEN prepare for publishing as v3.0.0"
echo ""

# Go back to project root
cd ..

echo "💡 Tip: Run 'ddev stop' when done testing"