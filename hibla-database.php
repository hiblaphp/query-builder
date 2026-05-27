<?php

declare(strict_types=1);

use function Rcalicdan\ConfigLoader\env;

require 'vendor/autoload.php';

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | This option controls the default database connection that will be used
    | by your application.
    |
    */
    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are the database connections configured for your application.
    |
    */
    'connections' => [
        'connect_b' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST_CONNECT_B', '127.0.0.1'),
            'port' => env('DB_PORT_CONNECT_B', 3306, true),
            'database' => env('DB_DATABASE_CONNECT_B', 'test'),
            'username' => env('DB_USERNAME_CONNECT_B', 'root'),
            'password' => env('DB_PASSWORD_CONNECT_B', ''),
            'max_connections' => env('DB_MAX_CONNECTIONS', 10, convertNumeric: true),
            'min_connections' => env('DB_MIN_CONNECTIONS', 0, convertNumeric: true),
            'enable_server_side_cancellation' => env('DB_ENABLE_SERVER_SIDE_CANCELLATION', false),
            'compress' => env('DB_COMPRESS', false),
            'charset' => 'utf8mb4',
            'idle_timeout' => 60,
            'max_lifetime' => 3600,
            'max_waiters' => 0,
            'acquire_timeout' => 10.0,
            'enable_statement_cache' => true,
            'statement_cache_size' => 256,
            'connect_timeout' => 10,
            'reset_connection' => false,
            'multi_statements' => false,
            'cast_prepared_types' => true,
            'ssl' => false,
            'ssl_verify' => false,
            'ssl_ca' => null,
            'ssl_cert' => null,
            'ssl_key' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306, true),
            'database' => env('DB_DATABASE', 'test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'max_connections' => env('DB_MAX_CONNECTIONS', 10, convertNumeric: true),
            'min_connections' => env('DB_MIN_CONNECTIONS', 0, convertNumeric: true),
            'enable_server_side_cancellation' => env('DB_ENABLE_SERVER_SIDE_CANCELLATION', false),
            'compress' => env('DB_COMPRESS', false),
            'charset' => 'utf8mb4',
            'idle_timeout' => 60,
            'max_lifetime' => 3600,
            'max_waiters' => 0,
            'acquire_timeout' => 10.0,
            'enable_statement_cache' => true,
            'statement_cache_size' => 256,
            'connect_timeout' => 10,
            'reset_connection' => false,
            'multi_statements' => false,
            'cast_prepared_types' => true,
            'ssl' => false,
            'ssl_verify' => false,
            'ssl_ca' => null,
            'ssl_cert' => null,
            'ssl_key' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Templates
    |--------------------------------------------------------------------------
    |
    | Configure where pagination templates should be published and loaded from.
    |
    | - 'templates_path': The directory where templates will be published and loaded.
    |                     Set to null to use the default built-in templates.
    | - 'default_template': The default pagination template to use.
    | - 'default_cursor_template': The default cursor pagination template to use.
    |
    | To publish templates, run: php hibla-db publish:templates
    | The templates will be copied to the path specified below.
    |
    */
    'pagination' => [
        'templates_path' => null,
        'default_template' => 'tailwind',
        'default_cursor_template' => 'cursor-tailwind',
    ],
];
