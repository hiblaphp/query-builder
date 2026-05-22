<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Sql\SqlClientInterface;
use Hibla\Sql\TransactionOptions;

interface DatabaseConnectionInterface extends RawQueryInterface
{
    /**
     * Start a new query builder instance for the given table.
     */
    public function table(string $table): QueryBuilderInterface;

    /**
     * Execute a callback within an automatically managed transaction.
     *
     * @template TResult
     *
     * @param callable(DatabaseTransactionInterface): TResult $callback
     * @param TransactionOptions|null $options
     *
     * @return PromiseInterface<TResult>
     */
    public function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface;

    /**
     * Manually begin a transaction.
     *
     * @return PromiseInterface<DatabaseTransactionInterface>
     */
    public function beginTransaction(): PromiseInterface;

    /**
     * Get the underlying raw client instance.
     */
    public function getClient(): SqlClientInterface;

    /**
     * Get the driver name (e.g., 'mysql', 'pgsql').
     */
    public function getDriverName(): string;
}
