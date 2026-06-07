<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Hibla\Sql\SqlClientInterface;

use function Hibla\await;

final class TestSchema
{
    public static function up(SqlClientInterface $client, string $driver): void
    {
        self::dropAll($client);
        self::createUsersTable($client, $driver);
        self::createOrdersTable($client, $driver);
    }

    public static function down(SqlClientInterface $client): void
    {
        self::dropAll($client);
    }

    public static function truncateAll(SqlClientInterface $client): void
    {
        await($client->execute('DELETE FROM orders'));
        await($client->execute('DELETE FROM users'));
    }

    private static function dropAll(SqlClientInterface $client): void
    {
        await($client->execute('DROP TABLE IF EXISTS orders'));
        await($client->execute('DROP TABLE IF EXISTS users'));
    }

    private static function createUsersTable(SqlClientInterface $client, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql', 'postgres' => "
                CREATE TABLE users (
                    id         SERIAL PRIMARY KEY,
                    name       VARCHAR(255) NOT NULL,
                    email      VARCHAR(255) UNIQUE NOT NULL,
                    status     VARCHAR(50)  NOT NULL DEFAULT 'active',
                    age        INTEGER      DEFAULT NULL,
                    score      NUMERIC(8,2) NOT NULL DEFAULT 0,
                    meta       JSONB        DEFAULT NULL, -- Added native Postgres JSONB column
                    deleted_at TIMESTAMP    DEFAULT NULL,
                    created_at TIMESTAMP    NOT NULL DEFAULT NOW()
                )
            ",
            default => "
                CREATE TABLE users (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name       VARCHAR(255) NOT NULL,
                    email      VARCHAR(255) UNIQUE NOT NULL,
                    status     VARCHAR(50)  NOT NULL DEFAULT 'active',
                    age        INT          DEFAULT NULL,
                    score      DECIMAL(8,2) NOT NULL DEFAULT 0,
                    meta       JSON         DEFAULT NULL, -- Added native MySQL JSON column
                    deleted_at TIMESTAMP    DEFAULT NULL,
                    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ",
        };

        await($client->execute($sql));
    }

    private static function createOrdersTable(SqlClientInterface $client, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql', 'postgres' => "
                CREATE TABLE orders (
                    id         SERIAL PRIMARY KEY,
                    user_id    INTEGER      NOT NULL,
                    total      NUMERIC(8,2) NOT NULL DEFAULT 0,
                    status     VARCHAR(50)  NOT NULL DEFAULT 'pending',
                    created_at TIMESTAMP    NOT NULL DEFAULT NOW()
                )
            ",
            default => "
                CREATE TABLE orders (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id    INT UNSIGNED NOT NULL,
                    total      DECIMAL(8,2) NOT NULL DEFAULT 0,
                    status     VARCHAR(50)  NOT NULL DEFAULT 'pending',
                    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ",
        };

        await($client->execute($sql));
    }

    public static function insertUsers(SqlClientInterface $client, array $rows): void
    {
        foreach ($rows as $row) {
            await($client->execute(
                'INSERT INTO users (name, email, status, age, score) VALUES (?, ?, ?, ?, ?)',
                [
                    $row['name'],
                    $row['email'],
                    $row['status'] ?? 'active',
                    $row['age'] ?? null,
                    $row['score'] ?? 0,
                ]
            ));
        }
    }

    public static function insertOrders(SqlClientInterface $client, array $rows): void
    {
        foreach ($rows as $row) {
            await($client->execute(
                'INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)',
                [
                    $row['user_id'],
                    $row['total'] ?? 0,
                    $row['status'] ?? 'pending',
                ]
            ));
        }
    }
}
