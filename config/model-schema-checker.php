<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model Directory
    |--------------------------------------------------------------------------
    |
    | The directory where your Laravel models are located.
    |
    */
    'models_dir' => app_path('Models'),

    /*
    |--------------------------------------------------------------------------
    | Excluded Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be excluded from fillable property checks.
    |
    */
    'excluded_fields' => [
        'id',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
        'email_verified_at',
        'remember_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for schema analysis.
    |
    */
    'database_connection' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Migrations Directory
    |--------------------------------------------------------------------------
    |
    | The directory where your Laravel migrations are located.
    |
    */
    'migrations_dir' => database_path('migrations'),

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Whether to enable database backup recommendations.
    |
    */
    'backup_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Migration Validation Mode
    |--------------------------------------------------------------------------
    |
    | Choose how migrations are validated:
    | - 'migration_files': Validate migration files only (database-agnostic)
    | - 'database_schema': Validate current database schema
    | - 'both': Validate both migration files and database schema
    |
    */
    'migration_validation_mode' => env('MSC_MIGRATION_MODE', 'migration_files'),

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Different validation modes for different environments.
    | Keys should match APP_ENV values (local, testing, staging, production)
    |
    */
    'environments' => [
        'local' => [
            'strict_mode' => false,
            'skip_performance_checks' => true,
            'allow_missing_indexes' => true,
        ],
        'testing' => [
            'strict_mode' => true,
            'skip_performance_checks' => false,
            'allow_missing_indexes' => false,
        ],
        'staging' => [
            'strict_mode' => true,
            'skip_performance_checks' => false,
            'allow_missing_indexes' => false,
        ],
        'production' => [
            'strict_mode' => true,
            'skip_performance_checks' => false,
            'allow_missing_indexes' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File and Path Exclusions
    |--------------------------------------------------------------------------
    |
    | Patterns to exclude from validation. Supports glob patterns.
    |
    */
    'exclude_patterns' => [
        'files' => [
            '**/vendor/**',
            '**/node_modules/**',
            '**/storage/**',
            '**/bootstrap/cache/**',
            '**/*.log',
            '**/tests/**', // Can be overridden for testing environments
        ],
        'models' => [
            // Exclude specific model files
        ],
        'migrations' => [
            // Exclude specific migration files
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for performance-related checks (in milliseconds)
    |
    */
    'performance_thresholds' => [
        'query_timeout' => env('MSC_QUERY_TIMEOUT', 1000), // ms
        'memory_limit' => env('MSC_MEMORY_LIMIT', 128), // MB
        'max_relationships_per_model' => env('MSC_MAX_RELATIONSHIPS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Output and Reporting
    |--------------------------------------------------------------------------
    |
    | Configure output format and reporting options
    |
    */
    'output' => [
        'format' => env('MSC_OUTPUT_FORMAT', 'console'), // console, json, xml
        'verbose' => env('MSC_VERBOSE', false),
        'show_progress' => env('MSC_SHOW_PROGRESS', true),
        'fail_on_warnings' => env('MSC_FAIL_ON_WARNINGS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Rules
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific validation rules
    |
    */
    'rules' => [
        'enabled' => [
            'model_fillable_check' => true,
            'relationship_integrity' => true,
            'migration_syntax' => true,
            'security_checks' => true,
            'performance_checks' => true,
            'code_quality' => true,
        ],
        'custom' => [
            // Space for custom validation rules
        ],
    ],
];