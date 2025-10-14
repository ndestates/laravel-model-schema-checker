#!/bin/bash

# Post-start hook for DDEV
# This runs after DDEV starts up

echo "Setting up Laravel Model Schema Checker development environment..."

# Install composer dependencies if they don't exist
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-progress --prefer-dist
fi

echo "✅ Development environment ready!"