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