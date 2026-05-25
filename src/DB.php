<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseTransactionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\Sql\SqlClientInterface;

/**
 * Static Facade for the DatabaseManager.
 */
class DB
{
    private static ?DatabaseManager $manager = null;

    private function __construct() {}

    /**
     * Get the singleton DatabaseManager instance internally.
     */
    private static function getManager(): DatabaseManager
    {
        if (self::$manager === null) {
            self::$manager = new DatabaseManager();
        }

        return self::$manager;
    }

    /**
     * Register a new database connection dynamically.
     */
    public static function addConnection(string $name, DatabaseConnectionInterface $connection): void
    {
        self::getManager()->addConnection($name, $connection);
    }

    /**
     * Remove an existing database connection.
     */
    public static function removeConnection(string $name): void
    {
        self::getManager()->removeConnection($name);
    }

    /**
     * Resolve a raw SQL client from a configuration array.
     * Useful for dynamic connections or schema builders.
     *
     * @param array<string, mixed> $config
     */
    public static function resolveClientFromConfig(array $config): SqlClientInterface
    {
        return self::getManager()->resolveClientFromConfig($config);
    }

    /**
     * Get a specific connection instance.
     */
    public static function connection(?string $name = null): DatabaseConnectionInterface
    {
        return self::getManager()->connection($name);
    }

    /**
     * Start a new query builder on the default connection.
     */
    public static function table(string $table): QueryBuilderInterface
    {
        return self::getManager()->table($table);
    }

    /**
     * Execute a raw query and return all rows.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<array<int, array<string, mixed>>|array<int, object>>
     */
    public static function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return self::getManager()->raw($sql, $bindings);
    }

    /**
     * Execute a raw query and return the first result.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<array<string, mixed>|object|null>
     */
    public static function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return self::getManager()->rawFirst($sql, $bindings);
    }

    /**
     * Execute a raw query and return a single scalar value.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<mixed>
     */
    public static function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return self::getManager()->rawValue($sql, $bindings);
    }

    /**
     * Execute a raw statement (INSERT, UPDATE, DELETE).
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     *
     * @return PromiseInterface<int> The number of affected rows.
     */
    public static function rawExecute(string $sql, array $bindings = []): PromiseInterface
    {
        return self::getManager()->rawExecute($sql, $bindings);
    }

    /**
     * Execute an auto-managed transaction on the default connection.
     *
     * @template TResult
     *
     * @param callable(DatabaseTransactionInterface): TResult $callback
     *
     * @return PromiseInterface<TResult>
     */
    public static function transaction(callable $callback): PromiseInterface
    {
        return self::getManager()->transaction($callback);
    }

    /**
     * Begin a manual transaction on the default connection.
     *
     * @return PromiseInterface<DatabaseTransactionInterface>
     */
    public static function beginTransaction(): PromiseInterface
    {
        return self::getManager()->beginTransaction();
    }

    /**
     * Reset the DatabaseManager (useful for tests).
     */
    public static function reset(): void
    {
        self::$manager = null;
    }
}
