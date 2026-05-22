<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseConnectionInterface;
use Hibla\QueryBuilder\Interfaces\DatabaseTransactionInterface;
use Hibla\QueryBuilder\Interfaces\QueryBuilderInterface;

/**
 * Static Facade for the DatabaseManager.
 */
class DB
{
    private static ?DatabaseManager $manager = null;

    private function __construct() {}

    /**
     * Get the singleton DatabaseManager instance.
     */
    public static function getManager(): DatabaseManager
    {
        if (self::$manager === null) {
            self::$manager = new DatabaseManager();
        }

        return self::$manager;
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

    public static function raw(string $sql, array $bindings = []): PromiseInterface
    {
        return self::getManager()->raw($sql, $bindings);
    }

    public static function rawFirst(string $sql, array $bindings = []): PromiseInterface
    {
        return self::getManager()->rawFirst($sql, $bindings);
    }

    public static function rawValue(string $sql, array $bindings = []): PromiseInterface
    {
        return self::getManager()->rawValue($sql, $bindings);
    }

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
