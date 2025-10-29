#!/bin/bash

# Test Individual Commands Script
# Tests that each command option works individually in dry-run mode

echo "🧪 Testing Individual Laravel Model Schema Checker Commands"
echo "=========================================================="

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

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "composer.json" ] || [ ! -d "src" ]; then
    print_error "Please run this from the laravel-model-schema-checker root directory"
    exit 1
fi

# Test individual commands
COMMANDS=(
    "--check-models"
    "--check-controllers"
    "--check-migrations-quality"
    "--check-laravel-forms"
    "--check-filament"
    "--check-security"
    "--check-relationships"
    "--check-migrations"
    "--check-validation"
    "--check-performance"
    "--check-code-quality"
    "--check-encrypted-fields"
)

PASSED=0
FAILED=0

for cmd in "${COMMANDS[@]}"; do
    print_status "Testing: $cmd --dry-run"

    # Try to run the command from the test-package directory
    if (cd test-package && ddev exec --dir=/var/www/html/test-package php artisan model:schema-check $cmd --dry-run > /dev/null 2>&1); then
        print_success "✅ $cmd works individually"
        ((PASSED++))
    else
        print_error "❌ $cmd failed"
        ((FAILED++))
    fi
    echo ""
done

echo "📊 Test Results:"
echo "✅ Passed: $PASSED"
echo "❌ Failed: $FAILED"

if [ $FAILED -eq 0 ]; then
    print_success "🎉 All individual commands work correctly in dry-run mode!"
else
    print_error "⚠️  Some commands failed. Check the output above."
fi