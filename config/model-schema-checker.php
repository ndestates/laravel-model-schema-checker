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
];