<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for security checks and compliance requirements.
    | This ensures the Laravel Model Schema Checker follows modern
    | cybersecurity essentials and best practices.
    |
    */

    'enabled' => env('LMSC_SECURITY_ENABLED', true),

    'environments' => [
        'production' => [
            'enabled' => false, // Never run security analysis in production
            'restrict_to_developers' => true,
        ],
        'staging' => [
            'enabled' => true,
            'restrict_to_developers' => false,
        ],
        'testing' => [
            'enabled' => true,
            'restrict_to_developers' => false,
        ],
        'local' => [
            'enabled' => true,
            'restrict_to_developers' => false,
        ],
    ],

    'access_control' => [
        'allowed_ips' => env('LMSC_ALLOWED_IPS', null), // Comma-separated IPs
        'allowed_users' => env('LMSC_ALLOWED_USERS', null), // Comma-separated user IDs
        'require_auth' => env('LMSC_REQUIRE_AUTH', false),
    ],

    'security_checks' => [
        'mass_assignment' => [
            'enabled' => true,
            'severity' => 'high', // critical, high, medium, low
        ],
        'authentication_bypass' => [
            'enabled' => true,
            'severity' => 'high',
        ],
        'data_exposure' => [
            'enabled' => true,
            'severity' => 'high',
        ],
        'sql_injection' => [
            'enabled' => true,
            'severity' => 'critical',
        ],
        'xss' => [
            'enabled' => true,
            'severity' => 'high',
        ],
        'csrf' => [
            'enabled' => true,
            'severity' => 'high',
        ],
        'path_traversal' => [
            'enabled' => true,
            'severity' => 'critical',
        ],
        'secure_headers' => [
            'enabled' => true,
            'severity' => 'medium',
        ],
        'secure_config' => [
            'enabled' => true,
            'severity' => 'critical',
        ],
        'dependency_security' => [
            'enabled' => true,
            'severity' => 'medium',
        ],
        'secure_coding' => [
            'enabled' => true,
            'severity' => 'medium',
        ],
        'input_validation' => [
            'enabled' => true,
            'severity' => 'high',
        ],
        'error_handling' => [
            'enabled' => true,
            'severity' => 'medium',
        ],
    ],

    'file_security' => [
        'allowed_extensions' => ['php', 'blade.php'],
        'blocked_paths' => [
            'vendor/',
            'node_modules/',
            'storage/logs/',
            '.git/',
        ],
        'max_file_size' => 1024 * 1024, // 1MB
        'scan_hidden_files' => false,
    ],

    'database_security' => [
        'allow_live_database' => false, // Never scan live databases
        'require_ssl' => true,
        'mask_sensitive_data' => true,
        'allowed_tables' => null, // null = all tables, or array of allowed tables
    ],

    'reporting' => [
        'anonymize_paths' => false,
        'include_file_contents' => false, // Don't include actual file contents in reports
        'max_issues_per_type' => 100,
        'severity_threshold' => 'low', // Report issues at this level and above
    ],

    'audit' => [
        'enabled' => true,
        'log_file' => storage_path('logs/lmsc-audit.log'),
        'log_operations' => true,
        'log_errors' => true,
    ],
];