#!/bin/bash#!/bin/bash



# Laravel Model Schema Checker Installation Script# Laravel Model-Database Schema Checker Installation Script

# This script helps install the package via Composer# Installs the check tool in any Laravel project



set -eset -e  # Exit on any error



# Color codes for output# Colors for output

RED='\033[0;31m'RED='\033[0;31m'

GREEN='\033[0;32m'GREEN='\033[0;32m'

YELLOW='\033[1;33m'YELLOW='\033[1;33m'

BLUE='\033[0;34m'BLUE='\033[0;34m'

NC='\033[0m' # No ColorNC='\033[0m' # No Color



# Print functions# Function to print colored output

print_info() {print_status() {

    echo -e "${BLUE}ℹ️  $1${NC}"    echo -e "${GREEN}✓${NC} $1"

}}



print_status() {print_warning() {

    echo -e "${GREEN}✅ $1${NC}"    echo -e "${YELLOW}⚠${NC} $1"

}}



print_warning() {print_error() {

    echo -e "${YELLOW}⚠️  $1${NC}"    echo -e "${RED}✗${NC} $1"

}}



print_error() {print_info() {

    echo -e "${RED}❌ $1${NC}"    echo -e "${BLUE}ℹ${NC} $1"

}}



# Check if we're in a Laravel project# Check if we're in a Laravel project

check_laravel_project() {check_laravel_project() {

    if [ ! -f "artisan" ] || [ ! -d "app" ]; then    if [ ! -f "artisan" ]; then

        print_error "This doesn't appear to be a Laravel project directory."        print_error "This doesn't appear to be a Laravel project (artisan file not found)"

        print_info "Please run this script from your Laravel project root directory."        exit 1

        exit 1    fi

    fi

}    if [ ! -d "app" ]; then

        print_error "This doesn't appear to be a Laravel project (app directory not found)"

# Check if composer is available        exit 1

check_composer() {    fi

    if ! command -v composer &> /dev/null; then

        print_error "Composer is not installed or not in PATH."    print_status "Laravel project detected"

        print_info "Please install Composer: https://getcomposer.org/"}

        exit 1

    fi# Detect environment (DDEV, Docker, or local)

}detect_environment() {

    if command -v ddev >/dev/null 2>&1 && ddev describe >/dev/null 2>&1; then

# Install the package        ENVIRONMENT="ddev"

install_package() {        print_info "DDEV environment detected"

    print_info "Installing Laravel Model Schema Checker..."    elif [ -n "$DOCKER_CONTAINER" ] || [ -f "/.dockerenv" ]; then

        ENVIRONMENT="docker"

    # Install as dev dependency (recommended for development/testing)        print_info "Docker environment detected"

    composer require ndestates/laravel-model-schema-checker --dev    else

        ENVIRONMENT="local"

    if [ $? -eq 0 ]; then        print_info "Local environment detected"

        print_status "Package installed successfully!"    fi

    else}

        print_error "Failed to install package."

        exit 1# Install via composer (preferred method)

    fiinstall_via_composer() {

}    print_info "Installing via Composer..."



# Test the installation    if command -v composer >/dev/null 2>&1; then

test_installation() {        composer require ndestates/laravel-model-schema-checker --dev

    print_info "Testing installation..."        print_status "Package installed via Composer"

        return 0

    if php artisan model:schema-check --help >/dev/null 2>&1; then    else

        print_status "Installation test passed!"        print_warning "Composer not found, falling back to manual installation"

    else        return 1

        print_warning "Installation test failed - the command may not be available."    fi

        print_info "Try running: composer dump-autoload"}

    fi

}# Manual installation

install_manually() {

# Main installation process    print_info "Performing manual installation..."

main() {

    echo "========================================"    # Create check directory if it doesn't exist

    echo "Laravel Model Schema Checker"    mkdir -p check/logs

    echo "Installation Script"

    echo "========================================"    # Copy files (assuming they're in the same directory as this script)

    echo    if [ -f "check.php" ]; then

        cp check.php .

    check_laravel_project        print_status "Copied check.php"

    check_composer    else

        print_error "check.php not found in current directory"

    echo        exit 1

    install_package    fi

    echo

    test_installation    if [ -d "check" ]; then

        cp -r check/* ./check/ 2>/dev/null || true

    echo        print_status "Copied check directory contents"

    echo "========================================"    else

    print_status "Installation complete!"        print_error "check directory not found"

    echo        exit 1

    print_info "Usage:"    fi

    echo "  php artisan model:schema-check              # Run basic checks"

    echo "  php artisan model:schema-check --fix        # Fix issues automatically"    if [ -f "run-checker.sh" ]; then

    echo "  php artisan model:schema-check --help       # Show all options"        cp run-checker.sh .

    echo        chmod +x run-checker.sh

    print_info "For more information, see: https://github.com/ndestates/laravel-model-schema-checker"        print_status "Copied and made run-checker.sh executable"

    echo "========================================"    else

}        print_error "run-checker.sh not found"

        exit 1

# Run main function    fi

main "$@"}

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