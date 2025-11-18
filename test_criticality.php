<?php

require 'vendor/autoload.php';

use NDEstates\LaravelModelSchemaChecker\Services\MigrationCriticalityAnalyzer;

$analyzer = new MigrationCriticalityAnalyzer();
$analysis = $analyzer->analyzeMigrations('test-migrations');

echo "=== Migration Criticality Analysis ===\n";
echo "Total migrations: " . $analysis['migration_count'] . "\n";
echo "Issues found: " . $analysis['issues_found'] . "\n";
echo "Data mapping required: " . ($analysis['data_mapping_required'] ? 'YES' : 'NO') . "\n";
echo "Rerun risk level: " . $analysis['rerun_risk_level'] . "\n\n";

$levels = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'LEAST'];
foreach ($levels as $level) {
    $issues = $analysis['criticality'][$level] ?? [];
    if (!empty($issues)) {
        echo "$level (" . count($issues) . " issues):\n";
        foreach ($issues as $issue) {
            echo "  - {$issue['description']}\n";
        }
        echo "\n";
    }
}

if (!empty($analysis['recommendations'])) {
    echo "Recommendations:\n";
    foreach ($analysis['recommendations'] as $rec) {
        echo "  - [{$rec['priority']}] {$rec['action']}\n";
        echo "    {$rec['reason']}\n";
    }
}