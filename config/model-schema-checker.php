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
    | Security Excluded Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be excluded from fillable property checks for security reasons.
    | These fields typically contain sensitive information and should not be mass-assignable.
    |
    */
    'security_excluded_fields' => [
        'email_verified_at',
        'remember_token',
        'password',
        'password_confirmation',
        'api_token',
        'access_token',
        'refresh_token',
        'verification_token',
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
    | Constraint Check Settings
    |--------------------------------------------------------------------------
    |
    | Whether to ignore ID columns in constraint checks.
    |
    */
    'ignore_id_columns_in_constraint_check' => true,
];