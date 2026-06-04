<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Enums\DatabaseDriver;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;
use Hibla\QueryBuilder\Interfaces\TransactionalQueryBuilderInterface;
use Hibla\QueryBuilder\Internals\DatabaseConnection;
use Hibla\QueryBuilder\Internals\DatabaseManager;
use Hibla\Sql\IsolationLevelInterface;
use Hibla\Sql\SqlClientInterface;
use Hibla\Sql\TransactionOptions;

/**
 * Static Facade for the DatabaseManager.
 */
class DB
{
    private static ?DatabaseManager $manager = null;

    private function __construct()
    {
    }

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
     * Inject a custom SQL client directly.
     * This acts as an escape hatch for testing or bypassing the configuration file entirely.
     *
     * This will automatically register the injected client as the default connection.
     *
     * @param SqlClientInterface $client The custom client instance (e.g., a mock).
     * @param DatabaseDriver $driver The database driver (default: Mysql).
     */
    public static function setSqlClient(SqlClientInterface $client, DatabaseDriver $driver = DatabaseDriver::Mysql): void
    {
        $connectionName = 'default';
        $connection = new DatabaseConnection($client, $driver->value);

        self::getManager()->addConnection($connectionName, $connection);
        self::getManager()->setDefaultConnectionName($connectionName);
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
     * Execute a raw query and return an unbuffered stream of results.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     * @param positive-int $bufferSize
     *
     * @return PromiseInterface<\Hibla\Sql\RowStream>
     */
    public static function rawStream(string $sql, array $bindings = [], int $bufferSize = 100): PromiseInterface
    {
        return self::getManager()->rawStream($sql, $bindings, $bufferSize);
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
     * @param callable(TransactionalQueryBuilderInterface): TResult $callback
     * @param TransactionOptions|null $options
     *
     * @return PromiseInterface<TResult>
     */
    public static function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface
    {
        return self::getManager()->transaction($callback, $options);
    }

    /**
     * Begin a manual transaction on the default connection.
     *
     * @param IsolationLevelInterface|null $isolationLevel
     *
     * @return PromiseInterface<TransactionalQueryBuilderInterface>
     */
    public static function beginTransaction(?IsolationLevelInterface $isolationLevel = null): PromiseInterface
    {
        return self::getManager()->beginTransaction($isolationLevel);
    }

    /**
     * Close a specific connection pool, or all open connection pools if no name is provided.
     */
    public static function close(?string $name = null): void
    {
        self::getManager()->close($name);
    }

    /**
     * Close a specific connection pool, or all open connection pools asynchronously.
     *
     * @return PromiseInterface<void>
     */
    public static function closeAsync(?string $name = null): PromiseInterface
    {
        return self::getManager()->closeAsync($name);
    }

    /**
     * Reset the DatabaseManager (useful for tearing down tests).
     */
    public static function reset(): void
    {
        self::$manager = null;
    }
}
