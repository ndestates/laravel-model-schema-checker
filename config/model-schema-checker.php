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
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Included Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be included in checks even if they match exclusion patterns.
    | For example, specific *_id fields that should be checked.
    |
    */
    'included_fields' => [
        // Add specific *_id fields here if you want them checked
        // 'specific_field_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Models
    |--------------------------------------------------------------------------
    |
    | Model classes to exclude from validation. Useful for standard Laravel models
    | or third-party package models you don't want to validate.
    |
    */
    'excluded_models' => [
        // Standard Laravel models
        'App\Models\User',

        // Common third-party models (uncomment to exclude)
        // 'Spatie\Permission\Models\Role',
        // 'Spatie\Permission\Models\Permission',
        // 'Laravel\Sanctum\PersonalAccessToken',
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
            '**/tests/**',
            '**/test/**',
            '**/.git/**',
            '**/.svn/**',
            '**/.DS_Store',
            '**/Thumbs.db',
        ],
        'models' => [
            // Exclude specific model files by path pattern
            '**/vendor/**',
        ],
        'migrations' => [
            // Exclude migration subdirectories that contain legacy/old migrations
            '**/migrations/old/**',
            '**/migrations/archive/**',
            '**/migrations/legacy/**',
            '**/migrations/backup/**',
            '**/migrations/deprecated/**',
            '**/migrations/v*/**', // Versioned migration directories
            // Exclude vendor package migrations
            '**/vendor/**',
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

    /*
    |--------------------------------------------------------------------------
    | Excluded Database Tables
    |--------------------------------------------------------------------------
    |
    | Database tables to exclude from schema validation. Useful for third-party
    | package tables or system tables you don't want to validate.
    |
    */
    'excluded_tables' => [
        // Laravel system tables
        'migrations',
        'failed_jobs',
        'cache',
        'sessions',
        'password_resets',

        // Common third-party tables (uncomment to exclude)
        // 'personal_access_tokens', // Laravel Sanctum
        // 'model_has_permissions', // Spatie Permission
        // 'model_has_roles',       // Spatie Permission
        // 'permissions',            // Spatie Permission
        // 'roles',                  // Spatie Permission
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Caching options to improve performance on large codebases.
    |
    */
    'cache' => [
        'enabled' => env('MSC_CACHE_ENABLED', true),
        'ttl' => env('MSC_CACHE_TTL', 3600), // seconds
        'store' => env('MSC_CACHE_STORE', 'file'),
    ],
];