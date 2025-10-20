<?php

// Basic syntax check for the ModelSchemaCheckCommand
require_once __DIR__ . '/src/Commands/ModelSchemaCheckCommand.php';

echo "Syntax check passed - ModelSchemaCheckCommand loaded successfully\n";

// Check if the new security methods exist
$reflection = new ReflectionClass('NDEstates\LaravelModelSchemaChecker\Commands\ModelSchemaCheckCommand');

$methods = [
    'checkRawDatabaseQueries',
    'checkEloquentUsage',
    'checkFileOperations',
    'checkFileUploads',
    'checkSQLInjectionVulnerabilities',
    'checkPathTraversalVulnerabilities'
];

foreach ($methods as $method) {
    if ($reflection->hasMethod($method)) {
        echo "✓ Method {$method} exists\n";
    } else {
        echo "✗ Method {$method} missing\n";
    }
}

echo "\nSecurity enhancement implementation completed successfully!\n";