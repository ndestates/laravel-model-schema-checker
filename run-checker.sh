#!/bin/bash

# Laravel Model-Database Schema Checker Runner
# Automatically detects environment and uses appropriate PHP command

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHECKER_PATH="$SCRIPT_DIR/check/check.php"

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to detect environment
detect_environment() {
    if [ -n "$DDEV_PROJECT" ]; then
        echo "ddev"
    elif [ -f "/.dockerenv" ] || [ -n "$DOCKER_CONTAINER" ]; then
        echo "docker"
    elif command_exists ddev && ddev describe >/dev/null 2>&1; then
        echo "ddev_available"
    elif command_exists php; then
        echo "local_php"
    else
        echo "unknown"
    fi
}

# Function to run PHP command based on environment
run_php() {
    local php_cmd="$1"
    shift

    case "$(detect_environment)" in
        "ddev")
            echo "Running in DDEV environment..."
            # Use relative path in DDEV container
            local relative_path="check/check.php"
            ddev exec php "$relative_path" "$@"
            ;;
        "docker")
            echo "Running in Docker container..."
            # Try common PHP paths in containers
            if command_exists php; then
                php "$php_cmd" "$@"
            elif [ -f "/usr/local/bin/php" ]; then
                /usr/local/bin/php "$php_cmd" "$@"
            elif [ -f "/usr/bin/php" ]; then
                /usr/bin/php "$php_cmd" "$@"
            else
                echo "Error: PHP not found in Docker container"
                exit 1
            fi
            ;;
        "ddev_available")
            echo "DDEV project detected, using DDEV..."
            # Use relative path in DDEV container
            local relative_path="check/check.php"
            ddev exec php "$relative_path" "$@"
            ;;
        "local_php")
            echo "Running with local PHP..."
            php "$php_cmd" "$@"
            ;;
        *)
            echo "Error: No suitable PHP environment found."
            echo ""
            echo "Please ensure one of the following:"
            echo "1. You're in a DDEV project: ddev start && ddev exec php ..."
            echo "2. PHP is installed locally: apt install php-cli"
            echo "3. You're in a Docker container with PHP"
            echo ""
            echo "Or run manually:"
            echo "  ddev exec php check/check.php [options]"
            echo "  php $CHECKER_PATH [options]"
            exit 1
            ;;
    esac
}

# Show usage if no arguments or help requested
if [ $# -eq 0 ] || [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
    echo "Laravel Model-Database Schema Checker"
    echo ""
    echo "USAGE:"
    echo "  $0 [options]"
    echo ""
    echo "OPTIONS:"
    echo "  --fix                    Fix model \$fillable properties automatically"
    echo "  --dry-run               Show what would be changed without applying"
    echo "  --generate-migrations   Generate Laravel migrations"
    echo "  --run-migrations        Run migrations after generating them"
    echo "  --backup-db             Create database backup before running migrations"
    echo "  --backup                Show database backup recommendations"
    echo "  --json                  Output results in JSON format"
    echo "  --generate-schema       Generate database schema without data"
    echo "  --check-filament-forms  Check for broken relationships in Filament forms"
    echo "  --check-all             Run all available checks (model comparison, Filament checks)"
    echo "  --help, -h              Show this help message"
    echo ""
    echo "ENVIRONMENTS:"
    echo "  Automatically detects and uses:"
    echo "  - DDEV (ddev exec php)"
    echo "  - Docker containers"
    echo "  - Local PHP installation"
    echo ""
    echo "EXAMPLES:"
    echo "  $0                           # Compare models with database"
    echo "  $0 --fix                    # Fix all model fillable properties"
    echo "  $0 --dry-run                # Preview changes without applying"
    echo "  $0 --json                   # Output results in JSON format"
    echo "  $0 --generate-schema --json # Generate schema as JSON for analysis"
    echo "  $0 --generate-migrations   # Generate Laravel migrations"
    echo "  $0 --generate-migrations --run-migrations  # Generate and run migrations"
    echo "  $0 --generate-migrations --backup-db --run-migrations  # Backup, generate, and run migrations"
    echo "  $0 --backup --fix           # Show backup commands and fix models"
    echo ""
    exit 0
fi

# Check if checker script exists
if [ ! -f "$CHECKER_PATH" ]; then
    echo "Error: Checker script not found at $CHECKER_PATH"
    echo "Please ensure the modular checker is properly installed."
    exit 1
fi

# Run the checker with all arguments
run_php "$CHECKER_PATH" "$@"
