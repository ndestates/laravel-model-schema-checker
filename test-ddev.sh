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
if [ ! -f "composer.json" ] || [ ! -f "check.php" ]; then
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
echo "🎉 SUCCESS! Package is ready for publishing"
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
echo "🚀 Publishing Steps:"
echo "1. git add . && git commit -m 'Ready for release'"
echo "2. git push origin feature/develop"
echo "3. git checkout master && git merge feature/develop"
echo "4. git tag v1.0.0"
echo "5. git push origin master --tags"
echo "6. Submit to Packagist: https://packagist.org/packages/submit"
echo ""

# Go back to project root
cd ..

echo "💡 Tip: Run 'ddev stop' when done testing"