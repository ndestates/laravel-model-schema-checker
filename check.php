<?php

/**
 * Laravel Model Schema Checker
 *
 * This script validates Laravel model fillable properties against database schema
 * and checks Filament relationship integrity.
 *
 * Usage:
 *   php check.php --generate-migrations  # Generate Laravel migrations
 *   php check.php --backup           # Create database backup recommendations
 */

// Function to bootstrap Laravel - detects Laravel project automatically
function bootstrapLaravel() {
    // When installed in a Laravel project, bootstrap from project root
    $projectBootstrap = getcwd() . '/bootstrap/app.php';
    if (file_exists($projectBootstrap)) {
        $app = require_once $projectBootstrap;
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        return $app;
    }

    // Fallback: check package directory (for development)
    $packageBootstrap = __DIR__ . '/../bootstrap/app.php';
    if (file_exists($packageBootstrap)) {
        $app = require_once $packageBootstrap;
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        return $app;
    }

    // Laravel not found
    echo "Warning: Laravel application not detected. Some features may not work.\n";
    return null;
}

// Only bootstrap if this file is being executed directly (not just included)
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $app = bootstrapLaravel();
}

// Load classes directly
require_once __DIR__ . '/check/config/CheckConfig.php';
require_once __DIR__ . '/check/utils/Logger.php';
require_once __DIR__ . '/check/utils/FileHelper.php';
require_once __DIR__ . '/check/utils/PatternMatcher.php';
require_once __DIR__ . '/check/services/ModelAnalyzer.php';
require_once __DIR__ . '/check/services/DatabaseAnalyzer.php';
require_once __DIR__ . '/check/services/RelationshipAnalyzer.php';
require_once __DIR__ . '/check/services/MigrationGenerator.php';
require_once __DIR__ . '/check/services/CommandRunner.php';
require_once __DIR__ . '/check/commands/CompareCommand.php';
require_once __DIR__ . '/check/commands/FixCommand.php';
require_once __DIR__ . '/check/commands/GenerateMigrationsCommand.php';
require_once __DIR__ . '/check/commands/AnalyzeCommand.php';
require_once __DIR__ . '/check/commands/GenerateSchemaCommand.php';
require_once __DIR__ . '/check/commands/GenerateSchemaSqlCommand.php';
require_once __DIR__ . '/check/commands/CheckFilamentRelationshipsCommand.php';

use Check\Config\CheckConfig;
use Check\Services\ModelAnalyzer;
use Check\Services\DatabaseAnalyzer;
use Check\Services\RelationshipAnalyzer;
use Check\Commands\AnalyzeCommand;
use Check\Commands\CompareCommand;
use Check\Commands\FixCommand;
use Check\Commands\GenerateMigrationsCommand;
use Check\Commands\GenerateSchemaCommand;
use Check\Commands\GenerateSchemaSqlCommand;
use Check\Commands\CheckFilamentRelationshipsCommand;
use Check\Utils\Logger;

