<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\Utilities\ConnectionFactory;
use Hibla\Sql\SqlClientInterface;

final class ClientFactory
{
    private static ?SqlClientInterface $instance = null;

    public static function driver(): string
    {
        return strtolower(getenv('DATABASE') ?: 'sqlite');
    }

    public static function driverEnum(): DatabaseDriver
    {
        return match (self::driver()) {
            'pgsql', 'postgres' => DatabaseDriver::Postgres,
            'sqlite' => DatabaseDriver::Sqlite,
            default => DatabaseDriver::Mysql,
        };
    }

    public static function make(): SqlClientInterface
    {
        return self::$instance ??= ConnectionFactory::make(self::config());
    }

    public static function config(): array
    {
        return match (self::driver()) {
            'pgsql', 'postgres' => [
                'driver' => 'pgsql',
                'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('PGSQL_PORT') ?: 5443),
                'database' => getenv('PGSQL_DATABASE') ?: 'test_db',
                'username' => getenv('PGSQL_USERNAME') ?: 'postgres',
                'password' => getenv('PGSQL_PASSWORD') ?: 'postgres',
                'max_connections' => 2,
                'min_connections' => 1,
            ],
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => __DIR__ . '/../../database.sqlite',
                'max_connections' => 2,
                'min_connections' => 1,
            ],
            default => [
                'driver' => 'mysql',
                'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
                'port' => (int) (getenv('MYSQL_PORT') ?: 3306),
                'database' => getenv('MYSQL_DATABASE') ?: 'test_db',
                'username' => getenv('MYSQL_USERNAME') ?: 'test_user',
                'password' => getenv('MYSQL_PASSWORD') ?: 'test_password',
                'max_connections' => 2,
                'min_connections' => 1,
            ],
        };
    }
}
