<?php

declare(strict_types=1);

namespace Hibla\QueryBuilder\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Sql\IsolationLevelInterface;
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
     * @param callable(TransactionalQueryBuilderInterface): TResult $callback
     * @param TransactionOptions|null $options
     *
     * @return PromiseInterface<TResult>
     */
    public function transaction(callable $callback, ?TransactionOptions $options = null): PromiseInterface;

    /**
     * Manually begin a transaction.
     *
     * @param IsolationLevelInterface|null $isolationLevel
     *
     * @return PromiseInterface<TransactionalQueryBuilderInterface>
     */
    public function beginTransaction(?IsolationLevelInterface $isolationLevel = null): PromiseInterface;

    /**
     * Get the underlying raw client instance.
     */
    public function getClient(): SqlClientInterface;

    /**
     * Get the driver name (e.g., 'mysql', 'pgsql').
     */
    public function getDriverName(): string;

    /**
     * Close the database connection pool synchronously.
     */
    public function close(): void;

    /**
     * Close the database connection pool asynchronously.
     *
     * @return PromiseInterface<void>
     */
    public function closeAsync(): PromiseInterface;
}