try {
    // Only execute when run directly, not when required as a library
    $isMainScript = realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']);

    if (!$isMainScript) {
        return;
    }

    // Try to bootstrap Laravel if available
    $app = bootstrapLaravel();

    // Initialize configuration
    $config = new CheckConfig();
    $logger = new Logger($config->getLogFile());

    // Parse command line arguments
    $flags = [
        'fix' => in_array('--fix', $argv),
        'dry_run' => in_array('--dry-run', $argv),
        'generate_migrations' => in_array('--generate-migrations', $argv),
        'run_migrations' => in_array('--run-migrations', $argv),
        'backup_db' => in_array('--backup-db', $argv),
        'backup' => in_array('--backup', $argv),
        'json' => in_array('--json', $argv),
        'analyze' => in_array('--analyze', $argv),
        'help' => in_array('--help', $argv) || in_array('-h', $argv),
        'generate_schema' => in_array('--generate-schema', $argv),
        'generate_schema_sql' => in_array('--generate-schema-sql', $argv),
        'check_filament' => in_array('--check-filament', $argv),
        'check_all' => in_array('--check-all', $argv),
    ];

    // Show help if requested
    if ($flags['help']) {
        showHelp();
        exit(0);
    }

    // Initialize services
    $modelAnalyzer = new ModelAnalyzer($config, $logger);
    $dbAnalyzer = new DatabaseAnalyzer($config, $logger);
    $relationshipAnalyzer = new RelationshipAnalyzer($logger);
    $commandRunner = new \Check\Services\CommandRunner($logger);

    // Show backup recommendations if requested or if fixing
    if ($flags['backup'] || $flags['fix']) {
        $dbAnalyzer->generateBackupCommands();
    }

    // Execute appropriate command
    if ($flags['analyze']) {
        $command = new AnalyzeCommand($logger, $commandRunner);
        $command->execute();
    } elseif ($flags['generate_migrations']) {
        $command = new GenerateMigrationsCommand(
            $config,
            $logger,
            $modelAnalyzer,
            $dbAnalyzer,
            new \Check\Services\MigrationGenerator($config, $logger, $dbAnalyzer),
            new \Check\Services\RelationshipAnalyzer($logger),
            $commandRunner
        );
        $command->execute($flags);
    } elseif ($flags['fix'] || $flags['dry_run']) {
        $command = new FixCommand($config, $logger, $modelAnalyzer, $dbAnalyzer);
        $command->execute($flags);
    } elseif ($flags['generate_schema']) {
        $command = new GenerateSchemaCommand($config, $logger, $dbAnalyzer);
        $command->execute($flags);
    } elseif ($flags['generate_schema_sql']) {
        $command = new GenerateSchemaSqlCommand($config, $logger, $commandRunner);
        $command->execute($flags);
    } elseif ($flags['check_filament']) {
        $command = new CheckFilamentRelationshipsCommand($config, $logger);
        $command->execute();
    } elseif ($flags['check_all']) {
        echo "=== RUNNING ALL CHECKS ===\n";
        $logger->section("RUNNING ALL CHECKS");

        // Run model comparison
        echo "Running model-database comparison...\n";
        $logger->info("Running model-database comparison...");
        $command = new CompareCommand($config, $logger, $modelAnalyzer, $dbAnalyzer);
        $command->execute($flags);

        // Run Filament relationships check
        echo "Running Filament relationships check...\n";
        $logger->info("Running Filament relationships check...");
        $command = new CheckFilamentRelationshipsCommand($config, $logger);
        $command->execute();

        echo "All checks completed. Check the log file for details.\n";
        $logger->success("All checks completed");
    } else {
        $command = new CompareCommand($config, $logger, $modelAnalyzer, $dbAnalyzer);
        $command->execute($flags);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (isset($logger)) {
        $logger->error("Fatal error: " . $e->getMessage());
    }
    exit(1);
}

function showHelp(): void
{
    echo "Laravel Model-Database Schema Checker\n\n";
    echo "USAGE:\n";
    echo "  php check.php [options]\n";
    echo "  ./run-checker.sh [options]  # Auto-detects environment\n\n";
    echo "OPTIONS:\n";
    echo "  --fix                    Fix model \$fillable properties automatically\n";
    echo "  --dry-run               Show what would be changed without applying\n";
    echo "  --analyze               Run static analysis using Larastan\n";
    echo "  --generate-migrations   Generate Laravel migrations\n";
    echo "  --run-migrations        Run migrations after generating them\n";
    echo "  --backup-db             Create database backup before running migrations\n";
    echo "  --backup                Show database backup recommendations\n";
    echo "  --json                  Output results in JSON format\n";
    echo "  --generate-schema       Generate database schema without data (JSON/array)\n";
    echo "  --generate-schema-sql   Generate database schema as SQL\n";
    echo "  --check-filament        Check for broken relationships in Filament resources\n";
    echo "  --check-all             Run all available checks (model comparison, Filament relationships)\n";
    echo "  --help, -h              Show this help message\n\n";
    echo "ENVIRONMENTS:\n";
    echo "  Works automatically in:\n";
    echo "  - DDEV projects (uses 'ddev exec php')\n";
    echo "  - Docker containers\n";
    echo "  - Local PHP installations\n\n";
    echo "EXAMPLES:\n";
    echo "  php check.php                      # Compare models with database\n";
    echo "  php check.php --fix               # Fix all model fillable properties\n";
    echo "  php check.php --dry-run           # Preview changes without applying\n";
    echo "  php check.php --analyze           # Run static analysis\n";
    echo "  php check.php --json              # Output results in JSON format\n";
    echo "  ./run-checker.sh --backup --fix   # Auto-detect environment and fix models\n\n";
}


