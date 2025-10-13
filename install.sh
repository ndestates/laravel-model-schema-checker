#!/bin/bash

# Laravel Model-Database Schema Checker Installation Script
# Installs the check tool in any Laravel project

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# Check if we're in a Laravel project
check_laravel_project() {
    if [ ! -f "artisan" ]; then
        print_error "This doesn't appear to be a Laravel project (artisan file not found)"
        exit 1
    fi

    if [ ! -d "app" ]; then
        print_error "This doesn't appear to be a Laravel project (app directory not found)"
        exit 1
    fi

    print_status "Laravel project detected"
}

# Detect environment (DDEV, Docker, or local)
detect_environment() {
    if command -v ddev >/dev/null 2>&1 && ddev describe >/dev/null 2>&1; then
        ENVIRONMENT="ddev"
        print_info "DDEV environment detected"
    elif [ -n "$DOCKER_CONTAINER" ] || [ -f "/.dockerenv" ]; then
        ENVIRONMENT="docker"
        print_info "Docker environment detected"
    else
        ENVIRONMENT="local"
        print_info "Local environment detected"
    fi
}

# Install via composer (preferred method)
install_via_composer() {
    print_info "Installing via Composer..."

    if command -v composer >/dev/null 2>&1; then
        composer require ndestates/laravel-model-schema-checker --dev
        print_status "Package installed via Composer"
        return 0
    else
        print_warning "Composer not found, falling back to manual installation"
        return 1
    fi
}

# Manual installation
install_manually() {
    print_info "Performing manual installation..."

    # Create check directory if it doesn't exist
    mkdir -p check/logs

    # Copy files (assuming they're in the same directory as this script)
    if [ -f "check.php" ]; then
        cp check.php .
        print_status "Copied check.php"
    else
        print_error "check.php not found in current directory"
        exit 1
    fi

    if [ -d "check" ]; then
        cp -r check/* ./check/ 2>/dev/null || true
        print_status "Copied check directory contents"
    else
        print_error "check directory not found"
        exit 1
    fi

    if [ -f "run-checker.sh" ]; then
        cp run-checker.sh .
        chmod +x run-checker.sh
        print_status "Copied and made run-checker.sh executable"
    else
        print_error "run-checker.sh not found"
        exit 1
    fi
}

# Test the installation
test_installation() {
    print_info "Testing installation..."

    # Test basic functionality
    case $ENVIRONMENT in
        "ddev")
            if ddev exec php check.php --help >/dev/null 2>&1; then
                print_status "Installation test passed (DDEV)"
            else
                print_warning "Installation test failed - check may not work properly"
            fi
            ;;
        "docker")
            if docker exec -it $(hostname) php check.php --help >/dev/null 2>&1; then
                print_status "Installation test passed (Docker)"
            else
                print_warning "Installation test failed - check may not work properly"
            fi
            ;;
        "local")
            if php check.php --help >/dev/null 2>&1; then
                print_status "Installation test passed (Local)"
            else
                print_warning "Installation test failed - check may not work properly"
            fi
            ;;
    esac
}

# Main installation process
main() {
    echo "========================================"
    echo "Laravel Model-Database Schema Checker"
    echo "Installation Script"
    echo "========================================"
    echo

    check_laravel_project
    detect_environment

    echo

    # Try composer installation first
    if ! install_via_composer; then
        install_manually
    fi

    echo
    test_installation

    echo
    echo "========================================"
    print_status "Installation complete!"
    echo
    print_info "Usage:"
    case $ENVIRONMENT in
        "ddev")
            echo "  ddev exec php check.php"
            echo "  ./run-checker.sh --fix"
            ;;
        "docker")
            echo "  docker exec -it <container> php check.php"
            echo "  ./run-checker.sh --fix"
            ;;
        "local")
            echo "  php check.php"
            echo "  ./run-checker.sh --fix"
            ;;
    esac
    echo
    print_info "For more options: php check.php --help"
    echo "========================================"
}

# Run main function
main "$@"