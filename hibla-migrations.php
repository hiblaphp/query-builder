<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use function Rcalicdan\ConfigLoader\env;

return [
    /*
    |--------------------------------------------------------------------------
    | Migrations Path
    |--------------------------------------------------------------------------
    |
    | The directory where migration files are stored and loaded from.
    | You can use nested directories to organize migrations.
    |
    */
    'migrations_path' => __DIR__ . '/../database/migrations',

    /*
    |--------------------------------------------------------------------------
    | Migrations Table
    |--------------------------------------------------------------------------
    |
    | The database table name used to track which migrations have been run.
    |
    */
    'migrations_table' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Naming Convention
    |--------------------------------------------------------------------------
    |
    | The naming convention for generated migration files.
    | Supported values: "timestamp", "sequential"
    |
    */
    'naming_convention' => 'timestamp',

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone used for timestamp-based migration file names and timestamp columns.
    |
    */
    'timezone' => env('TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Recursive Migration Discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, the migration system will scan subdirectories recursively
    | for migration files. This allows you to organize migrations into folders.
    |
    */
    'recursive' => true,

    /*
    |--------------------------------------------------------------------------
    | Connection-Specific Migration Paths
    |--------------------------------------------------------------------------
    |
    | Organize migration files into subdirectories based on database connections.
    | When creating a migration with --connection flag, it will automatically
    | be placed in the mapped subdirectory.
    |
    | This is purely for FILE ORGANIZATION - it doesn't affect database behavior.
    |
    | Example:
    |   'mysql' => 'mysql'     → migrations/mysql/2024_01_01_create_users.php
    |
    |   'pgsql' => 'postgres'  → migrations/postgres/2024_01_01_create_orders.php
    |
    | Leave empty to store all migrations in the root migrations directory.
    */
    'connection_paths' => [
        // 'mysql' => 'mysql',
        // 'pgsql' => 'postgres',
        // 'sqlite' => 'sqlite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection-Specific Configuration Overrides
    |--------------------------------------------------------------------------
    |
    | Override migration settings (migrations_table, naming_convention, timezone)
    | for specific database connections. These settings affect how migrations
    | are STORED IN THE DATABASE and NAMED, not where files are located.
    |
    | Example:
    |   'pgsql' => [
    |       'migrations_table' => 'schema_versions',  // Use different table name
    |       'naming_convention' => 'sequential',      // Use 0001_, 0002_ instead of timestamps
    |       'timezone' => 'America/New_York',         // Timezone for timestamps
    |   ]
    |
    | If not specified, the connection uses the global settings defined above.
    |
    */
    'connections' => [
        // 'mysql' => [
        //     'migrations_table' => 'migrations',
        //     'naming_convention' => 'timestamp',
        //     'timezone' => 'UTC',
        // ],
    ],
];
